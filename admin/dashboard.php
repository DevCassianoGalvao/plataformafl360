<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$studentCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'aluno'")->fetchColumn();
$moduleCount = (int) $pdo->query('SELECT COUNT(*) FROM modules')->fetchColumn();
$lessonCount = (int) $pdo->query('SELECT COUNT(*) FROM lessons')->fetchColumn();
$materialCount = (int) $pdo->query('SELECT COUNT(*) FROM materials')->fetchColumn();

$noticeStmt = $pdo->query('SELECT id, titulo, mensagem, data FROM announcements ORDER BY data DESC LIMIT 5');
$announcements = $noticeStmt->fetchAll();

$active_page = 'dashboard';
$page_title = 'Dashboard Admin';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content-area">
        <section class="hero-card">
            <h1>Painel Administrativo FL360</h1>
            <p>Gerencie usuários, módulos, aulas, materiais e comunicados do programa.</p>
        </section>

        <section class="stats-grid">
            <article class="stat-card"><h3>Usuários</h3><strong><?= e((string) $userCount) ?></strong></article>
            <article class="stat-card"><h3>Alunos</h3><strong><?= e((string) $studentCount) ?></strong></article>
            <article class="stat-card"><h3>Módulos</h3><strong><?= e((string) $moduleCount) ?></strong></article>
            <article class="stat-card"><h3>Aulas</h3><strong><?= e((string) $lessonCount) ?></strong></article>
            <article class="stat-card"><h3>Materiais</h3><strong><?= e((string) $materialCount) ?></strong></article>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Avisos recentes</h2>
                <a href="<?= e(url('admin/avisos.php')) ?>" class="btn btn-ghost">Gerenciar avisos</a>
            </div>

            <div class="notice-list">
                <?php if (!$announcements): ?>
                    <p>Nenhum aviso cadastrado.</p>
                <?php else: ?>
                    <?php foreach ($announcements as $notice): ?>
                        <article class="notice-item">
                            <h3><?= e($notice['titulo']) ?></h3>
                            <small><?= e(date('d/m/Y H:i', strtotime((string) $notice['data']))) ?></small>
                            <p><?= nl2br(e($notice['mensagem'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>