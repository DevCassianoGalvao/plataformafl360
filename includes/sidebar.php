<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
if (!is_logged_in()) { return; }

$role = current_role();
$activePage = $active_page ?? '';
$unreadNotifications = $role === 'aluno' ? notification_unread_count($pdo, (int) ($_SESSION['user_id'] ?? 0)) : 0;

function nav_icon(string $name): string
{
    $paths = [
        'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10.5V20h13v-9.5"/><path d="M9 20v-6h6v6"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'modules' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'play' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3z"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h6"/>',
        'quiz' => '<circle cx="12" cy="12" r="10"/><path d="M9.5 9a2.5 2.5 0 1 1 3.6 2.25c-.7.35-1.1.8-1.1 1.75M12 17h.01"/>',
        'forum' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>',
        'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21h-4v-.09A1.7 1.7 0 0 0 8.6 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3v-4h.09A1.7 1.7 0 0 0 4.6 8.6a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3h4v.09A1.7 1.7 0 0 0 15.4 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.35.28.57.67.6 1.1V10h1v4h-.09A1.7 1.7 0 0 0 19.4 15z"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$name] ?? $paths['home']) . '</svg>';
}

$menus = [
    'admin' => [
        'dashboard' => ['Dashboard', 'admin/dashboard.php', 'home', ''],
        'usuarios' => ['Usuários', 'admin/usuarios.php', 'users', ''],
        'avisos' => ['Avisos', 'admin/avisos.php', 'bell', ''],
        'forum_admin' => ['Moderação do fórum', 'admin/forum.php', 'forum', ''],
        'perfil' => ['Perfil', 'admin/perfil.php', 'profile', ''],
        'logout' => ['Sair', 'logout.php', 'logout', ''],
    ],
    'professor' => [
        'dashboard' => ['Visão geral', 'professor/dashboard.php', 'home', ''],
        'modulos' => ['Módulos', 'professor/modulos.php', 'modules', ''],
        'aulas' => ['Aulas', 'professor/aulas.php', 'play', ''],
        'materiais' => ['Materiais', 'professor/materiais.php', 'file', ''],
        'quiz' => ['Quizzes', 'professor/quiz.php', 'quiz', ''],
        'perfil' => ['Perfil', 'professor/perfil.php', 'profile', ''],
        'logout' => ['Sair', 'logout.php', 'logout', ''],
    ],
    'aluno' => [
        'dashboard' => ['Início', 'pages/dashboard.php', 'home', ''],
        'notificacoes' => ['Notificações', 'pages/notificacoes.php', 'bell', ''],
        'modulos' => ['Módulos', 'pages/modulos.php', 'modules', ''],
        'materiais' => ['Materiais', 'pages/materiais.php', 'file', ''],
        'forum' => ['Fórum', 'pages/forum.php', 'forum', ''],
        'perfil' => ['Perfil', 'pages/perfil.php', 'profile', ''],
        'logout' => ['Sair', 'logout.php', 'logout', ''],
    ],
];
$menu = $menus[$role] ?? $menus['aluno'];
$pedagogicalMenu = [
    'modulos' => ['Módulos', 'admin/modulos.php', 'modules'],
    'aulas' => ['Aulas', 'admin/aulas.php', 'play'],
    'materiais' => ['Materiais', 'admin/materiais.php', 'file'],
    'quiz' => ['Quizzes', 'admin/quiz.php', 'quiz'],
];
?>
<aside class="sidebar" id="mainSidebar">
    <div class="sidebar-brand"><div class="sidebar-logo-wrap"><img src="<?= e(url('assets/img/logo fl360.png')) ?>" alt="FL360" class="sidebar-logo"></div><div><strong>FL360</strong><span><?= $role === 'professor' ? 'Área do professor' : ($role === 'admin' ? 'Administração' : 'Portal do aluno') ?></span></div></div>
    <nav class="sidebar-nav" aria-label="Navegação principal">
        <?php foreach ($menu as $key => [$label, $itemUrl, $icon, $class]): ?>
            <a class="sidebar-link <?= $activePage === $key ? 'active' : '' ?> <?= e($class) ?>" href="<?= e(url($itemUrl)) ?>"><?= nav_icon($icon) ?><span><?= e($label) ?></span><?php if ($key === 'notificacoes' && $unreadNotifications > 0): ?><span class="sidebar-counter"><?= $unreadNotifications ?></span><?php endif; ?></a>
            <?php if ($role === 'admin' && $key === 'avisos'): ?>
                <details class="sidebar-nav-group">
                    <summary><?= nav_icon('settings') ?><span>Gestão pedagógica</span><span class="sidebar-group-chevron">›</span></summary>
                    <div class="sidebar-nav-group-items">
                        <?php foreach ($pedagogicalMenu as $pedKey => [$pedLabel, $pedUrl, $pedIcon]): ?>
                            <a class="sidebar-link <?= $activePage === $pedKey ? 'active' : '' ?> secondary" href="<?= e(url($pedUrl)) ?>"><?= nav_icon($pedIcon) ?><span><?= e($pedLabel) ?></span></a>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-partners" aria-label="Realização e parceiros">
        <img src="<?= e(url('assets/img/LOGO MAICON - ATUALIZADA.png')) ?>" alt="Maicon Gonçalves">
        <img src="<?= e(url('assets/img/escola do legislativo.png')) ?>" alt="Escola do Legislativo">
    </div>
    <div class="sidebar-footer"><span>Programa Friburgo Líder 360</span><small>Formação cidadã</small></div>
</aside>
