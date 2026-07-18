<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
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

    $stmt = $pdo->prepare(
        'SELECT id, nome, email, role, status, email_verificado_em, criado_em, foto_perfil
         FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || ($user['status'] ?? 'ativo') !== 'ativo' || empty($user['email_verificado_em'])) {
        logout_user();
        return null;
    }

    $cachedUser = $user;
    return $cachedUser;
}

function require_login(): void
{
    global $pdo;

    if (!is_logged_in() || !($pdo instanceof PDO) || current_user($pdo) === null) {
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

function current_role(): string
{
    return (string) ($_SESSION['role'] ?? '');
}

function role_home_path(?string $role = null): string
{
    return match ($role ?? current_role()) {
        'admin' => 'admin/dashboard.php',
        'professor' => 'professor/dashboard.php',
        default => 'pages/dashboard.php',
    };
}

function redirect_to_role_home(): void
{
    redirect(role_home_path());
}

function require_student(): void
{
    require_login();
    if (current_role() !== 'aluno') {
        redirect_to_role_home();
    }
}

function require_professor(): void
{
    require_login();
    if (current_role() !== 'professor') {
        redirect_to_role_home();
    }
}

function require_content_manager(): void
{
    require_login();
    if (!in_array(current_role(), ['admin', 'professor'], true)) {
        flash('error', 'Acesso permitido apenas para administradores e professores.');
        redirect_to_role_home();
    }
}

function content_manager_path(string $page): string
{
    $area = current_role() === 'professor' ? 'professor' : 'admin';
    return $area . '/' . ltrim($page, '/');
}

function can_manage_module(PDO $pdo, int $moduleId, ?array $user = null): bool
{
    if ($moduleId <= 0) {
        return false;
    }

    $user ??= current_user($pdo);
    if (!$user) {
        return false;
    }

    if (($user['role'] ?? '') === 'admin') {
        return true;
    }

    if (($user['role'] ?? '') !== 'professor') {
        return false;
    }

    if (db_table_exists($pdo, 'module_professors')) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM module_professors WHERE module_id = :id AND user_id = :professor_id');
        $stmt->execute([':id' => $moduleId, ':professor_id' => (int) $user['id']]);
        if (((int) $stmt->fetchColumn()) > 0) {
            return true;
        }
    }

    if (!db_column_exists($pdo, 'modules', 'professor_id')) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM modules WHERE id = :id AND professor_id = :professor_id');
    $stmt->execute([':id' => $moduleId, ':professor_id' => (int) $user['id']]);
    return ((int) $stmt->fetchColumn()) > 0;
}

function password_validation_error(string $password): ?string
{
    if (strlen($password) < 12) {
        return 'A senha deve ter pelo menos 12 caracteres. Prefira uma frase-senha fácil de lembrar.';
    }

    if (strlen($password) > 72) {
        return 'A senha deve ter no máximo 72 caracteres.';
    }

    $normalized = strtolower(trim($password));
    $blocked = ['123456789012', 'senha12345678', 'password1234', 'qwerty123456', 'admin12345678'];
    if (in_array($normalized, $blocked, true)) {
        return 'Essa senha é muito comum. Escolha uma frase-senha exclusiva.';
    }

    return null;
}

function email_has_valid_domain(string $email): bool
{
    // Confirmação por link prova o acesso ao endereço. DNS síncrono pode falhar
    // temporariamente no cPanel e não deve impedir um cadastro válido.
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function absolute_url(string $path): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'www.maicongoncalves.com.br');
    return ($https ? 'https://' : 'http://') . $host . url($path);
}

function send_verification_email(string $email, string $name, string $token): bool
{
    $verificationUrl = absolute_url('verificar-email.php?token=' . rawurlencode($token));
    $subject = 'Confirme seu e-mail - FL360';
    $message = "Olá, {$name}!\n\nConfirme seu e-mail para concluir seu cadastro no Portal FL360:\n{$verificationUrl}\n\nO link expira em 24 horas. Depois da confirmação, o acesso ainda será analisado pela administração.";
    $headers = [
        'From: Portal FL360 <no-reply@maicongoncalves.com.br>',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    return @mail($email, $subject, $message, implode("\r\n", $headers));
}

function login_is_rate_limited(PDO $pdo, string $email, string $ip): bool
{
    if (!db_table_exists($pdo, 'login_attempts')) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE sucesso = 0 AND criado_em >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
           AND (email_hash = :email_hash OR ip_hash = :ip_hash)'
    );
    $stmt->execute([
        ':email_hash' => hash('sha256', strtolower(trim($email))),
        ':ip_hash' => hash('sha256', $ip),
    ]);
    return ((int) $stmt->fetchColumn()) >= 8;
}

