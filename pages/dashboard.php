<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if (($_SESSION['role'] ?? '') !== 'aluno') {
    redirect('admin/dashboard.php');
}

$user = current_user($pdo);
$userId = (int) $user['id'];
$progressTable = progress_table_name($pdo);

$totalLessons = (int) $pdo->query('SELECT COUNT(*) FROM lessons')->fetchColumn();

$stmtCompleted = $pdo->prepare("SELECT COUNT(*) FROM {$progressTable} WHERE user_id = :user_id AND completed = 1");
$stmtCompleted->execute([':user_id' => $userId]);
$completedLessons = (int) $stmtCompleted->fetchColumn();

$overallProgress = $totalLessons > 0 ? (int) round(($completedLessons / $totalLessons) * 100) : 0;

$latestLessonsStmt = $pdo->query(
    'SELECT l.id, l.titulo, l.descricao, m.titulo AS modulo_titulo
     FROM lessons l
     INNER JOIN modules m ON m.id = l.module_id
     ORDER BY l.id DESC
     LIMIT 6'
);
$latestLessons = $latestLessonsStmt->fetchAll();

$announcementsStmt = $pdo->query(
    'SELECT id, titulo, mensagem, data
     FROM announcements
     ORDER BY data DESC
     LIMIT 5'
);
$announcements = $announcementsStmt->fetchAll();

$notificationsStmt = $pdo->prepare(
    'SELECT n.id, n.titulo, n.mensagem, n.url, n.criado_em,
            CASE WHEN nr.id IS NULL THEN 0 ELSE 1 END AS lida
     FROM notifications n
     LEFT JOIN notification_reads nr
       ON nr.notification_id = n.id AND nr.user_id = :user_id
     ORDER BY n.criado_em DESC
     LIMIT 5'
);
$notificationsStmt->execute([':user_id' => $userId]);
$notifications = $notificationsStmt->fetchAll();

$active_page = 'dashboard';
$page_title = 'Dashboard do Aluno';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content-area">
        <section class="hero-card">
            <h1>Bem-vindo, <?= e($user['nome']) ?>!</h1>
            <p>Siga sua jornada de formação cidadã e gestão pública no FL360.</p>
        </section>

        <section class="stats-grid">
            <article class="stat-card">
                <h3>Progresso total</h3>
                <strong><?= e((string) $overallProgress) ?>%</strong>
                <div class="progress-bar"><span style="width: <?= e((string) $overallProgress) ?>%"></span></div>
            </article>

            <article class="stat-card">
                <h3>Aulas concluídas</h3>
                <strong><?= e((string) $completedLessons) ?></strong>
                <small>de <?= e((string) $totalLessons) ?> aulas</small>
            </article>

            <article class="stat-card">
                <h3>Novidades</h3>
                <strong><?= e((string) notification_unread_count($pdo, $userId)) ?></strong>
                <small>não lidas</small>
            </article>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Avisos do programa</h2>
            </div>
            <div class="notice-list">
                <?php if (!$announcements): ?>
                    <p>Sem avisos no momento.</p>
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

        <section class="panel">
            <div class="panel-header">
                <h2>Novidades da plataforma</h2>
                <a href="<?= e(url('pages/notificacoes.php')) ?>" class="btn btn-ghost">Ver todas</a>
            </div>
            <div class="notice-list">
                <?php if (!$notifications): ?>
                    <p>Nenhuma novidade registrada ainda.</p>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <article class="notice-item <?= ((int) $notification['lida'] === 0) ? 'notice-unread' : '' ?>">
                            <h3><?= e($notification['titulo']) ?></h3>
                            <small><?= e(date('d/m/Y H:i', strtotime((string) $notification['criado_em']))) ?></small>
                            <p><?= e($notification['mensagem']) ?></p>
                            <?php if (!empty($notification['url'])): ?>
                                <a class="btn btn-ghost" href="<?= e(url((string) $notification['url'])) ?>">Abrir</a>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Últimas aulas liberadas</h2>
                <a href="<?= e(url('pages/modulos.php')) ?>" class="btn btn-ghost">Ver módulos</a>
            </div>
            <div class="lesson-list">
                <?php if (!$latestLessons): ?>
                    <p>Nenhuma aula cadastrada ainda.</p>
                <?php else: ?>
                    <?php foreach ($latestLessons as $lesson): ?>
                        <a class="lesson-item" href="<?= e(url('pages/aula.php?id=' . (int) $lesson['id'])) ?>">
                            <strong><?= e($lesson['titulo']) ?></strong>
                            <span><?= e($lesson['modulo_titulo']) ?></span>
                            <p><?= e($lesson['descricao']) ?></p>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>