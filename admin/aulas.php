<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $moduleId = (int) ($_POST['module_id'] ?? 0);
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        $videoUrl = trim((string) ($_POST['video_url'] ?? ''));
        $ordem = (int) ($_POST['ordem'] ?? 0);

        if ($moduleId <= 0 || $titulo === '' || $videoUrl === '') {
            flash('error', 'Preencha módulo, título e URL do vídeo.');
            redirect('admin/aulas.php');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO lessons (module_id, titulo, descricao, video_url, ordem)
             VALUES (:module_id, :titulo, :descricao, :video_url, :ordem)'
        );
        $stmt->execute([
            ':module_id' => $moduleId,
            ':titulo' => $titulo,
            ':descricao' => $descricao,
            ':video_url' => $videoUrl,
            ':ordem' => $ordem,
        ]);

        $lessonId = (int) $pdo->lastInsertId();
        create_notification(
            $pdo,
            'aula',
            'Nova aula liberada',
            'A aula "' . $titulo . '" já está disponível para acesso.',
            'pages/aula.php?id=' . $lessonId
        );

        flash('success', 'Aula criada com sucesso.');
        redirect('admin/aulas.php');
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $moduleId = (int) ($_POST['module_id'] ?? 0);
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        $videoUrl = trim((string) ($_POST['video_url'] ?? ''));
        $ordem = (int) ($_POST['ordem'] ?? 0);

        if ($id <= 0 || $moduleId <= 0 || $titulo === '' || $videoUrl === '') {
            flash('error', 'Dados inválidos para atualizar aula.');
            redirect('admin/aulas.php');
        }

        $stmt = $pdo->prepare(
            'UPDATE lessons
             SET module_id = :module_id, titulo = :titulo, descricao = :descricao, video_url = :video_url, ordem = :ordem
             WHERE id = :id'
        );
        $stmt->execute([
            ':module_id' => $moduleId,
            ':titulo' => $titulo,
            ':descricao' => $descricao,
            ':video_url' => $videoUrl,
            ':ordem' => $ordem,
            ':id' => $id,
        ]);

        flash('success', 'Aula atualizada.');
        redirect('admin/aulas.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM lessons WHERE id = :id');
        $stmt->execute([':id' => $id]);

        flash('success', 'Aula excluída.');
        redirect('admin/aulas.php');
    }
}

$modules = $pdo->query('SELECT id, titulo FROM modules ORDER BY ordem ASC, id ASC')->fetchAll();

$lessonsStmt = $pdo->query(
    'SELECT l.id, l.module_id, l.titulo, l.descricao, l.video_url, l.ordem, m.titulo AS modulo_titulo
     FROM lessons l
     INNER JOIN modules m ON m.id = l.module_id
     ORDER BY m.ordem ASC, l.ordem ASC, l.id ASC'
);
$lessons = $lessonsStmt->fetchAll();

$active_page = 'aulas';
$page_title = 'Gerenciar Aulas';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content-area">
        <section class="panel">
            <div class="panel-header"><h1>Nova aula</h1></div>

            <?php if (!$modules): ?>
                <p>Cadastre um módulo antes de criar aulas.</p>
            <?php else: ?>
                <form method="post" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create">

                    <label for="module_id">Módulo</label>
                    <select id="module_id" name="module_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($modules as $module): ?>
                            <option value="<?= e((string) $module['id']) ?>"><?= e($module['titulo']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="titulo">Título</label>
                    <input id="titulo" type="text" name="titulo" required>

                    <label for="descricao">Descrição</label>
                    <textarea id="descricao" name="descricao" rows="3"></textarea>

                    <label for="video_url">URL do YouTube</label>
                    <input id="video_url" type="url" name="video_url" required placeholder="https://www.youtube.com/watch?v=...">

                    <label for="ordem">Ordem</label>
                    <input id="ordem" type="number" name="ordem" value="1" min="0">

                    <button type="submit" class="btn btn-primary">Criar aula</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-header"><h2>Lista de aulas</h2></div>

            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Dados da aula</th>
                        <th>Módulo</th>
                        <th>Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$lessons): ?>
                        <tr><td colspan="4">Nenhuma aula cadastrada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($lessons as $lesson): ?>
                            <tr>
                                <td><?= e((string) $lesson['id']) ?></td>
                                <td>
                                    <form method="post" class="inline-form wrap">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="id" value="<?= e((string) $lesson['id']) ?>">

                                        <input type="text" name="titulo" value="<?= e($lesson['titulo']) ?>" required>
                                        <input type="text" name="descricao" value="<?= e($lesson['descricao']) ?>">
                                        <input type="url" name="video_url" value="<?= e($lesson['video_url']) ?>" required>
                                        <input type="number" name="ordem" value="<?= e((string) $lesson['ordem']) ?>" min="0">
                                        <select name="module_id" required>
                                            <?php foreach ($modules as $module): ?>
                                                <option value="<?= e((string) $module['id']) ?>" <?= (int) $lesson['module_id'] === (int) $module['id'] ? 'selected' : '' ?>>
                                                    <?= e($module['titulo']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-primary" type="submit">Salvar</button>
                                    </form>
                                </td>
                                <td><?= e($lesson['modulo_titulo']) ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Excluir esta aula?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e((string) $lesson['id']) ?>">
                                        <button class="btn btn-danger" type="submit">Excluir</button>
                                    </form>
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