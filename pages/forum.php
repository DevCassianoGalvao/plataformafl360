<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_student();

$user = current_user($pdo);
$userId = (int) $user['id'];

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);

    $titulo = trim((string) ($_POST['titulo'] ?? ''));
    $mensagem = trim((string) ($_POST['mensagem'] ?? ''));

    if ($titulo === '' || $mensagem === '') {
        flash('error', 'Preencha o título e a mensagem para abrir um tópico.');
        redirect('pages/forum.php');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO forum_topics (user_id, titulo, mensagem, criado_em, atualizado_em)
         VALUES (:user_id, :titulo, :mensagem, NOW(), NOW())'
    );
    $stmt->execute([':user_id' => $userId, ':titulo' => $titulo, ':mensagem' => $mensagem]);

    $topicId = (int) $pdo->lastInsertId();
    create_notification(
        $pdo,
        'forum_topico',
        'Novo tópico no fórum',
        $user['nome'] . ' criou o tópico: ' . $titulo,
        'pages/topico.php?id=' . $topicId
    );

    flash('success', 'Tópico criado com sucesso.');
    redirect('pages/forum.php');
}

$topicsStmt = $pdo->query(
    'SELECT t.id, t.titulo, t.mensagem, t.fixado, t.bloqueado, t.criado_em, t.atualizado_em,
            u.nome,
            COUNT(r.id) AS total_respostas
     FROM forum_topics t
     INNER JOIN users u ON u.id = t.user_id
     LEFT JOIN forum_replies r ON r.topic_id = t.id
     GROUP BY t.id, t.titulo, t.mensagem, t.fixado, t.bloqueado, t.criado_em, t.atualizado_em, u.nome
     ORDER BY t.fixado DESC, t.atualizado_em DESC'
);
$topics = $topicsStmt->fetchAll();

$active_page = 'forum';
$page_title = 'Fórum da Comunidade';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content-area">
        <section class="panel">
            <div class="panel-header">
                <h1>Comunidade FL360</h1>
            </div>
            <p>Troque ideias, dúvidas e experiências com outros alunos.</p>

            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <label for="titulo">Título do tópico</label>
                <input id="titulo" type="text" name="titulo" required>

                <label for="mensagem">Mensagem inicial</label>
                <textarea id="mensagem" name="mensagem" rows="4" required></textarea>

                <button type="submit" class="btn btn-primary">Criar tópico</button>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Tópicos recentes</h2>
            </div>

            <div class="forum-topic-list">
                <?php if (!$topics): ?>
                    <p>Ainda não há tópicos no fórum.</p>
                <?php else: ?>
                    <?php foreach ($topics as $topic): ?>
                        <a class="forum-topic-item" href="<?= e(url('pages/topico.php?id=' . (int) $topic['id'])) ?>">
                            <span class="inline-form wrap"><?php if ($topic['fixado']): ?><span class="badge badge-success">Fixado</span><?php endif; ?><?php if ($topic['bloqueado']): ?><span class="badge badge-warning">Somente leitura</span><?php endif; ?></span>
                            <strong><?= e($topic['titulo']) ?></strong>
                            <span>Por <?= e($topic['nome']) ?> em <?= e(date('d/m/Y H:i', strtotime((string) $topic['criado_em']))) ?></span>
                            <p><?= e($topic['mensagem']) ?></p>
                            <small><?= e((string) $topic['total_respostas']) ?> respostas</small>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
