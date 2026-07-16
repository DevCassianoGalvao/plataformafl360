<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_student();

$user = current_user($pdo);
$userId = (int) $user['id'];
$progressTable = progress_table_name($pdo);
$totalLessons = (int) $pdo->query('SELECT COUNT(*) FROM lessons')->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT lesson_id) FROM {$progressTable} WHERE user_id = :user_id AND completed = 1");
$stmt->execute([':user_id' => $userId]);
$completedLessons = (int) $stmt->fetchColumn();
$overallProgress = $totalLessons ? (int) round(($completedLessons / $totalLessons) * 100) : 0;
$latestLessons = $pdo->query('SELECT l.id, l.titulo, m.titulo AS modulo_titulo FROM lessons l INNER JOIN modules m ON m.id = l.module_id ORDER BY l.id DESC LIMIT 3')->fetchAll();
$announcements = $pdo->query('SELECT id, titulo, mensagem, data FROM announcements ORDER BY data DESC LIMIT 2')->fetchAll();

$active_page = 'dashboard';
$page_title = 'Início';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content-area student-dashboard">
        <section class="hero-card dashboard-hero compact-hero"><div><span class="eyebrow">Sua jornada</span><h1>Olá, <?= e(explode(' ', trim($user['nome']))[0]) ?>.</h1><p>Continue de onde parou e avance no seu ritmo.</p></div><a class="btn btn-primary" href="<?= e(url('pages/modulos.php')) ?>">Continuar estudando</a></section>
        <section class="progress-strip"><div><span>Progresso geral</span><strong><?= $overallProgress ?>%</strong></div><div class="progress-bar"><span style="width: <?= $overallProgress ?>%"></span></div><small><?= $completedLessons ?> de <?= $totalLessons ?> aulas concluídas</small></section>
        <div class="dashboard-summary-grid">
            <section class="panel compact-panel"><div class="panel-header"><div><span class="eyebrow">Comunicados</span><h2>Avisos recentes</h2></div><a class="text-link" href="<?= e(url('pages/notificacoes.php')) ?>">Ver notificações</a></div><div class="compact-feed"><?php if (!$announcements): ?><p>Sem avisos no momento.</p><?php endif; ?><?php foreach ($announcements as $notice): ?><a href="<?= e(url('pages/notificacoes.php')) ?>"><div><strong><?= e($notice['titulo']) ?></strong><p><?= e(mb_strimwidth(strip_tags($notice['mensagem']), 0, 90, '...')) ?></p></div><time><?= e(date('d/m', strtotime($notice['data']))) ?></time></a><?php endforeach; ?></div></section>
            <section class="panel compact-panel"><div class="panel-header"><div><span class="eyebrow">Continue aprendendo</span><h2>Últimas aulas</h2></div><a class="text-link" href="<?= e(url('pages/modulos.php')) ?>">Ver módulos</a></div><div class="compact-feed"><?php if (!$latestLessons): ?><p>Nenhuma aula publicada.</p><?php endif; ?><?php foreach ($latestLessons as $lesson): ?><a href="<?= e(url('pages/aula.php?id=' . (int) $lesson['id'])) ?>"><div><strong><?= e($lesson['titulo']) ?></strong><p><?= e($lesson['modulo_titulo']) ?></p></div><span>Assistir →</span></a><?php endforeach; ?></div></section>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
