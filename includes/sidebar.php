<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (!is_logged_in()) {
    return;
}

$isAdmin = (($_SESSION['role'] ?? '') === 'admin');
$activePage = $active_page ?? '';
$unreadNotifications = notification_unread_count($pdo, (int) ($_SESSION['user_id'] ?? 0));

$menu = $isAdmin
    ? [
        'dashboard' => ['label' => 'Dashboard', 'url' => 'admin/dashboard.php'],
        'usuarios' => ['label' => 'Usuários', 'url' => 'admin/usuarios.php'],
        'modulos' => ['label' => 'Módulos', 'url' => 'admin/modulos.php'],
        'aulas' => ['label' => 'Aulas', 'url' => 'admin/aulas.php'],
        'quiz' => ['label' => 'Quizzes', 'url' => 'admin/quiz.php'],
        'materiais' => ['label' => 'Materiais', 'url' => 'admin/materiais.php'],
        'avisos' => ['label' => 'Avisos', 'url' => 'admin/avisos.php'],
        'perfil' => ['label' => 'Perfil', 'url' => 'admin/perfil.php'],
        'logout' => ['label' => 'Sair', 'url' => 'logout.php'],
    ]
    : [
        'dashboard' => ['label' => 'Dashboard', 'url' => 'pages/dashboard.php'],
        'notificacoes' => ['label' => 'Novidades', 'url' => 'pages/notificacoes.php'],
        'modulos' => ['label' => 'Módulos', 'url' => 'pages/modulos.php'],
        'materiais' => ['label' => 'Materiais', 'url' => 'pages/materiais.php'],
        'forum' => ['label' => 'Fórum', 'url' => 'pages/forum.php'],
        'perfil' => ['label' => 'Perfil', 'url' => 'pages/perfil.php'],
        'logout' => ['label' => 'Sair', 'url' => 'logout.php'],
    ];
?>
<aside class="sidebar" id="mainSidebar">
    <div class="sidebar-brand">
        <div class="sidebar-logo-wrap">
            <img src="<?= e(url('assets/img/logo fl360.png')) ?>" alt="Logo FL360" class="sidebar-logo">
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menu as $key => $item): ?>
            <a class="sidebar-link <?= $activePage === $key ? 'active' : '' ?>" href="<?= e(url($item['url'])) ?>">
                <?= e($item['label']) ?>
                <?php if (!$isAdmin && $key === 'notificacoes' && $unreadNotifications > 0): ?>
                    <span class="sidebar-counter"><?= e((string) $unreadNotifications) ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-apoio sidebar-realizacao">
        <small>Realização:</small>
        <img src="<?= e(url('assets/img/LOGO MAICON - ATUALIZADA.png')) ?>" alt="Maicon Gonçalves">
    </div>

    <div class="sidebar-apoio">
        <small>Apoio:</small>
        <img src="<?= e(url('assets/img/escola do legislativo.png')) ?>" alt="Escola do Legislativo">
    </div>

</aside>
