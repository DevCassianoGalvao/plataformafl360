<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_content_manager();

$manager = current_user($pdo);
$managerId = (int) $manager['id'];
$isAdmin = $manager['role'] === 'admin';
$returnPath = content_manager_path('materiais.php');

if (!db_column_exists($pdo, 'materials', 'module_id')) {
    flash('error', 'Execute a atualização do banco antes de gerenciar materiais.');
    redirect($isAdmin ? 'admin/migracoes.php' : 'professor/dashboard.php');
}

$allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx'];
$allowedMimeTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/octet-stream'];
$maxUploadBytes = 10 * 1024 * 1024;

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $targetType = (string) ($_POST['target_type'] ?? '');
        $moduleId = $targetType === 'module' ? (int) ($_POST['module_id'] ?? 0) : 0;
        $lessonId = $targetType === 'lesson' ? (int) ($_POST['lesson_id'] ?? 0) : 0;
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        if ($lessonId > 0) {
            $stmt = $pdo->prepare('SELECT module_id FROM lessons WHERE id = :id');
            $stmt->execute([':id' => $lessonId]);
            $moduleId = (int) ($stmt->fetchColumn() ?: 0);
        }
        if (!in_array($targetType, ['module', 'lesson'], true) || !can_manage_module($pdo, $moduleId, $manager) || $titulo === '' || !isset($_FILES['arquivo'])) {
            flash('error', 'Informe um destino permitido, título e arquivo.'); redirect($returnPath);
        }
        $file = $_FILES['arquivo'];
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) $file['size'] <= 0) {
            flash('error', 'Falha no envio do arquivo.'); redirect($returnPath);
        }
        if ((int) $file['size'] > $maxUploadBytes) { flash('error', 'O arquivo excede o limite de 10 MB.'); redirect($returnPath); }
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $tmpName = (string) $file['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
        if ($finfo) { finfo_close($finfo); }
        if (!in_array($extension, $allowedExtensions, true) || !in_array($mimeType, $allowedMimeTypes, true)) {
            flash('error', 'Formato de arquivo não permitido.'); redirect($returnPath);
        }
        $safeName = bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($tmpName, ensure_upload_dir() . DIRECTORY_SEPARATOR . $safeName)) {
            flash('error', 'Não foi possível salvar o arquivo no servidor.'); redirect($returnPath);
        }
        $stmt = $pdo->prepare('INSERT INTO materials (module_id, lesson_id, titulo, arquivo) VALUES (:module_id, :lesson_id, :titulo, :arquivo)');
        $stmt->execute([':module_id' => $targetType === 'module' ? $moduleId : null, ':lesson_id' => $targetType === 'lesson' ? $lessonId : null, ':titulo' => $titulo, ':arquivo' => $safeName]);
        create_notification($pdo, 'material', 'Novo material disponível', 'O material "' . $titulo . '" foi adicionado.', 'pages/materiais.php');
        flash('success', 'Material enviado com sucesso.'); redirect($returnPath);
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT mat.arquivo, COALESCE(mat.module_id, l.module_id) AS module_id FROM materials mat LEFT JOIN lessons l ON l.id = mat.lesson_id WHERE mat.id = :id');
        $stmt->execute([':id' => $id]);
        $material = $stmt->fetch();
        if (!$material || !can_manage_module($pdo, (int) $material['module_id'], $manager)) { flash('error', 'Você não tem permissão para excluir este material.'); redirect($returnPath); }
        $pdo->prepare('DELETE FROM materials WHERE id = :id')->execute([':id' => $id]);
        $path = ensure_upload_dir() . DIRECTORY_SEPARATOR . basename((string) $material['arquivo']);
        if (is_file($path)) { @unlink($path); }
        flash('success', 'Material removido.'); redirect($returnPath);
    }
}