function record_login_attempt(PDO $pdo, string $email, string $ip, bool $success): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO login_attempts (email_hash, ip_hash, sucesso, criado_em)
             VALUES (:email_hash, :ip_hash, :sucesso, NOW())'
        );
        $stmt->execute([
            ':email_hash' => hash('sha256', strtolower(trim($email))),
            ':ip_hash' => hash('sha256', $ip),
            ':sucesso' => $success ? 1 : 0,
        ]);
    } catch (Throwable $exception) {
        // O controle de tentativas não deve indisponibilizar o login.
    }
}

function module_professors(PDO $pdo, int $moduleId): array
{
    $stmt = $pdo->prepare(
        'SELECT u.id, u.nome
         FROM module_professors mp
         INNER JOIN users u ON u.id = mp.user_id
         WHERE mp.module_id = :module_id
         ORDER BY u.nome'
    );
    $stmt->execute([':module_id' => $moduleId]);
    return $stmt->fetchAll();
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

    redirect(role_home_path());
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
        $roleTypeStmt = $pdo->prepare(
            'SELECT COLUMN_TYPE FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
        );
        $roleTypeStmt->execute([':table' => 'users', ':column' => 'role']);
        $roleType = (string) ($roleTypeStmt->fetchColumn() ?: '');
        if ($roleType !== '' && !str_contains($roleType, "'professor'")) {
            $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','professor','aluno') NOT NULL DEFAULT 'aluno'");
        }

        if (!db_column_exists($pdo, 'users', 'foto_perfil')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN foto_perfil VARCHAR(255) NULL AFTER email');
        }

        if (!db_column_exists($pdo, 'users', 'status')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('pendente','ativo','rejeitado') NOT NULL DEFAULT 'ativo' AFTER role");
        }
        if (!db_column_exists($pdo, 'users', 'email_verificado_em')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN email_verificado_em DATETIME NULL AFTER status');
            $pdo->exec("UPDATE users SET email_verificado_em = COALESCE(criado_em, NOW()) WHERE email_verificado_em IS NULL");
        }
        if (!db_column_exists($pdo, 'users', 'email_verification_hash')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN email_verification_hash CHAR(64) NULL AFTER email_verificado_em');
        }
        if (!db_column_exists($pdo, 'users', 'email_verification_expires')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN email_verification_expires DATETIME NULL AFTER email_verification_hash');
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS module_professors (
                module_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                assigned_by INT UNSIGNED NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (module_id, user_id),
                KEY idx_module_professors_user (user_id),
                CONSTRAINT fk_module_professors_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_module_professors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_module_professors_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        if (db_column_exists($pdo, 'modules', 'professor_id')) {
            $pdo->exec(
                'INSERT IGNORE INTO module_professors (module_id, user_id, assigned_by)
                 SELECT id, professor_id, professor_id FROM modules WHERE professor_id IS NOT NULL'
            );
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS login_attempts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email_hash CHAR(64) NOT NULL,
                ip_hash CHAR(64) NOT NULL,
                sucesso TINYINT(1) NOT NULL DEFAULT 0,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_login_attempts_email_data (email_hash, criado_em),
                KEY idx_login_attempts_ip_data (ip_hash, criado_em)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

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
        if (!db_column_exists($pdo, 'forum_topics', 'fixado')) {
            $pdo->exec('ALTER TABLE forum_topics ADD COLUMN fixado TINYINT(1) NOT NULL DEFAULT 0 AFTER mensagem');
        }
        if (!db_column_exists($pdo, 'forum_topics', 'bloqueado')) {
            $pdo->exec('ALTER TABLE forum_topics ADD COLUMN bloqueado TINYINT(1) NOT NULL DEFAULT 0 AFTER fixado');
        }

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
        if (!db_column_exists($pdo, 'quizzes', 'liberacao')) {
            $pdo->exec("ALTER TABLE quizzes ADD COLUMN liberacao ENUM('sempre','apos_aulas') NOT NULL DEFAULT 'apos_aulas' AFTER descricao");
        }

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

ensure_schema_updates($pdo);
