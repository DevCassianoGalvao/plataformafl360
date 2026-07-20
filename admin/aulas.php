<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_content_manager();

$manager = current_user($pdo);
$managerId = (int) $manager['id'];
$isAdmin = $manager['role'] === 'admin';
$returnPath = content_manager_path('aulas.php');

if (!db_column_exists($pdo, 'modules', 'professor_id')) {
    flash('error', 'Execute a atualização do banco antes de gerenciar professores.');
    redirect($isAdmin ? 'admin/migracoes.php' : 'professor/dashboard.php');
}

function ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return PHP_INT_MAX;
    }

    $number = (float) $value;
    if ($number <= 0) {
        return PHP_INT_MAX;
    }
    return match (strtolower(substr($value, -1))) {
        'g' => (int) ($number * 1024 * 1024 * 1024),
        'm' => (int) ($number * 1024 * 1024),
        'k' => (int) ($number * 1024),
        default => (int) $number,
    };
}

function human_file_size(int $bytes): string
{
    if ($bytes >= 1024 * 1024 * 1024) {
        return number_format($bytes / (1024 * 1024 * 1024), 1, ',', '.') . ' GB';
    }
    return number_format($bytes / (1024 * 1024), 0, ',', '.') . ' MB';
}

function upload_error_message(int $error, string $limit): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O vídeo excede o limite do servidor (' . $limit . ').',
        UPLOAD_ERR_PARTIAL => 'O envio do vídeo foi interrompido. Tente novamente.',
        UPLOAD_ERR_NO_TMP_DIR => 'O servidor está sem pasta temporária para uploads.',
        UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar o vídeo.',
        UPLOAD_ERR_EXTENSION => 'O servidor bloqueou o formato enviado.',
        default => 'Não foi possível enviar o vídeo.',
    };
}