$moduleSql = 'SELECT id, titulo FROM modules'; $moduleParams = [];
if (!$isAdmin) { $moduleSql .= ' WHERE professor_id = :professor_id'; $moduleParams[':professor_id'] = $managerId; }
$moduleSql .= ' ORDER BY ordem, id'; $stmt = $pdo->prepare($moduleSql); $stmt->execute($moduleParams); $modules = $stmt->fetchAll();
$lessonSql = 'SELECT l.id, l.titulo, m.titulo AS modulo_titulo FROM lessons l INNER JOIN modules m ON m.id = l.module_id'; $lessonParams = [];
if (!$isAdmin) { $lessonSql .= ' WHERE m.professor_id = :professor_id'; $lessonParams[':professor_id'] = $managerId; }
$lessonSql .= ' ORDER BY m.ordem, l.ordem, l.id'; $stmt = $pdo->prepare($lessonSql); $stmt->execute($lessonParams); $lessons = $stmt->fetchAll();
$materialSql = 'SELECT mat.id, mat.titulo, mat.arquivo, mat.module_id, mat.lesson_id, l.titulo AS aula_titulo, m.titulo AS modulo_titulo FROM materials mat LEFT JOIN lessons l ON l.id = mat.lesson_id INNER JOIN modules m ON m.id = COALESCE(mat.module_id, l.module_id)'; $materialParams = [];
if (!$isAdmin) { $materialSql .= ' WHERE m.professor_id = :professor_id'; $materialParams[':professor_id'] = $managerId; }
$materialSql .= ' ORDER BY mat.id DESC'; $stmt = $pdo->prepare($materialSql); $stmt->execute($materialParams); $materials = $stmt->fetchAll();

$active_page = 'materiais'; $page_title = 'Materiais'; require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout"><?php require_once __DIR__ . '/../includes/sidebar.php'; ?><main class="content-area">
<section class="page-heading"><div><span class="eyebrow">Gestão pedagógica</span><h1>Materiais</h1><p>Disponibilize documentos para um módulo inteiro ou uma aula específica.</p></div></section>
<section class="panel"><div class="panel-header"><h2>Novo material</h2></div>
<?php if (!$modules): ?><p>Crie ou atribua um módulo antes de enviar materiais.</p><?php else: ?>
<form method="post" enctype="multipart/form-data" class="form-grid form-grid-columns">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
<label>Destino<select name="target_type" id="materialTarget" required><option value="module">Módulo inteiro</option><option value="lesson">Aula específica</option></select></label>
<label data-module-target>Módulo<select name="module_id"><?php foreach ($modules as $module): ?><option value="<?= (int) $module['id'] ?>"><?= e($module['titulo']) ?></option><?php endforeach; ?></select></label>
<label data-lesson-target hidden>Aula<select name="lesson_id"><option value="">Selecione</option><?php foreach ($lessons as $lesson): ?><option value="<?= (int) $lesson['id'] ?>"><?= e($lesson['modulo_titulo'] . ' · ' . $lesson['titulo']) ?></option><?php endforeach; ?></select></label>
<label class="field-wide">Título<input type="text" name="titulo" maxlength="180" required></label>
<label class="field-wide">Arquivo<input type="file" name="arquivo" accept=".pdf,.doc,.docx,.ppt,.pptx" required><small>PDF, DOC, DOCX, PPT ou PPTX. Máximo de 10 MB.</small></label>
<div class="field-wide"><button class="btn btn-primary" type="submit">Enviar material</button></div></form><?php endif; ?></section>
<section class="panel"><div class="panel-header"><h2>Materiais publicados</h2><span class="badge badge-neutral"><?= count($materials) ?> itens</span></div><div class="resource-grid">
<?php if (!$materials): ?><p>Nenhum material cadastrado.</p><?php endif; ?>
<?php foreach ($materials as $material): ?><article class="resource-card"><div class="resource-icon">DOC</div><div><strong><?= e($material['titulo']) ?></strong><span><?= e($material['modulo_titulo']) ?></span><small><?= $material['lesson_id'] ? 'Aula: ' . e($material['aula_titulo']) : 'Disponível no módulo inteiro' ?></small></div><div class="resource-actions"><a class="btn btn-ghost" href="<?= e(url('pages/download.php?id=' . (int) $material['id'])) ?>">Baixar</a><form method="post" onsubmit="return confirm('Excluir este material?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $material['id'] ?>"><button class="btn btn-danger btn-subtle" type="submit">Excluir</button></form></div></article><?php endforeach; ?>
</div></section></main></div><?php require_once __DIR__ . '/../includes/footer.php'; ?>
