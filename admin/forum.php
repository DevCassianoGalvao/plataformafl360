<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$admin = current_user($pdo);

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');
    $topicId = (int) ($_POST['topic_id'] ?? 0);

    if ($action === 'create') {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        if ($titulo === '' || $mensagem === '') {
            flash('error', 'Preencha o título e a mensagem.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO forum_topics (user_id, titulo, mensagem, fixado, criado_em, atualizado_em) VALUES (:user_id, :titulo, :mensagem, 1, NOW(), NOW())');
            $stmt->execute([':user_id' => (int) $admin['id'], ':titulo' => $titulo, ':mensagem' => $mensagem]);
            create_notification($pdo, 'forum_topico', 'Novo tópico oficial', $titulo, 'pages/forum.php');
            flash('success', 'Tópico oficial publicado e fixado.');
        }
        redirect('admin/forum.php');
    }

    if (in_array($action, ['pin', 'lock', 'delete_topic'], true) && $topicId > 0) {
        if ($action === 'pin') {
            $pdo->prepare('UPDATE forum_topics SET fixado = IF(fixado = 1, 0, 1) WHERE id = :id')->execute([':id' => $topicId]);
            flash('success', 'Destaque do tópico atualizado.');
        } elseif ($action === 'lock') {
            $pdo->prepare('UPDATE forum_topics SET bloqueado = IF(bloqueado = 1, 0, 1) WHERE id = :id')->execute([':id' => $topicId]);
            flash('success', 'Permissão para respostas atualizada.');
        } else {
            $pdo->prepare('DELETE FROM forum_topics WHERE id = :id')->execute([':id' => $topicId]);
            flash('success', 'Tópico e respostas excluídos.');
        }
        redirect('admin/forum.php');
    }

    if ($action === 'delete_reply') {
        $replyId = (int) ($_POST['reply_id'] ?? 0);
        $pdo->prepare('DELETE FROM forum_replies WHERE id = :id')->execute([':id' => $replyId]);
        flash('success', 'Resposta removida.');
        redirect('admin/forum.php');
    }
}

$topics = $pdo->query(
    'SELECT t.id, t.titulo, t.mensagem, t.fixado, t.bloqueado, t.criado_em, u.nome,
            COUNT(r.id) AS total_respostas
     FROM forum_topics t
     INNER JOIN users u ON u.id = t.user_id
     LEFT JOIN forum_replies r ON r.topic_id = t.id
     GROUP BY t.id, t.titulo, t.mensagem, t.fixado, t.bloqueado, t.criado_em, u.nome
     ORDER BY t.fixado DESC, t.atualizado_em DESC'
)->fetchAll();
$replies = $pdo->query(
    'SELECT r.id, r.topic_id, r.mensagem, r.criado_em, u.nome, t.titulo AS topico_titulo
     FROM forum_replies r INNER JOIN users u ON u.id = r.user_id INNER JOIN forum_topics t ON t.id = r.topic_id
     ORDER BY r.criado_em DESC LIMIT 50'
)->fetchAll();

$active_page = 'forum_admin';
$page_title = 'Moderação do fórum';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content-area">
        <section class="page-heading"><div><span class="eyebrow">Comunidade</span><h1>Moderação do fórum</h1><p>Publique comunicados e mantenha as discussões seguras e relevantes.</p></div></section>
        <details class="panel disclosure-panel">
            <summary><span><strong>Novo tópico oficial</strong><small>Será publicado em destaque para toda a comunidade.</small></span><span class="disclosure-icon">+</span></summary>
            <form method="post" class="form-grid disclosure-content"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create"><label>Título<input name="titulo" maxlength="200" required></label><label>Mensagem<textarea name="mensagem" rows="4" required></textarea></label><button class="btn btn-primary">Publicar tópico</button></form>
        </details>
        <section class="panel">
            <div class="panel-header"><h2>Tópicos</h2><span class="badge badge-neutral"><?= count($topics) ?></span></div>
            <div class="management-list">
                <?php if (!$topics): ?><p>Nenhum tópico publicado.</p><?php endif; ?>
                <?php foreach ($topics as $topic): ?><article class="management-card">
                    <div class="management-card-head"><div><div class="inline-form wrap"><?php if ($topic['fixado']): ?><span class="badge badge-success">Fixado</span><?php endif; ?><?php if ($topic['bloqueado']): ?><span class="badge badge-warning">Bloqueado</span><?php endif; ?></div><h3><?= e($topic['titulo']) ?></h3><small>Por <?= e($topic['nome']) ?> · <?= (int) $topic['total_respostas'] ?> respostas</small></div></div>
                    <p><?= e(mb_strimwidth($topic['mensagem'], 0, 240, '...')) ?></p>
                    <div class="inline-form wrap"><a class="btn btn-ghost" href="<?= e(url('pages/topico.php?id=' . (int) $topic['id'])) ?>">Visualizar</a>
                        <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="pin"><input type="hidden" name="topic_id" value="<?= (int) $topic['id'] ?>"><button class="btn btn-ghost"><?= $topic['fixado'] ? 'Desafixar' : 'Fixar' ?></button></form>
                        <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="lock"><input type="hidden" name="topic_id" value="<?= (int) $topic['id'] ?>"><button class="btn btn-ghost"><?= $topic['bloqueado'] ? 'Liberar respostas' : 'Bloquear' ?></button></form>
                        <form method="post" onsubmit="return confirm('Excluir o tópico e todas as respostas?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_topic"><input type="hidden" name="topic_id" value="<?= (int) $topic['id'] ?>"><button class="btn btn-danger">Excluir</button></form>
                    </div>
                </article><?php endforeach; ?>
            </div>
        </section>
        <section class="panel"><div class="panel-header"><h2>Respostas recentes</h2></div><div class="moderation-replies">
            <?php if (!$replies): ?><p>Nenhuma resposta publicada.</p><?php endif; ?>
            <?php foreach ($replies as $reply): ?><article><div><strong><?= e($reply['nome']) ?></strong><small>em <?= e($reply['topico_titulo']) ?> · <?= e(date('d/m/Y H:i', strtotime($reply['criado_em']))) ?></small><p><?= e($reply['mensagem']) ?></p></div><form method="post" onsubmit="return confirm('Remover esta resposta?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_reply"><input type="hidden" name="reply_id" value="<?= (int) $reply['id'] ?>"><button class="btn btn-danger">Remover</button></form></article><?php endforeach; ?>
        </div></section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
