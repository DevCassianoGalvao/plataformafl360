<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if (($_SESSION['role'] ?? '') !== 'aluno') {
    redirect('admin/dashboard.php');
}

$user = current_user($pdo);
$userId = (int) $user['id'];
$lessonId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$progressTable = progress_table_name($pdo);

if ($lessonId <= 0) {
    flash(‘error’, ‘Aula inválida.’);
    redirect('pages/modulos.php');
}

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'complete') {
        $findStmt = $pdo->prepare("SELECT id FROM {$progressTable} WHERE user_id = :user_id AND lesson_id = :lesson_id LIMIT 1");
        $findStmt->execute([':user_id' => $userId, ':lesson_id' => $lessonId]);
        $existingId = (int) ($findStmt->fetchColumn() ?: 0);

        if ($existingId > 0) {
            $updateStmt = $pdo->prepare("UPDATE {$progressTable} SET completed = 1 WHERE id = :id");
            $updateStmt->execute([':id' => $existingId]);
        } else {
            $insertStmt = $pdo->prepare("INSERT INTO {$progressTable} (user_id, lesson_id, completed) VALUES (:user_id, :lesson_id, 1)");
            $insertStmt->execute([':user_id' => $userId, ':lesson_id' => $lessonId]);
        }

        flash(‘success’, ‘Aula marcada como concluída.’);
    }

    redirect('pages/aula.php?id=' . $lessonId);
}

$lessonStmt = $pdo->prepare(
    'SELECT l.id, l.module_id, l.titulo, l.descricao, l.video_url, l.ordem, m.titulo AS modulo_titulo
     FROM lessons l
     INNER JOIN modules m ON m.id = l.module_id
     WHERE l.id = :id
     LIMIT 1'
);
$lessonStmt->execute([':id' => $lessonId]);
$lesson = $lessonStmt->fetch();

if (!$lesson) {
    flash(‘error’, ‘Aula não encontrada.’);
    redirect('pages/modulos.php');
}

$statusStmt = $pdo->prepare("SELECT completed FROM {$progressTable} WHERE user_id = :user_id AND lesson_id = :lesson_id ORDER BY id DESC LIMIT 1");
$statusStmt->execute([':user_id' => $userId, ':lesson_id' => $lessonId]);
$completed = ((int) ($statusStmt->fetchColumn() ?: 0)) === 1;

$materialsStmt = $pdo->prepare('SELECT id, titulo, arquivo FROM materials WHERE lesson_id = :lesson_id ORDER BY id DESC');
$materialsStmt->execute([':lesson_id' => $lessonId]);
$materials = $materialsStmt->fetchAll();

$nextStmt = $pdo->prepare(
    'SELECT id FROM lessons
     WHERE module_id = :module_id AND (ordem > :ordem_maior OR (ordem = :ordem_igual AND id > :id))
     ORDER BY ordem ASC, id ASC LIMIT 1'
);
$nextStmt->execute([
    ':module_id' => (int) $lesson['module_id'],
    ':ordem_maior' => (int) $lesson['ordem'],
    ':ordem_igual' => (int) $lesson['ordem'],
    ':id' => $lessonId,
]);
$nextId = (int) ($nextStmt->fetchColumn() ?: 0);

$embedUrl = get_youtube_embed_url((string) $lesson['video_url']);

$active_page = 'modulos';
$page_title = 'Aula';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content-area">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h1><?= e($lesson['titulo']) ?></h1>
                    <p><?= e($lesson['modulo_titulo']) ?></p>
                </div>
                <span class="badge <?= $completed ? 'badge-success' : 'badge-neutral' ?>">
                    <?= $completed ? ‘Concluída’ : ‘Pendente’ ?>
                </span>
            </div>

            <p><?= e($lesson['descricao']) ?></p>

            <div class="video-wrapper">
                <?php if ($embedUrl !== ''): ?>
                    <iframe
                        src="<?= e($embedUrl) ?>"
                        title="Vídeo da aula"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allowfullscreen>
                    </iframe>
                <?php else: ?>
                    <p>Vídeo não informado.</p>
                <?php endif; ?>
            </div>

            <form method="post" class="inline-form wrap">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="complete">
                <button type="submit" class="btn btn-primary" <?= $completed ? 'disabled' : '' ?>>
                    <?= $completed ? ‘Aula já concluída’ : ‘Marcar aula como concluída’ ?>
                </button>
                <?php if ($nextId > 0): ?>
                    <a class="btn btn-ghost" href="<?= e(url(‘pages/aula.php?id=’ . $nextId)) ?>">Próxima aula</a>
                <?php endif; ?>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Materiais desta aula</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>Título</th>
                        <th>Download</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$materials): ?>
                        <tr>
                            <td colspan="2">Nenhum material cadastrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($materials as $material): ?>
                            <tr>
                                <td><?= e($material['titulo']) ?></td>
                                <td>
                                    <a class="btn btn-ghost" href="<?= e(url('pages/download.php?id=' . (int) $material['id'])) ?>">
                                        Baixar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>




