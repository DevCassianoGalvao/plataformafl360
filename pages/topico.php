<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if (($_SESSION['role'] ?? '') !== 'aluno') {
    redirect('admin/dashboard.php');
}

$user = current_user($pdo);
$userId = (int) $user['id'];
$topicId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($topicId <= 0) {
    flash('error', 'Tópico inválido.');
    redirect('pages/forum.php');
}

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    $mensagem = trim((string) ($_POST['mensagem'] ?? ''));

    if ($mensagem === '') {
        flash('error', 'Digite uma mensagem para responder.');
        redirect('pages/topico.php?id=' . $topicId);
    }

    $insert = $pdo->prepare(
        'INSERT INTO forum_replies (topic_id, user_id, mensagem, criado_em)
         VALUES (:topic_id, :user_id, :mensagem, NOW())'
    );
    $insert->execute([':topic_id' => $topicId, ':user_id' => $userId, ':mensagem' => $mensagem]);

    $pdo->prepare('UPDATE forum_topics SET atualizado_em = NOW() WHERE id = :id')->execute([':id' => $topicId]);

    $topicTitleStmt = $pdo->prepare('SELECT titulo FROM forum_topics WHERE id = :id LIMIT 1');
    $topicTitleStmt->execute([':id' => $topicId]);
    $topicTitle = (string) ($topicTitleStmt->fetchColumn() ?: 'Discussão no fórum');

    create_notification(
        $pdo,
        'forum_resposta',
        'Nova resposta no fórum',
        $user['nome'] . ' respondeu ao tópico: ' . $topicTitle,
        'pages/topico.php?id=' . $topicId
    );

    flash('success', 'Resposta enviada com sucesso.');
    redirect('pages/topico.php?id=' . $topicId);
}

$topicStmt = $pdo->prepare(
    'SELECT t.id, t.titulo, t.mensagem, t.criado_em, u.nome
     FROM forum_topics t
     INNER JOIN users u ON u.id = t.user_id
     WHERE t.id = :id
     LIMIT 1'
);
$topicStmt->execute([':id' => $topicId]);
$topic = $topicStmt->fetch();

if (!$topic) {
    flash('error', 'Tópico não encontrado.');
    redirect('pages/forum.php');
}

$repliesStmt = $pdo->prepare(
    'SELECT r.id, r.mensagem, r.criado_em, u.nome
     FROM forum_replies r
     INNER JOIN users u ON u.id = r.user_id
     WHERE r.topic_id = :topic_id
     ORDER BY r.id ASC'
);
$repliesStmt->execute([':topic_id' => $topicId]);
$replies = $repliesStmt->fetchAll();

$active_page = 'forum';
$page_title = 'Tópico do Fórum';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content-area">
        <section class="panel">
            <div class="panel-header">
                <h1><?= e($topic['titulo']) ?></h1>
                <a class="btn btn-ghost" href="<?= e(url('pages/forum.php')) ?>">Voltar ao fórum</a>
            </div>

            <article class="notice-item">
                <small>Criado por <?= e($topic['nome']) ?> em <?= e(date('d/m/Y H:i', strtotime((string) $topic['criado_em']))) ?></small>
                <p><?= nl2br(e($topic['mensagem'])) ?></p>
            </article>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Respostas</h2>
            </div>

            <div class="notice-list">
                <?php if (!$replies): ?>
                    <p>Este tópico ainda não possui respostas.</p>
                <?php else: ?>
                    <?php foreach ($replies as $reply): ?>
                        <article class="notice-item">
                            <small><?= e($reply['nome']) ?> • <?= e(date('d/m/Y H:i', strtotime((string) $reply['criado_em']))) ?></small>
                            <p><?= nl2br(e($reply['mensagem'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="mensagem">Sua resposta</label>
                <textarea id="mensagem" name="mensagem" rows="4" required></textarea>
                <button type="submit" class="btn btn-primary">Responder</button>
            </form>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
