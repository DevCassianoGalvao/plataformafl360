<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_student();

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

$active_page = 'dashboard';
$page_title = 'Dashboard do Aluno';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content-area">
        <section class="hero-card dashboard-hero">
            <div><span class="eyebrow">Sua jornada</span><h1>Bem-vindo, <?= e($user['nome']) ?>!</h1>
            <p>Continue sua formação cidadã e avance no seu ritmo.</p></div>
            <a class="btn btn-primary" href="<?= e(url('pages/modulos.php')) ?>">Continuar estudando</a>
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
