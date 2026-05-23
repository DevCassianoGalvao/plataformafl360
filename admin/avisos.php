<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));

        if ($titulo === '' || $mensagem === '') {
            flash('error', 'Título e mensagem são obrigatórios.');
            redirect('admin/avisos.php');
        }

        $stmt = $pdo->prepare('INSERT INTO announcements (titulo, mensagem, data) VALUES (:titulo, :mensagem, NOW())');
        $stmt->execute([':titulo' => $titulo, ':mensagem' => $mensagem]);

        create_notification(
            $pdo,
            'aviso',
            'Novo aviso do programa',
            $titulo . ': ' . $mensagem,
            'pages/dashboard.php'
        );

        flash('success', 'Aviso publicado com sucesso.');
        redirect('admin/avisos.php');
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
        $dataRaw = trim((string) ($_POST['data'] ?? ''));

        if ($id <= 0 || $titulo === '' || $mensagem === '' || $dataRaw === '') {
            flash('error', 'Dados inválidos para atualizar aviso.');
            redirect('admin/avisos.php');
        }

        $data = str_replace('T', ' ', $dataRaw);
        if (strlen($data) === 16) {
            $data .= ':00';
        }

        $stmt = $pdo->prepare('UPDATE announcements SET titulo = :titulo, mensagem = :mensagem, data = :data WHERE id = :id');
        $stmt->execute([':titulo' => $titulo, ':mensagem' => $mensagem, ':data' => $data, ':id' => $id]);

        flash('success', 'Aviso atualizado.');
        redirect('admin/avisos.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM announcements WHERE id = :id');
        $stmt->execute([':id' => $id]);

        flash('success', 'Aviso excluído.');
        redirect('admin/avisos.php');
    }
}

$announcements = $pdo->query('SELECT id, titulo, mensagem, data FROM announcements ORDER BY data DESC')->fetchAll();

$active_page = 'avisos';
$page_title = 'Gerenciar Avisos';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content-area">
        <section class="panel">
            <div class="panel-header"><h1>Novo aviso</h1></div>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="create">

                <label for="titulo">Título</label>
                <input id="titulo" type="text" name="titulo" required>

                <label for="mensagem">Mensagem</label>
                <textarea id="mensagem" name="mensagem" rows="4" required></textarea>

                <button type="submit" class="btn btn-primary">Publicar aviso</button>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header"><h2>Avisos publicados</h2></div>

            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Conteúdo</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$announcements): ?>
                        <tr><td colspan="4">Nenhum aviso publicado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($announcements as $notice): ?>
                            <tr>
                                <td><?= e((string) $notice['id']) ?></td>
                                <td>
                                    <form method="post" class="form-grid compact">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="id" value="<?= e((string) $notice['id']) ?>">

                                        <input type="text" name="titulo" value="<?= e($notice['titulo']) ?>" required>
                                        <textarea name="mensagem" rows="3" required><?= e($notice['mensagem']) ?></textarea>
                                        <input type="datetime-local" name="data" value="<?= e(date('Y-m-d\\TH:i', strtotime((string) $notice['data']))) ?>" required>

                                        <button class="btn btn-primary" type="submit">Salvar</button>
                                    </form>
                                </td>
                                <td><?= e(date('d/m/Y H:i', strtotime((string) $notice['data']))) ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Excluir este aviso?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e((string) $notice['id']) ?>">
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