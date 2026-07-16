<?php
declare(strict_types=1);

// ─── Proteção: desabilitar após instalar ──────────────────────────────────
$lockFile = __DIR__ . '/storage/installed.lock';
if (file_exists($lockFile)) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>FL360</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#0f7282;color:#fff;}
    .box{background:rgba(0,0,0,.35);padding:2rem 2.5rem;border-radius:16px;text-align:center;}
    h2{margin:0 0 .5rem;}p{opacity:.8;}</style></head>
    <body><div class="box"><h2>✓ Sistema já instalado</h2>
    <p>Este instalador foi desabilitado por segurança.</p>
    <p><a href="login.php" style="color:#7ee8f4;">Ir para o login →</a></p>
    </div></body></html>');
}

// ─── Helpers ──────────────────────────────────────────────────────────────
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$step    = 1;
$errors  = [];
$success = [];
$pdo     = null;

// ─── POST: executar instalação ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost     = trim((string) ($_POST['db_host']     ?? 'localhost'));
    $dbPort     = trim((string) ($_POST['db_port']     ?? '3306'));
    $dbName     = trim((string) ($_POST['db_name']     ?? ''));
    $dbUser     = trim((string) ($_POST['db_user']     ?? ''));
    $dbPass     = (string) ($_POST['db_pass']     ?? '');
    $adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
    $adminName  = trim((string) ($_POST['admin_name']  ?? 'Administrador FL360'));
    $adminPass  = (string) ($_POST['admin_pass']  ?? '');

    // ── Validação básica
    if ($dbName === '')    { $errors[] = 'Nome do banco não pode ser vazio.'; }
    if ($dbUser === '')    { $errors[] = 'Usuário do banco não pode ser vazio.'; }
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) { $errors[] = 'E-mail do admin inválido.'; }
    if (strlen($adminPass) < 6) { $errors[] = 'Senha do admin deve ter pelo menos 6 caracteres.'; }

    if (empty($errors)) {
        // ── Conectar
        $port = (int) ($dbPort ?: 3306);
        $dsn  = "mysql:host={$dbHost};port={$port};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $success[] = "Conexão com o MySQL estabelecida.";
        } catch (PDOException $e) {
            $errors[] = 'Falha na conexão: ' . $e->getMessage();
        }
    }

    if (empty($errors) && $pdo) {
        // ── Criar banco se não existir
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");
            $success[] = "Banco de dados <strong>{$dbName}</strong> selecionado.";
        } catch (PDOException $e) {
            $errors[] = 'Não foi possível criar/selecionar o banco: ' . $e->getMessage();
        }
    }

    if (empty($errors) && $pdo) {
        // ── Criar tabelas
        $tables = [
            'users' => "CREATE TABLE IF NOT EXISTS users (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nome        VARCHAR(150) NOT NULL,
                email       VARCHAR(180) NOT NULL,
                foto_perfil VARCHAR(255) NULL,
                senha       VARCHAR(255) NOT NULL,
                role        ENUM('admin','professor','aluno') NOT NULL DEFAULT 'aluno',
                criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_users_email (email),
                KEY idx_users_role (role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'modules' => "CREATE TABLE IF NOT EXISTS modules (
                id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                titulo    VARCHAR(180) NOT NULL,
                descricao TEXT NULL,
                ordem     INT NOT NULL DEFAULT 0,
                professor_id INT UNSIGNED NULL,
                KEY idx_modules_ordem (ordem),
                KEY idx_modules_professor (professor_id),
                CONSTRAINT fk_modules_professor FOREIGN KEY (professor_id) REFERENCES users(id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'lessons' => "CREATE TABLE IF NOT EXISTS lessons (
                id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                module_id INT UNSIGNED NOT NULL,
                titulo    VARCHAR(180) NOT NULL,
                descricao TEXT NULL,
                video_url VARCHAR(255) NOT NULL DEFAULT '',
                ordem     INT NOT NULL DEFAULT 0,
                KEY idx_lessons_module (module_id),
                KEY idx_lessons_module_ordem (module_id, ordem, id),
                CONSTRAINT fk_lessons_module
                    FOREIGN KEY (module_id) REFERENCES modules(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'materials' => "CREATE TABLE IF NOT EXISTS materials (
                id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                module_id INT UNSIGNED NULL,
                lesson_id INT UNSIGNED NULL,
                titulo    VARCHAR(180) NOT NULL,
                arquivo   VARCHAR(255) NOT NULL,
                KEY idx_materials_module (module_id),
                KEY idx_materials_lesson (lesson_id),
                CONSTRAINT fk_materials_module
                    FOREIGN KEY (module_id) REFERENCES modules(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_materials_lesson
                    FOREIGN KEY (lesson_id) REFERENCES lessons(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'progress' => "CREATE TABLE IF NOT EXISTS progress (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id    INT UNSIGNED NOT NULL,
                lesson_id  INT UNSIGNED NOT NULL,
                completed  TINYINT(1) NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_user_lesson (user_id, lesson_id),
                KEY idx_progress_user (user_id),
                KEY idx_progress_lesson (lesson_id),
                CONSTRAINT fk_progress_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_progress_lesson
                    FOREIGN KEY (lesson_id) REFERENCES lessons(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'announcements' => "CREATE TABLE IF NOT EXISTS announcements (
                id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                titulo   VARCHAR(200) NOT NULL,
                mensagem TEXT NOT NULL,
                data     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_announcements_data (data)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'notifications' => "CREATE TABLE IF NOT EXISTS notifications (
                id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tipo      VARCHAR(40)  NOT NULL,
                titulo    VARCHAR(180) NOT NULL,
                mensagem  TEXT NOT NULL,
                url       VARCHAR(255) NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_notifications_data (criado_em)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'notification_reads' => "CREATE TABLE IF NOT EXISTS notification_reads (
                id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                notification_id INT UNSIGNED NOT NULL,
                user_id         INT UNSIGNED NOT NULL,
                lido_em         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_notification_user (notification_id, user_id),
                KEY idx_notification_reads_user (user_id),
                CONSTRAINT fk_nr_notification
                    FOREIGN KEY (notification_id) REFERENCES notifications(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_nr_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'forum_topics' => "CREATE TABLE IF NOT EXISTS forum_topics (
                id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id       INT UNSIGNED NOT NULL,
                titulo        VARCHAR(200) NOT NULL,
                mensagem      TEXT NOT NULL,
                criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_forum_topics_atualizado (atualizado_em),
                CONSTRAINT fk_forum_topics_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'forum_replies' => "CREATE TABLE IF NOT EXISTS forum_replies (
                id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                topic_id  INT UNSIGNED NOT NULL,
                user_id   INT UNSIGNED NOT NULL,
                mensagem  TEXT NOT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_forum_replies_topic (topic_id),
                CONSTRAINT fk_forum_replies_topic
                    FOREIGN KEY (topic_id) REFERENCES forum_topics(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_forum_replies_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'quizzes' => "CREATE TABLE IF NOT EXISTS quizzes (
                id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                module_id INT UNSIGNED NOT NULL,
                titulo    VARCHAR(180) NOT NULL,
                descricao TEXT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_quizzes_module (module_id),
                CONSTRAINT fk_quizzes_module
                    FOREIGN KEY (module_id) REFERENCES modules(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'quiz_questions' => "CREATE TABLE IF NOT EXISTS quiz_questions (
                id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                quiz_id INT UNSIGNED NOT NULL,
                pergunta TEXT NOT NULL,
                ordem   INT NOT NULL DEFAULT 0,
                KEY idx_quiz_questions_quiz (quiz_id),
                CONSTRAINT fk_quiz_questions_quiz
                    FOREIGN KEY (quiz_id) REFERENCES quizzes(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'quiz_options' => "CREATE TABLE IF NOT EXISTS quiz_options (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                question_id INT UNSIGNED NOT NULL,
                texto       VARCHAR(500) NOT NULL,
                correta     TINYINT(1) NOT NULL DEFAULT 0,
                KEY idx_quiz_options_question (question_id),
                CONSTRAINT fk_quiz_options_question
                    FOREIGN KEY (question_id) REFERENCES quiz_questions(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'quiz_attempts' => "CREATE TABLE IF NOT EXISTS quiz_attempts (
                id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id  INT UNSIGNED NOT NULL,
                quiz_id  INT UNSIGNED NOT NULL,
                acertos  INT UNSIGNED NOT NULL DEFAULT 0,
                total    INT UNSIGNED NOT NULL DEFAULT 0,
                nota     TINYINT UNSIGNED NOT NULL DEFAULT 0,
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($tables as $name => $sql) {
            try {
                $pdo->exec($sql);
                $success[] = "Tabela <strong>{$name}</strong> — OK";
            } catch (PDOException $e) {
                $errors[] = "Erro na tabela {$name}: " . $e->getMessage();
            }
        }
    }

    if (empty($errors) && $pdo) {
        // ── Criar admin
        try {
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $chk->execute([':email' => $adminEmail]);
            if ($chk->fetchColumn()) {
                $success[] = "Admin <strong>{$adminEmail}</strong> já existia — senha atualizada.";
                $upd = $pdo->prepare("UPDATE users SET nome=:nome, senha=:senha, role='admin' WHERE email=:email");
                $upd->execute([
                    ':nome'  => $adminName,
                    ':senha' => password_hash($adminPass, PASSWORD_DEFAULT),
                    ':email' => $adminEmail,
                ]);
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO users (nome, email, senha, role, criado_em)
                     VALUES (:nome, :email, :senha, 'admin', NOW())"
                );
                $ins->execute([
                    ':nome'  => $adminName,
                    ':email' => $adminEmail,
                    ':senha' => password_hash($adminPass, PASSWORD_DEFAULT),
                ]);
                $success[] = "Admin <strong>{$adminEmail}</strong> criado com sucesso.";
            }
        } catch (PDOException $e) {
            $errors[] = 'Erro ao criar admin: ' . $e->getMessage();
        }
    }

    if (empty($errors)) {
        // ── Criar lock file e atualizar db.php
        $storageDir = __DIR__ . '/storage';
        if (!is_dir($storageDir)) { mkdir($storageDir, 0755, true); }
        file_put_contents($lockFile, date('Y-m-d H:i:s'));

        // Atualizar includes/db.php com as credenciais informadas
        $dbPhpPath = __DIR__ . '/includes/db.php';
        if (file_exists($dbPhpPath)) {
            $content = file_get_contents($dbPhpPath);
            $content = preg_replace("/\\\$dbHost\s*=.*?;/", "\$dbHost = getenv('DB_HOST') ?: " . var_export($dbHost, true) . ";", $content);
            $content = preg_replace("/\\\$dbName\s*=.*?;/", "\$dbName = getenv('DB_NAME') ?: " . var_export($dbName, true) . ";", $content);
            $content = preg_replace("/\\\$dbUser\s*=.*?;/", "\$dbUser = getenv('DB_USER') ?: " . var_export($dbUser, true) . ";", $content);
            $content = preg_replace("/\\\$dbPass\s*=.*?;/", "\$dbPass = getenv('DB_PASS') ?: " . var_export($dbPass, true) . ";", $content);
            file_put_contents($dbPhpPath, $content);
        }

        $step = 3; // sucesso
    } else {
        $step = 2; // mostrar erros
        // repopular campos
        $fields = compact('dbHost','dbPort','dbName','dbUser','adminEmail','adminName');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>FL360 — Instalação</title>
<style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
        margin: 0; padding: 0;
        font-family: 'Segoe UI', system-ui, sans-serif;
        background: linear-gradient(135deg, #1593A8 0%, #0f7282 55%, #0a5464 100%);
        min-height: 100vh;
        display: flex; align-items: center; justify-content: center;
        color: #111;
    }
    .wrap {
        width: min(100%, 520px);
        padding: 1rem;
    }
    .logo-panel {
        background: rgba(8,48,60,0.55);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: 18px;
        padding: 1.4rem 2rem;
        text-align: center;
        margin-bottom: 1.2rem;
        color: #fff;
    }
    .logo-panel img { max-width: 240px; height: auto; }
    .logo-panel h1 { margin: 0.5rem 0 0.2rem; font-size: 1.4rem; }
    .logo-panel p  { margin: 0; opacity: .8; font-size: .9rem; }
    .card {
        background: #fff;
        border-radius: 18px;
        padding: 2rem;
        box-shadow: 0 24px 64px rgba(0,0,0,.22);
    }
    .card h2 { margin: 0 0 .35rem; font-size: 1.5rem; color: #111; }
    .card > p { margin: 0 0 1.4rem; color: #5f7280; font-size: .93rem; }
    .section-title {
        font-size: .75rem; font-weight: 700; letter-spacing: .08em;
        text-transform: uppercase; color: #1593A8;
        margin: 1.4rem 0 .6rem;
    }
    label { display: block; font-size: .85rem; font-weight: 600; color: #2c3e50; margin-bottom: .25rem; }
    input[type=text], input[type=email], input[type=password], input[type=number] {
        width: 100%; padding: .7rem .9rem; font-size: .95rem;
        border: 1.5px solid #d7e0e5; border-radius: 10px;
        background: #f8fbfc; outline: none; transition: border .2s;
        margin-bottom: .85rem;
    }
    input:focus { border-color: #1593A8; background: #fff; }
    .row { display: grid; grid-template-columns: 1fr 100px; gap: .6rem; }
    .btn {
        width: 100%; padding: .85rem; font-size: 1rem; font-weight: 700;
        background: #1593A8; color: #fff; border: none; border-radius: 10px;
        cursor: pointer; margin-top: .5rem; transition: background .2s;
    }
    .btn:hover { background: #0f7282; }
    .alert { border-radius: 10px; padding: .7rem 1rem; margin-bottom: .6rem; font-size: .88rem; }
    .alert-error   { background: #fdf0f1; border: 1px solid #f5c6cb; color: #922b35; }
    .alert-success { background: #eafaf4; border: 1px solid #a8e6d0; color: #1a6644; }
    .log { max-height: 220px; overflow-y: auto; display: grid; gap: .35rem; margin-bottom: 1rem; }
    .done {
        text-align: center; padding: 1.5rem 0 .5rem;
    }
    .done .icon { font-size: 3rem; margin-bottom: .5rem; }
    .done h3 { font-size: 1.35rem; color: #111; margin: 0 0 .5rem; }
    .done p  { color: #5f7280; font-size: .93rem; margin: 0 0 1.2rem; }
    .btn-ghost {
        display: inline-block; padding: .75rem 1.5rem; border-radius: 10px;
        background: transparent; border: 2px solid #1593A8; color: #1593A8;
        font-weight: 700; font-size: .95rem; text-decoration: none; transition: all .2s;
    }
    .btn-ghost:hover { background: #1593A8; color: #fff; }
    hr { border: 0; border-top: 1px solid #e8eff3; margin: 1rem 0; }
</style>
</head>
<body>
<div class="wrap">

    <div class="logo-panel">
        <img src="assets/img/logo fl360.png" alt="FL360">
        <h1>Portal do Aluno</h1>
        <p>Instalador do sistema</p>
    </div>

    <div class="card">

    <?php if ($step === 3): ?>
    <!-- ──────────────── SUCESSO ──────────────── -->
    <div class="done">
        <div class="icon">🎉</div>
        <h3>Instalação concluída!</h3>
        <p>Todas as tabelas foram criadas e o administrador está pronto.<br>
           O instalador foi <strong>bloqueado automaticamente</strong>.</p>
        <a href="login.php" class="btn-ghost">Ir para o login →</a>
    </div>
    <hr>
    <div class="log">
        <?php foreach ($success as $msg): ?>
            <div class="alert alert-success"><?= $msg ?></div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- ──────────────── FORMULÁRIO ──────────────── -->
    <h2>Configurar instalação</h2>
    <p>Preencha as credenciais do banco e defina o administrador. O sistema criará todas as tabelas automaticamente.</p>

    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-error">⚠ <?= h($err) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <form method="post" autocomplete="off">

        <div class="section-title">Banco de dados MySQL</div>

        <label for="db_host">Host</label>
        <input id="db_host" type="text" name="db_host"
               value="<?= h($fields['dbHost'] ?? 'localhost') ?>" placeholder="localhost">

        <div class="row">
            <div>
                <label for="db_name">Nome do banco</label>
                <input id="db_name" type="text" name="db_name"
                       value="<?= h($fields['dbName'] ?? '') ?>" placeholder="xdigcomb_plataformafl360" required>
            </div>
            <div>
                <label for="db_port">Porta</label>
                <input id="db_port" type="number" name="db_port"
                       value="<?= h($fields['dbPort'] ?? '3306') ?>" placeholder="3306">
            </div>
        </div>

        <label for="db_user">Usuário</label>
        <input id="db_user" type="text" name="db_user"
               value="<?= h($fields['dbUser'] ?? '') ?>" placeholder="xdigcomb_cassianofl360" required>

        <label for="db_pass">Senha do banco</label>
        <input id="db_pass" type="password" name="db_pass" placeholder="Senha do usuário MySQL" autocomplete="new-password">

        <div class="section-title">Conta de administrador</div>

        <label for="admin_name">Nome do admin</label>
        <input id="admin_name" type="text" name="admin_name"
               value="<?= h($fields['adminName'] ?? 'Administrador FL360') ?>" required>

        <label for="admin_email">E-mail do admin</label>
        <input id="admin_email" type="email" name="admin_email"
               value="<?= h($fields['adminEmail'] ?? '') ?>" placeholder="admin@exemplo.com" required>

        <label for="admin_pass">Senha do admin</label>
        <input id="admin_pass" type="password" name="admin_pass"
               placeholder="Mínimo 6 caracteres" autocomplete="new-password" required>

        <button type="submit" class="btn">Instalar agora</button>
    </form>

    <?php endif; ?>

    </div><!-- .card -->
</div><!-- .wrap -->
</body>
</html>