function store_lesson_video(array $file, int $maxBytes): string
{
    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $tmpName = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    $allowed = [
        'mp4' => ['video/mp4', 'video/x-m4v'],
        'm4v' => ['video/mp4', 'video/x-m4v'],
        'webm' => ['video/webm'],
    ];

    if ($size <= 0 || $size > $maxBytes || !isset($allowed[$extension]) || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Vídeo inválido ou acima do limite permitido.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    if (!in_array($mimeType, $allowed[$extension], true)) {
        throw new RuntimeException('Formato não permitido. Envie MP4 ou WebM.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = lesson_video_storage_dir() . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmpName, $destination)) {
        throw new RuntimeException('O servidor não conseguiu salvar o vídeo.');
    }

    return $filename;
}

$applicationMaxBytes = 500 * 1024 * 1024;
$serverMaxBytes = min(ini_bytes((string) ini_get('upload_max_filesize')), ini_bytes((string) ini_get('post_max_size')));
$maxVideoBytes = min($applicationMaxBytes, $serverMaxBytes);
$maxVideoLabel = human_file_size($maxVideoBytes);

if (is_post() && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'O envio excedeu o limite do servidor (' . $maxVideoLabel . ').');
    redirect($returnPath);
}

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'create' || $action === 'update') {
        $moduleId = (int) ($_POST['module_id'] ?? 0);
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        $youtubeUrl = trim((string) ($_POST['video_url'] ?? ''));
        $ordem = max(0, (int) ($_POST['ordem'] ?? 0));
        $existingVideo = '';

        if (!can_manage_module($pdo, $moduleId, $manager) || $titulo === '') {
            flash('error', 'Selecione um módulo permitido e informe o título da aula.');
            redirect($returnPath);
        }

        if ($action === 'update') {
            $find = $pdo->prepare('SELECT module_id, video_url FROM lessons WHERE id = :id');
            $find->execute([':id' => $id]);
            $existingLesson = $find->fetch();
            if (!$existingLesson || !can_manage_module($pdo, (int) $existingLesson['module_id'], $manager)) {
                flash('error', 'Você não tem permissão para alterar esta aula.');
                redirect($returnPath);
            }
            $existingVideo = (string) $existingLesson['video_url'];
        }

        $file = $_FILES['video_file'] ?? null;
        $uploadError = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        $hasUpload = $uploadError !== UPLOAD_ERR_NO_FILE;
        if ($hasUpload && $uploadError !== UPLOAD_ERR_OK) {
            flash('error', upload_error_message($uploadError, $maxVideoLabel));
            redirect($returnPath);
        }

        if (!$hasUpload && $youtubeUrl === '' && $existingVideo === '') {
            flash('error', 'Informe uma URL do YouTube ou envie um arquivo de vídeo.');
            redirect($returnPath);
        }
        if (!$hasUpload && $youtubeUrl !== '' && get_youtube_embed_url($youtubeUrl) === '') {
            flash('error', 'Informe uma URL válida do YouTube.');
            redirect($returnPath);
        }

        $newFilename = '';
        $lessonSaved = false;
        $videoValue = $youtubeUrl !== '' ? $youtubeUrl : $existingVideo;
        try {
            if ($hasUpload && is_array($file)) {
                $newFilename = store_lesson_video($file, $maxVideoBytes);
                $videoValue = 'local:' . $newFilename;
            }

            if ($action === 'update') {
                $stmt = $pdo->prepare(
                    'UPDATE lessons SET module_id = :module_id, titulo = :titulo, descricao = :descricao,
                     video_url = :video_url, ordem = :ordem WHERE id = :id'
                );
                $stmt->execute([':module_id' => $moduleId, ':titulo' => $titulo, ':descricao' => $descricao, ':video_url' => $videoValue, ':ordem' => $ordem, ':id' => $id]);
                $lessonSaved = true;
                if ($videoValue !== $existingVideo) {
                    delete_local_lesson_video($existingVideo);
                }
                flash('success', 'Aula atualizada.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO lessons (module_id, titulo, descricao, video_url, ordem)
                     VALUES (:module_id, :titulo, :descricao, :video_url, :ordem)'
                );
                $stmt->execute([':module_id' => $moduleId, ':titulo' => $titulo, ':descricao' => $descricao, ':video_url' => $videoValue, ':ordem' => $ordem]);
                $lessonSaved = true;
                $lessonId = (int) $pdo->lastInsertId();
                try {
                    create_notification($pdo, 'aula', 'Nova aula liberada', 'A aula "' . $titulo . '" já está disponível.', 'pages/aula.php?id=' . $lessonId);
                } catch (Throwable $notificationException) {
                    error_log('FL360: aula criada, mas notificação falhou. Código: ' . $notificationException->getCode());
                }
                flash('success', 'Aula criada com sucesso.');
            }
        } catch (Throwable $exception) {
            if (!$lessonSaved && $newFilename !== '') {
                delete_local_lesson_video('local:' . $newFilename);
            }
            error_log('FL360: falha ao salvar aula com vídeo. Código: ' . $exception->getCode());
            flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Não foi possível salvar a aula.');
        }
        redirect($returnPath);
    }

    if ($action === 'delete') {
        $find = $pdo->prepare('SELECT module_id, video_url FROM lessons WHERE id = :id');
        $find->execute([':id' => $id]);
        $lessonToDelete = $find->fetch();
        if (!$lessonToDelete || !can_manage_module($pdo, (int) $lessonToDelete['module_id'], $manager)) {
            flash('error', 'Você não tem permissão para excluir esta aula.');
            redirect($returnPath);
        }
        $stmt = $pdo->prepare('DELETE FROM lessons WHERE id = :id');
        $stmt->execute([':id' => $id]);
        delete_local_lesson_video((string) $lessonToDelete['video_url']);
        flash('success', 'Aula excluída. O vídeo interno vinculado foi removido, quando existente.');
        redirect($returnPath);
    }
}

if ($isAdmin) {
    $modules = $pdo->query('SELECT id, titulo FROM modules ORDER BY ordem, id')->fetchAll();
    $lessons = $pdo->query(
        'SELECT l.id, l.module_id, l.titulo, l.descricao, l.video_url, l.ordem, m.titulo AS modulo_titulo
         FROM lessons l INNER JOIN modules m ON m.id = l.module_id ORDER BY m.ordem, l.ordem, l.id'
    )->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT m.id, m.titulo FROM modules m INNER JOIN module_professors mp ON mp.module_id = m.id WHERE mp.user_id = :id ORDER BY m.ordem, m.id');
    $stmt->execute([':id' => $managerId]);
    $modules = $stmt->fetchAll();
    $stmt = $pdo->prepare(
        'SELECT l.id, l.module_id, l.titulo, l.descricao, l.video_url, l.ordem, m.titulo AS modulo_titulo
         FROM lessons l INNER JOIN modules m ON m.id = l.module_id
         INNER JOIN module_professors mp ON mp.module_id = m.id
         WHERE mp.user_id = :id ORDER BY m.ordem, l.ordem, l.id'
    );
    $stmt->execute([':id' => $managerId]);
    $lessons = $stmt->fetchAll();
}

