<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

$pageTitle = isset($page_title) ? (string) $page_title : APP_NAME;
$user = is_logged_in() ? current_user($pdo) : null;
$unreadNotifications = 0;
$avatarUrl = null;
$avatarInitial = 'U';
$latestNotifications = [];

if ($user) {
    $userId = (int) $user['id'];
    $unreadNotifications = notification_unread_count($pdo, $userId);

    if (!empty($user['foto_perfil'])) {
        $avatarUrl = url('uploads/' . $user['foto_perfil']);
    }

    $firstLetter = substr(trim((string) $user['nome']), 0, 1);
    if ($firstLetter !== '') {
        $avatarInitial = strtoupper($firstLetter);
    }

    if (($user['role'] ?? '') === 'aluno') {
        $stmtNotifications = $pdo->prepare(
            'SELECT n.id, n.titulo, n.url, n.criado_em,
                    CASE WHEN nr.id IS NULL THEN 0 ELSE 1 END AS lida
             FROM notifications n
             LEFT JOIN notification_reads nr
               ON nr.notification_id = n.id AND nr.user_id = :user_id
             ORDER BY n.criado_em DESC
             LIMIT 6'
        );
        $stmtNotifications->execute([':user_id' => $userId]);
        $latestNotifications = $stmtNotifications->fetchAll();
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <meta name="description" content="Portal do Aluno FL360 - Formação cidadã e compreensão da gestão pública.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@500;600;700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= e(url('assets/img/logo fl360.png')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=10">
    <script defer src="<?= e(url('assets/js/app.js')) ?>"></script>
</head>
<body>
<?php if ($success = flash('success')): ?>
    <div class="flash flash-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error = flash('error')): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($user): ?>
    <header class="topbar">
        <div class="topbar-left">
            <button class="theme-toggle mobile-only" id="sidebarToggle" type="button" aria-label="Abrir menu">
                Menu
            </button>
            <span class="topbar-label"><?= match ($user['role'] ?? '') { 'admin' => 'Administração', 'professor' => 'Área do Professor', default => 'Portal do Aluno' } ?></span>
        </div>
        <div class="topbar-right">
            <?php if (($user['role'] ?? '') === 'aluno'): ?>
                <div class="notif-dropdown" id="notifDropdown">
                    <button class="notif-bell" id="notifToggle" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Abrir notificações">
                        <span aria-hidden="true">&#128276;</span>
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="notif-count"><?= e((string) $unreadNotifications) ?></span>
                        <?php endif; ?>
                    </button>

                    <div class="notif-menu" id="notifMenu">
                        <div class="notif-menu-head">
                            <strong>Notificações</strong>
                            <a href="<?= e(url('pages/notificacoes.php')) ?>">Ver todas</a>
                        </div>

                        <?php if (!$latestNotifications): ?>
                            <p class="notif-empty">Nenhuma novidade por enquanto.</p>
                        <?php else: ?>
                            <div class="notif-list">
                                <?php foreach ($latestNotifications as $item): ?>
                                    <article class="notif-item <?= ((int) $item['lida'] === 0) ? 'unread' : '' ?>">
                                        <a href="<?= e(url(!empty($item['url']) ? (string) $item['url'] : 'pages/notificacoes.php')) ?>">
                                            <?= e($item['titulo']) ?>
                                        </a>
                                        <small><?= e(date('d/m H:i', strtotime((string) $item['criado_em']))) ?></small>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <span class="topbar-user">Olá, <?= e($user['nome']) ?></span>

            <a class="user-avatar" title="Abrir perfil" href="<?= e(url(match ($user['role'] ?? '') { 'admin' => 'admin/perfil.php', 'professor' => 'professor/perfil.php', default => 'pages/perfil.php' })) ?>">
                <?php if ($avatarUrl): ?>
                    <img src="<?= e($avatarUrl) ?>" alt="Foto de perfil">
                <?php else: ?>
                    <span><?= e($avatarInitial) ?></span>
                <?php endif; ?>
            </a>

            <button class="theme-toggle" id="themeToggle" type="button" aria-label="Alternar tema">
                <span aria-hidden="true">◐</span><span class="theme-label">Tema</span>
            </button>
            <a class="btn btn-ghost" href="<?= e(url('logout.php')) ?>">Sair</a>
        </div>
    </header>
<?php endif; ?>
