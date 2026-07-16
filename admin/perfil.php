<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_content_manager();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$profilePath = content_manager_path('perfil.php');
$maxImageBytes = 3 * 1024 * 1024;

function admin_create_image(string $tmpName, string $mimeType)
{
    return match ($mimeType) {
        'image/jpeg' => imagecreatefromjpeg($tmpName),
        'image/png'  => imagecreatefrompng($tmpName),
        'image/webp' => imagecreatefromwebp($tmpName),
        default      => false,
    };
}

function admin_save_avatar(string $tmpName, string $target, string $mimeType): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return move_uploaded_file($tmpName, $target);
    }

    $imageInfo = @getimagesize($tmpName);
    if (!$imageInfo) {
        return move_uploaded_file($tmpName, $target);
    }

    [$srcWidth, $srcHeight] = $imageInfo;
    $source = admin_create_image($tmpName, $mimeType);
    if (!$source) {
        return move_uploaded_file($tmpName, $target);
    }

    $side   = min($srcWidth, $srcHeight);
    $startX = (int) (($srcWidth  - $side) / 2);
    $startY = (int) (($srcHeight - $side) / 2);
    $destSize = 320;

    $avatar = imagecreatetruecolor($destSize, $destSize);
    imagealphablending($avatar, true);
    imagesavealpha($avatar, true);

    $ok = imagecopyresampled($avatar, $source, 0, 0, $startX, $startY, $destSize, $destSize, $side, $side);

    if (!$ok) {
        imagedestroy($avatar);
        imagedestroy($source);
        return move_uploaded_file($tmpName, $target);
    }

    $saved = imagejpeg($avatar, $target, 88);
    imagedestroy($avatar);
    imagedestroy($source);
    return $saved;
}

function admin_ensure_upload_dir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete_photo') {
        $stmt = $pdo->prepare('SELECT foto_perfil FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $oldPhoto = (string) ($stmt->fetchColumn() ?: '');

        $pdo->prepare('UPDATE users SET foto_perfil = NULL WHERE id = :id')->execute([':id' => $userId]);

        if ($oldPhoto !== '') {
            $oldPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($oldPhoto);
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        flash('success', 'Foto removida.');
        redirect($profilePath);
    }

    if ($action === 'upload_photo') {
        if (!isset($_FILES['foto']) || !is_array($_FILES['foto'])) {
            flash('error', 'Selecione uma imagem para enviar.');
            redirect($profilePath);
        }

        $file = $_FILES['foto'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Falha no upload da foto.');
            redirect($profilePath);
        }

        if ((int) $file['size'] > $maxImageBytes) {
            flash('error', 'A foto deve ter no máximo 3MB.');
            redirect($profilePath);
        }

        $tmpName  = (string) $file['tmp_name'];
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
        if ($finfo) finfo_close($finfo);

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $allowed, true)) {
            flash('error', 'Formato não permitido. Use JPG, PNG ou WEBP.');
            redirect($profilePath);
        }

        $extension = match ($mimeType) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg',
        };

        $uploadDir = admin_ensure_upload_dir();
        $newFile   = 'perfil_' . bin2hex(random_bytes(16)) . '.jpg';
        $target    = $uploadDir . DIRECTORY_SEPARATOR . $newFile;

        $processed = admin_save_avatar($tmpName, $target, $mimeType);
        if (!$processed) {
            $newFile = 'perfil_' . bin2hex(random_bytes(16)) . '.' . $extension;
            $target  = $uploadDir . DIRECTORY_SEPARATOR . $newFile;
            if (!move_uploaded_file($tmpName, $target)) {
                flash('error', 'Não foi possível processar sua foto.');
                redirect($profilePath);
            }
        }

        $stmtOld = $pdo->prepare('SELECT foto_perfil FROM users WHERE id = :id LIMIT 1');
        $stmtOld->execute([':id' => $userId]);
        $oldPhoto = (string) ($stmtOld->fetchColumn() ?: '');

        $pdo->prepare('UPDATE users SET foto_perfil = :foto WHERE id = :id')
            ->execute([':foto' => $newFile, ':id' => $userId]);

        if ($oldPhoto !== '') {
            $oldPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($oldPhoto);
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        flash('success', 'Foto atualizada com sucesso!');
        redirect($profilePath);
    }
}

$user     = current_user($pdo);
$photoUrl = !empty($user['foto_perfil']) ? url('uploads/' . $user['foto_perfil']) : null;

$active_page = 'perfil';
$page_title  = 'Meu Perfil';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="app-layout">
    <div class="content-area">

        <div class="hero-card">
            <h1>Meu Perfil</h1>
            <p>Gerencie sua foto de perfil de administrador.</p>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2>Foto de perfil</h2>
            </div>

            <div class="profile-grid">
                <article class="stat-card">
                    <h3>Foto atual</h3>
                    <div class="profile-avatar-large">
                        <?php if ($photoUrl): ?>
                            <img src="<?= e($photoUrl) ?>" alt="Foto de perfil">
                        <?php else: ?>
                            <span><?= e(strtoupper(substr(trim((string) $user['nome']), 0, 1))) ?></span>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="stat-card">
                    <h3>Nome</h3>
                    <strong><?= e($user['nome']) ?></strong>
                </article>

                <article class="stat-card">
                    <h3>E-mail</h3>
                    <strong><?= e($user['email']) ?></strong>
                </article>
            </div>

            <hr>

            <form method="post" enctype="multipart/form-data" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="upload_photo">

                <label for="foto">Enviar ou trocar foto</label>
                <input id="foto" type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" required>
                <small>Formatos: JPG, PNG, WEBP (máx. 3MB). Recortada automaticamente para quadrado.</small>

                <div class="inline-form wrap">
                    <button type="submit" class="btn btn-primary">Salvar foto</button>
                </div>
            </form>

            <?php if ($photoUrl): ?>
                <form method="post" class="inline-form" onsubmit="return confirm('Remover foto de perfil?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_photo">
                    <button type="submit" class="btn btn-danger">Excluir foto</button>
                </form>
            <?php endif; ?>
        </div>

    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
