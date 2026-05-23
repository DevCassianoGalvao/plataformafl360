<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx'];
$allowedMimeTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/octet-stream',
];
$maxUploadBytes = 10 * 1024 * 1024;

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $lessonId = (int) ($_POST['lesson_id'] ?? 0);
        $titulo = trim((string) ($_POST['titulo'] ?? ''));

        if ($lessonId <= 0 || $titulo === '' || !isset($_FILES['arquivo'])) {
            flash('error', 'Informe aula, título e arquivo.');
            redirect('admin/materiais.php');
        }

        $file = $_FILES['arquivo'];
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Falha no upload do arquivo.');
            redirect('admin/materiais.php');
        }

        if ((int) $file['size'] > $maxUploadBytes) {
            flash('error', 'Arquivo excede o limite de 10MB.');
            redirect('admin/materiais.php');
        }

        $originalName = (string) $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            flash('error', 'Tipo de arquivo não permitido.');
            redirect('admin/materiais.php');
        }

        $tmpName = (string) $file['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            flash('error', 'Arquivo bloqueado por validação de MIME.');
            redirect('admin/materiais.php');
        }

        $safeName = bin2hex(random_bytes(16)) . '.' . $extension;
        $uploadDir = ensure_upload_dir();
        $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $safeName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            flash('error', 'Não foi possível salvar o arquivo no servidor.');
            redirect('admin/materiais.php');
        }

        $stmt = $pdo->prepare('INSERT INTO materials (lesson_id, titulo, arquivo) VALUES (:lesson_id, :titulo, :arquivo)');
        $stmt->execute([':lesson_id' => $lessonId, ':titulo' => $titulo, ':arquivo' => $safeName]);

        create_notification(
            $pdo,
            'material',
            'Novo material disponível',
            'O material "' . $titulo . '" foi adicionado para estudo.',
            'pages/materiais.php'
        );

        flash('success', 'Material enviado com sucesso.');
        redirect('admin/materiais.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        $find = $pdo->prepare('SELECT arquivo FROM materials WHERE id = :id LIMIT 1');
        $find->execute([':id' => $id]);
        $material = $find->fetch();

        if ($material) {
            $delete = $pdo->prepare('DELETE FROM materials WHERE id = :id');
            $delete->execute([':id' => $id]);

            $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . (string) $material['arquivo'];
            if (is_file($path)) {
                @unlink($path);
            }

            flash('success', 'Material removido.');
        }

        redirect('admin/materiais.php');
    }
}

$lessonsStmt = $pdo->query(
    'SELECT l.id, l.titulo, m.titulo AS modulo_titulo
     FROM lessons l
     INNER JOIN modules m ON m.id = l.module_id
     ORDER BY m.ordem ASC, l.ordem ASC, l.id ASC'
);
$lessons = $lessonsStmt->fetchAll();

$materialsStmt = $pdo->query(
    'SELECT mat.id, mat.titulo, mat.arquivo, l.titulo AS aula_titulo, m.titulo AS modulo_titulo
     FROM materials mat
     INNER JOIN lessons l ON l.id = mat.lesson_id
     INNER JOIN modules m ON m.id = l.module_id
     ORDER BY mat.id DESC'
);
$materials = $materialsStmt->fetchAll();

$active_page = 'materiais';
$page_title = 'Gerenciar Materiais';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content-area">
        <section class="panel">
            <div class="panel-header"><h1>Novo material</h1></div>

            <?php if (!$lessons): ?>
                <p>Cadastre uma aula antes de enviar materiais.</p>
            <?php else: ?>
                <form method="post" enctype="multipart/form-data" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create">

                    <label for="lesson_id">Aula</label>
                    <select id="lesson_id" name="lesson_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($lessons as $lesson): ?>
                            <option value="<?= e((string) $lesson['id']) ?>">
                                <?= e($lesson['modulo_titulo'] . ' - ' . $lesson['titulo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="titulo">Título do material</label>
                    <input id="titulo" type="text" name="titulo" required>

                    <label for="arquivo">Arquivo</label>
                    <input id="arquivo" type="file" name="arquivo" accept=".pdf,.doc,.docx,.ppt,.pptx" required>

                    <small>Formatos permitidos: PDF, DOC, DOCX, PPT, PPTX (máx. 10MB)</small>

                    <button type="submit" class="btn btn-primary">Enviar material</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-header"><h2>Materiais cadastrados</h2></div>

            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Módulo</th>
                        <th>Aula</th>
                        <th>Arquivo</th>
                        <th>Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$materials): ?>
                        <tr><td colspan="6">Nenhum material cadastrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($materials as $material): ?>
                            <tr>
                                <td><?= e((string) $material['id']) ?></td>
                                <td><?= e($material['titulo']) ?></td>
                                <td><?= e($material['modulo_titulo']) ?></td>
                                <td><?= e($material['aula_titulo']) ?></td>
                                <td>
                                    <a class="btn btn-ghost" href="<?= e(url('pages/download.php?id=' . (int) $material['id'])) ?>">
                                        Download
                                    </a>
                                </td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Excluir este material?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e((string) $material['id']) ?>">
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
