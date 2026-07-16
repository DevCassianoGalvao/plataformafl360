<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_student();

$user = current_user($pdo);
$userId = (int) $user['id'];
$moduleId = (int) ($_GET['id'] ?? 0);
$progressTable = progress_table_name($pdo);

$stmt = $pdo->prepare(
    'SELECT m.id, m.titulo, m.descricao, m.ordem,
            GROUP_CONCAT(DISTINCT u.nome ORDER BY u.nome SEPARATOR \'||\') AS professores
     FROM modules m LEFT JOIN module_professors mp ON mp.module_id = m.id LEFT JOIN users u ON u.id = mp.user_id
     WHERE m.id = :id GROUP BY m.id, m.titulo, m.descricao, m.ordem LIMIT 1'
);
$stmt->execute([':id' => $moduleId]);
$module = $stmt->fetch();
if (!$module) { flash('error', 'Módulo não encontrado.'); redirect('pages/modulos.php'); }

$stmt = $pdo->prepare(
    "SELECT l.id, l.titulo, l.descricao, l.ordem,
            COALESCE((SELECT MAX(p.completed) FROM {$progressTable} p WHERE p.lesson_id = l.id AND p.user_id = :user_id), 0) AS completed
     FROM lessons l WHERE l.module_id = :module_id ORDER BY l.ordem, l.id"
);
$stmt->execute([':user_id' => $userId, ':module_id' => $moduleId]);
$lessons = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT id, titulo FROM materials WHERE module_id = :module_id AND lesson_id IS NULL ORDER BY id DESC');
$stmt->execute([':module_id' => $moduleId]);
$materials = $stmt->fetchAll();
$stmt = $pdo->prepare('SELECT id, titulo, descricao, liberacao FROM quizzes WHERE module_id = :module_id LIMIT 1');
$stmt->execute([':module_id' => $moduleId]);
$quiz = $stmt->fetch() ?: null;
$progress = module_progress_percent($pdo, $userId, $moduleId);
$quizOpen = $quiz && ($quiz['liberacao'] === 'sempre' || $progress >= 100);

$active_page = 'modulos';
$page_title = $module['titulo'];
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content-area">
        <a class="back-link" href="<?= e(url('pages/modulos.php')) ?>">← Todos os módulos</a>
        <section class="module-detail-hero"><div><span class="eyebrow">Módulo <?= (int) $module['ordem'] ?></span><h1><?= e($module['titulo']) ?></h1><p><?= e($module['descricao']) ?></p><div class="module-teachers"><span>Professores</span><strong><?= $module['professores'] ? e(str_replace('||', ', ', $module['professores'])) : 'Equipe FL360' ?></strong></div></div><div class="module-detail-progress"><strong><?= $progress ?>%</strong><span>concluído</span><div class="progress-bar"><span style="width: <?= $progress ?>%"></span></div></div></section>
        <div class="module-detail-layout">
            <section class="panel"><div class="panel-header"><div><span class="eyebrow">Conteúdo</span><h2>Aulas</h2></div><span class="badge badge-neutral"><?= count($lessons) ?></span></div>
                <div class="module-lesson-steps"><?php if (!$lessons): ?><p>Nenhuma aula publicada.</p><?php endif; ?><?php foreach ($lessons as $index => $lesson): ?><a href="<?= e(url('pages/aula.php?id=' . (int) $lesson['id'])) ?>"><span class="lesson-step-number"><?= $index + 1 ?></span><div><strong><?= e($lesson['titulo']) ?></strong><p><?= e(mb_strimwidth((string) $lesson['descricao'], 0, 130, '...')) ?></p></div><span class="badge <?= (int) $lesson['completed'] === 1 ? 'badge-success' : 'badge-neutral' ?>"><?= (int) $lesson['completed'] === 1 ? 'Concluída' : 'Assistir' ?></span></a><?php endforeach; ?></div>
            </section>
            <aside class="module-detail-aside">
                <section class="panel compact-panel"><div class="panel-header"><h2>Materiais do módulo</h2></div><?php if (!$materials): ?><p class="muted">Nenhum material geral.</p><?php endif; ?><?php foreach ($materials as $material): ?><a class="resource-link" href="<?= e(url('pages/download.php?id=' . (int) $material['id'])) ?>"><span><?= nav_icon('file') ?></span><strong><?= e($material['titulo']) ?></strong><small>Baixar</small></a><?php endforeach; ?></section>
                <?php if ($quiz): ?><section class="panel compact-panel quiz-gate <?= $quizOpen ? 'is-open' : 'is-locked' ?>"><span class="eyebrow">Avaliação</span><h2><?= e($quiz['titulo']) ?></h2><p><?= $quizOpen ? e($quiz['descricao']) : 'Conclua todas as aulas para liberar este quiz.' ?></p><?php if ($quizOpen): ?><a class="btn btn-primary btn-block" href="<?= e(url('pages/quiz.php?module_id=' . $moduleId)) ?>">Acessar quiz</a><?php else: ?><span class="badge badge-warning">Bloqueado · <?= $progress ?>%</span><?php endif; ?></section><?php endif; ?>
            </aside>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
