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

$modulesStmt = $pdo->query('SELECT id, titulo, descricao, ordem FROM modules ORDER BY ordem ASC, id ASC');
$modules = $modulesStmt->fetchAll();

$active_page = 'modulos';
$page_title = 'Módulos do Curso';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content-area">
        <section class="panel">
            <div class="panel-header">
                <h1>Módulos e aulas</h1>
            </div>

            <?php if (!$modules): ?>
                <p>Nenhum módulo disponível no momento.</p>
            <?php endif; ?>

            <?php foreach ($modules as $module): ?>
                <?php
                $moduleId = (int) $module['id'];
                $progress = module_progress_percent($pdo, $userId, $moduleId);

                $lessonStmt = $pdo->prepare(
                    "SELECT l.id, l.titulo, l.descricao, l.ordem, COALESCE(p.completed, 0) AS completed
                     FROM lessons l
                     LEFT JOIN {$progressTable} p ON p.lesson_id = l.id AND p.user_id = :user_id
                     WHERE l.module_id = :module_id
                     ORDER BY l.ordem ASC, l.id ASC"
                );
                $lessonStmt->execute([':user_id' => $userId, ':module_id' => $moduleId]);
                $lessons = $lessonStmt->fetchAll();

                $quizStmt = $pdo->prepare('SELECT id FROM quizzes WHERE module_id = :mid LIMIT 1');
                $quizStmt->execute([':mid' => $moduleId]);
                $moduleQuiz = $quizStmt->fetch() ?: null;

                $lastAttempt = null;
                if ($moduleQuiz) {
                    $laStmt = $pdo->prepare(
                        'SELECT acertos, total, nota, feito_em FROM quiz_attempts
                         WHERE user_id = :uid AND quiz_id = :qid
                         ORDER BY feito_em DESC LIMIT 1'
                    );
                    $laStmt->execute([':uid' => $userId, ':qid' => (int) $moduleQuiz['id']]);
                    $lastAttempt = $laStmt->fetch() ?: null;
                }
                ?>

                <article class="module-card">
                    <div class="module-head">
                        <div>
                            <h2><?= e($module['titulo']) ?></h2>
                            <p><?= e($module['descricao']) ?></p>
                        </div>
                        <div class="module-progress">
                            <span><?= e((string) $progress) ?>%</span>
                            <div class="progress-bar"><span style="width: <?= e((string) $progress) ?>%"></span></div>
                        </div>
                    </div>

                    <div class="lesson-list">
                        <?php if (!$lessons): ?>
                            <p>Este módulo ainda não possui aulas.</p>
                        <?php else: ?>
                            <?php foreach ($lessons as $lesson): ?>
                                <a class="lesson-item" href="<?= e(url('pages/aula.php?id=' . (int) $lesson['id'])) ?>">
                                    <strong><?= e($lesson['titulo']) ?></strong>
                                    <span>Aula <?= e((string) $lesson['ordem']) ?></span>
                                    <p><?= e($lesson['descricao']) ?></p>
                                    <span class="badge <?= ((int) $lesson['completed'] === 1) ? 'badge-success' : 'badge-neutral' ?>">
                                        <?= ((int) $lesson['completed'] === 1) ? 'Concluída' : 'Pendente' ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($moduleQuiz): ?>
                        <div class="quiz-module-bar">
                            <div class="quiz-module-info">
                                <span class="quiz-icon">&#9998;</span>
                                <div>
                                    <strong>Quiz do módulo</strong>
                                    <?php if ($lastAttempt): ?>
                                        <small>
                                            Última nota:
                                            <span class="badge <?= (int) $lastAttempt['nota'] >= 60 ? 'badge-success' : 'badge-danger' ?>">
                                                <?= e((string) $lastAttempt['nota']) ?>%
                                            </span>
                                            (<?= e((string) $lastAttempt['acertos']) ?>/<?= e((string) $lastAttempt['total']) ?> acertos)
                                        </small>
                                    <?php else: ?>
                                        <small>Você ainda não realizou este quiz.</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a class="btn btn-primary"
                               href="<?= e(url('pages/quiz.php?module_id=' . $moduleId)) ?>">
                                <?= $lastAttempt ? 'Refazer quiz' : 'Fazer quiz' ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>