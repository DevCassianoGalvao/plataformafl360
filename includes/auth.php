<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function url(string $path = ''): string
{
    $base = rtrim(BASE_PATH, '/');
    $path = ltrim($path, '/');

    if ($base === '') {
        return $path === '' ? '/' : '/' . $path;
    }

    return $path === '' ? $base : $base . '/' . $path;
}

function redirect(string $path): void
{
    $target = preg_match('#^https?://#i', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = (string) $user['role'];
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function current_user(PDO $pdo): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    static $cachedUser = null;
    if ($cachedUser !== null) {
        return $cachedUser;
    }

    $stmt = $pdo->prepare('SELECT id, nome, email, role, criado_em, foto_perfil FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        logout_user();
        return null;
    }

    $cachedUser = $user;
    return $cachedUser;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Você precisa entrar para acessar esta página.');
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        flash('error', 'Acesso permitido apenas para administradores.');
        redirect('pages/dashboard.php');
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $value = (string) $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $value;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    if (!$token || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf_token(?string $token): void
{
    if (verify_csrf_token($token)) {
        return;
    }

    flash('error', 'Token CSRF inválido. Tente novamente.');

    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($referer !== '' && $host !== '') {
        $refHost = (string) (parse_url($referer, PHP_URL_HOST) ?: '');
        if ($refHost === $host) {
            header('Location: ' . $referer);
            exit;
        }
    }

    if (!is_logged_in()) {
        redirect('login.php');
    }

    $fallback = (($_SESSION['role'] ?? '') === 'admin') ? 'admin/dashboard.php' : 'pages/dashboard.php';
    redirect($fallback);
}

function ensure_upload_dir(): string
{
    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    return $uploadDir;
}

function db_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
    $stmt->execute([':table' => $table]);
    return ((int) $stmt->fetchColumn()) > 0;
}

function db_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column');
    $stmt->execute([':table' => $table, ':column' => $column]);
    return ((int) $stmt->fetchColumn()) > 0;
}

function progress_table_name(PDO $pdo): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (db_table_exists($pdo, 'progress')) {
        $cached = 'progress';
    } elseif (db_table_exists($pdo, 'progres')) {
        $cached = 'progres';
    } else {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS progress (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                lesson_id INT UNSIGNED NOT NULL,
                completed TINYINT(1) NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_user_lesson (user_id, lesson_id),
                KEY idx_progress_user (user_id),
                KEY idx_progress_lesson (lesson_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $cached = 'progress';
    }

    if (!db_column_exists($pdo, $cached, 'completed')) {
        $pdo->exec("ALTER TABLE {$cached} ADD COLUMN completed TINYINT(1) NOT NULL DEFAULT 0");
    }

    return $cached;
}

function get_youtube_embed_url(string $rawUrl): string
{
    $url = trim($rawUrl);
    if ($url === '') {
        return '';
    }

    $patterns = [
        '#youtu\.be/([a-zA-Z0-9_-]{6,})#',
        '#youtube\.com/watch\?v=([a-zA-Z0-9_-]{6,})#',
        '#youtube\.com/embed/([a-zA-Z0-9_-]{6,})#',
        '#youtube\.com/shorts/([a-zA-Z0-9_-]{6,})#',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }
    }

    return $url;
}

function module_progress_percent(PDO $pdo, int $userId, int $moduleId): int
{
    $progressTable = progress_table_name($pdo);

    $stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM lessons WHERE module_id = :module_id');
    $stmtTotal->execute([':module_id' => $moduleId]);
    $total = (int) $stmtTotal->fetchColumn();

    if ($total === 0) {
        return 0;
    }

    $stmtDone = $pdo->prepare(
        "SELECT COUNT(*)
         FROM {$progressTable} p
         INNER JOIN lessons l ON l.id = p.lesson_id
         WHERE p.user_id = :user_id AND p.completed = 1 AND l.module_id = :module_id"
    );
    $stmtDone->execute([':user_id' => $userId, ':module_id' => $moduleId]);
    $done = (int) $stmtDone->fetchColumn();

    return (int) round(($done / $total) * 100);
}

function ensure_schema_updates(PDO $pdo): void
{
    static $alreadyChecked = false;
    if ($alreadyChecked) {
        return;
    }

    $alreadyChecked = true;

    try {
        if (!db_column_exists($pdo, 'users', 'foto_perfil')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN foto_perfil VARCHAR(255) NULL AFTER email');
        }

        progress_table_name($pdo);

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS notifications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tipo VARCHAR(40) NOT NULL,
                titulo VARCHAR(180) NOT NULL,
                mensagem TEXT NOT NULL,
                url VARCHAR(255) NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_notifications_data (criado_em)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS notification_reads (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                notification_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                lido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_notification_user (notification_id, user_id),
                KEY idx_notification_reads_user (user_id),
                CONSTRAINT fk_notification_reads_notification
                    FOREIGN KEY (notification_id) REFERENCES notifications(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_notification_reads_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS forum_topics (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                titulo VARCHAR(200) NOT NULL,
                mensagem TEXT NOT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_forum_topics_atualizado (atualizado_em),
                CONSTRAINT fk_forum_topics_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS forum_replies (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                topic_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                mensagem TEXT NOT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_forum_replies_topic (topic_id),
                CONSTRAINT fk_forum_replies_topic
                    FOREIGN KEY (topic_id) REFERENCES forum_topics(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_forum_replies_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS quizzes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                module_id INT UNSIGNED NOT NULL,
                titulo VARCHAR(180) NOT NULL,
                descricao TEXT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_quizzes_module (module_id),
                CONSTRAINT fk_quizzes_module
                    FOREIGN KEY (module_id) REFERENCES modules(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS quiz_questions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                quiz_id INT UNSIGNED NOT NULL,
                pergunta TEXT NOT NULL,
                ordem INT NOT NULL DEFAULT 0,
                KEY idx_quiz_questions_quiz (quiz_id),
                CONSTRAINT fk_quiz_questions_quiz
                    FOREIGN KEY (quiz_id) REFERENCES quizzes(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS quiz_options (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                question_id INT UNSIGNED NOT NULL,
                texto VARCHAR(500) NOT NULL,
                correta TINYINT(1) NOT NULL DEFAULT 0,
                KEY idx_quiz_options_question (question_id),
                CONSTRAINT fk_quiz_options_question
                    FOREIGN KEY (question_id) REFERENCES quiz_questions(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS quiz_attempts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                quiz_id INT UNSIGNED NOT NULL,
                acertos INT UNSIGNED NOT NULL DEFAULT 0,
                total INT UNSIGNED NOT NULL DEFAULT 0,
                nota TINYINT UNSIGNED NOT NULL DEFAULT 0,
                respostas TEXT NULL,
                feito_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_quiz_attempts_user (user_id),
                KEY idx_quiz_attempts_quiz (quiz_id),
                CONSTRAINT fk_quiz_attempts_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_quiz_attempts_quiz
                    FOREIGN KEY (quiz_id) REFERENCES quizzes(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (Throwable $exception) {
        // Evita interromper o sistema em hospedagens com limitacoes de permissao.
    }
}

function create_notification(PDO $pdo, string $tipo, string $titulo, string $mensagem, ?string $url = null): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (tipo, titulo, mensagem, url, criado_em)
             VALUES (:tipo, :titulo, :mensagem, :url, NOW())'
        );
        $stmt->execute([
            ':tipo' => $tipo,
            ':titulo' => $titulo,
            ':mensagem' => $mensagem,
            ':url' => $url,
        ]);
    } catch (Throwable $exception) {
        // Notificacoes nao podem quebrar fluxo principal.
    }
}

function notification_unread_count(PDO $pdo, int $userId): int
{
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM notifications n
             LEFT JOIN notification_reads nr
               ON nr.notification_id = n.id AND nr.user_id = :user_id_join
             WHERE nr.id IS NULL'
        );
        $stmt->execute([':user_id_join' => $userId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $exception) {
        return 0;
    }
}

function mark_notification_read(PDO $pdo, int $notificationId, int $userId): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO notification_reads (notification_id, user_id, lido_em)
         VALUES (:notification_id, :user_id, NOW())
         ON DUPLICATE KEY UPDATE lido_em = NOW()'
    );
    $stmt->execute([':notification_id' => $notificationId, ':user_id' => $userId]);
}

function mark_all_notifications_read(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO notification_reads (notification_id, user_id, lido_em)
         SELECT n.id, :user_id_insert, NOW()
         FROM notifications n
         LEFT JOIN notification_reads nr
           ON nr.notification_id = n.id AND nr.user_id = :user_id_join
         WHERE nr.id IS NULL'
    );
    $stmt->execute([':user_id_insert' => $userId, ':user_id_join' => $userId]);
}

function ensure_default_admin(PDO $pdo): void
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }

    $bootstrapped = true;

    try {
        $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $hasAdmin = (bool) $stmt->fetch();

        if ($hasAdmin) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO users (nome, email, senha, role, criado_em)
             VALUES (:nome, :email, :senha, :role, NOW())'
        );

        $insert->execute([
            ':nome' => 'Administrador FL360',
            ':email' => 'admin@fl360.local',
            ':senha' => password_hash('33222', PASSWORD_DEFAULT),
            ':role' => 'admin',
        ]);
    } catch (Throwable $exception) {
        // Ignora erros durante bootstrap.
    }
}

ensure_schema_updates($pdo);
ensure_default_admin($pdo);