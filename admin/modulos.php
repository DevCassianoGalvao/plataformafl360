<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        $ordem = (int) ($_POST['ordem'] ?? 0);

        if ($titulo === '') {
            flash('error', 'Título é obrigatório.');
            redirect('admin/modulos.php');
        }

        $stmt = $pdo->prepare('INSERT INTO modules (titulo, descricao, ordem) VALUES (:titulo, :descricao, :ordem)');
        $stmt->execute([':titulo' => $titulo, ':descricao' => $descricao, ':ordem' => $ordem]);

        create_notification(
            $pdo,
            'modulo',
            'Novo módulo disponível',
            'O módulo "' . $titulo . '" foi publicado na plataforma.',
            'pages/modulos.php'
        );

        flash('success', 'Módulo criado com sucesso.');
        redirect('admin/modulos.php');
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        $ordem = (int) ($_POST['ordem'] ?? 0);

        if ($id <= 0 || $titulo === '') {
            flash('error', 'Dados inválidos para atualizar módulo.');
            redirect('admin/modulos.php');
        }

        $stmt = $pdo->prepare('UPDATE modules SET titulo = :titulo, descricao = :descricao, ordem = :ordem WHERE id = :id');
        $stmt->execute([':titulo' => $titulo, ':descricao' => $descricao, ':ordem' => $ordem, ':id' => $id]);

        flash('success', 'Módulo atualizado.');
        redirect('admin/modulos.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM modules WHERE id = :id');
        $stmt->execute([':id' => $id]);

        flash('success', 'Módulo excluído.');
        redirect('admin/modulos.php');
    }
}

$modules = $pdo->query('SELECT id, titulo, descricao, ordem FROM modules ORDER BY ordem ASC, id ASC')->fetchAll();

$active_page = 'modulos';
$page_title = 'Gerenciar Módulos';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content-area">
        <section class="panel">
            <div class="panel-header"><h1>Novo módulo</h1></div>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="create">

                <label for="titulo">Título</label>
                <input id="titulo" type="text" name="titulo" required>

                <label for="descricao">Descrição</label>
                <textarea id="descricao" name="descricao" rows="3"></textarea>

                <label for="ordem">Ordem</label>
                <input id="ordem" type="number" name="ordem" value="1" min="0">

                <button type="submit" class="btn btn-primary">Criar módulo</button>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header"><h2>Lista de módulos</h2></div>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título e descrição</th>
                        <th>Ordem</th>
                        <th>Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$modules): ?>
                        <tr><td colspan="4">Nenhum módulo cadastrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($modules as $module): ?>
                            <tr>
                                <td><?= e((string) $module['id']) ?></td>
                                <td>
                                    <form method="post" class="inline-form wrap">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="id" value="<?= e((string) $module['id']) ?>">

                                        <input type="text" name="titulo" value="<?= e($module['titulo']) ?>" required>
                                        <input type="text" name="descricao" value="<?= e($module['descricao']) ?>">
                                        <input type="number" name="ordem" value="<?= e((string) $module['ordem']) ?>" min="0">
                                        <button class="btn btn-primary" type="submit">Salvar</button>
                                    </form>
                                </td>
                                <td><?= e((string) $module['ordem']) ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Excluir este módulo e as aulas relacionadas?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e((string) $module['id']) ?>">
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