$active_page = 'aulas';
$page_title = 'Aulas';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content-area">
        <section class="page-heading"><div><span class="eyebrow">Gestão pedagógica</span><h1>Aulas</h1><p>Publique vídeos do YouTube ou arquivos internos protegidos.</p></div></section>
        <section class="panel">
            <div class="panel-header"><h2>Nova aula</h2></div>
            <?php if (!$modules): ?><p>Crie ou atribua um módulo antes de publicar aulas.</p><?php else: ?>
            <form method="post" enctype="multipart/form-data" class="form-grid form-grid-columns">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
                <label>Módulo<select name="module_id" required><option value="">Selecione</option><?php foreach ($modules as $module): ?><option value="<?= (int) $module['id'] ?>"><?= e($module['titulo']) ?></option><?php endforeach; ?></select></label>
                <label>Ordem<input type="number" name="ordem" value="1" min="0"></label>
                <label class="field-wide">Título<input type="text" name="titulo" maxlength="180" required></label>
                <label class="field-wide">Descrição<textarea name="descricao" rows="3"></textarea></label>
                <div class="field-wide video-source-box">
                    <strong>Vídeo da aula</strong>
                    <p>Escolha uma opção. Se preencher ambas, o arquivo enviado terá prioridade.</p>
                    <label>URL do YouTube<input type="url" name="video_url" placeholder="https://www.youtube.com/watch?v=..."></label>
                    <span class="video-source-divider">ou</span>
                    <label>Arquivo interno<input type="file" name="video_file" accept="video/mp4,video/webm,.m4v"><small>MP4, M4V ou WebM. Limite atual do servidor: <?= e($maxVideoLabel) ?>.</small></label>
                </div>
                <div class="field-wide"><button class="btn btn-primary" type="submit">Publicar aula</button></div>
            </form><?php endif; ?>
        </section>
        <section class="panel">
            <div class="panel-header"><h2>Aulas publicadas</h2><span class="badge badge-neutral"><?= count($lessons) ?> itens</span></div>
            <div class="management-list">
            <?php if (!$lessons): ?><p>Nenhuma aula cadastrada.</p><?php endif; ?>
            <?php foreach ($lessons as $lesson): $hasLocalVideo = is_local_lesson_video((string) $lesson['video_url']); ?>
                <article class="management-card">
                    <form method="post" enctype="multipart/form-data" class="management-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $lesson['id'] ?>">
                        <div class="management-card-head"><span class="badge badge-neutral"><?= e($lesson['modulo_titulo']) ?></span><span>Ordem <?= (int) $lesson['ordem'] ?></span></div>
                        <label>Título<input type="text" name="titulo" value="<?= e($lesson['titulo']) ?>" required></label>
                        <label>Descrição<textarea name="descricao" rows="2"><?= e($lesson['descricao']) ?></textarea></label>
                        <div class="video-source-box compact">
                            <?php if ($hasLocalVideo): ?><div class="video-current"><span class="badge badge-success">Vídeo interno</span><small>Arquivo protegido vinculado à aula.</small></div><?php endif; ?>
                            <label>URL do YouTube<input type="url" name="video_url" value="<?= $hasLocalVideo ? '' : e($lesson['video_url']) ?>" placeholder="Cole para usar ou substituir pelo YouTube"></label>
                            <label><?= $hasLocalVideo ? 'Substituir arquivo interno' : 'Enviar arquivo interno' ?><input type="file" name="video_file" accept="video/mp4,video/webm,.m4v"><small>MP4, M4V ou WebM. Até <?= e($maxVideoLabel) ?>.</small></label>
                        </div>
                        <div class="inline-form wrap"><label>Módulo<select name="module_id"><?php foreach ($modules as $module): ?><option value="<?= (int) $module['id'] ?>" <?= (int) $lesson['module_id'] === (int) $module['id'] ? 'selected' : '' ?>><?= e($module['titulo']) ?></option><?php endforeach; ?></select></label><label>Ordem<input type="number" name="ordem" value="<?= (int) $lesson['ordem'] ?>" min="0"></label></div>
                        <button class="btn btn-primary" type="submit">Salvar alterações</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Excluir esta aula e seu vídeo interno?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $lesson['id'] ?>"><button class="btn btn-danger btn-subtle" type="submit">Excluir aula</button></form>
                </article>
            <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
