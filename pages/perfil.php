<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if (($_SESSION['role'] ?? '') !== 'aluno') {
    redirect('admin/dashboard.php');
}

function create_image_from_upload(string $tmpName, string $mimeType)
{
    return match ($mimeType) {
        'image/jpeg' => imagecreatefromjpeg($tmpName),
        'image/png' => imagecreatefrompng($tmpName),
        'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($tmpName) : false,
        default => false,
    };
}

function save_cropped_avatar(string $tmpName, string $target, string $mimeType): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return move_uploaded_file($tmpName, $target);
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false) {
        return false;
    }

    [$srcWidth, $srcHeight] = $imageInfo;
    $source = create_image_from_upload($tmpName, $mimeType);
    if (!$source) {
        return false;
    }

    $cropSize = min($srcWidth, $srcHeight);
    $cropX = (int) floor(($srcWidth - $cropSize) / 2);
    $cropY = (int) floor(($srcHeight - $cropSize) / 2);

    $destSize = 320;
    $avatar = imagecreatetruecolor($destSize, $destSize);
    imagealphablending($avatar, true);
    imagesavealpha($avatar, true);

    $ok = imagecopyresampled(
        $avatar,
        $source,
        0,
        0,
        $cropX,
        $cropY,
        $destSize,
        $destSize,
        $cropSize,
        $cropSize
    );

    if (!$ok) {
        imagedestroy($avatar);
        imagedestroy($source);
        return false;
    }

    $saved = imagejpeg($avatar, $target, 88);

    imagedestroy($avatar);
    imagedestroy($source);

    return (bool) $saved;
}

function remove_profile_photo(PDO $pdo, int $userId): void
{
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
}

$user = current_user($pdo);
$userId = (int) $user['id'];

$allowedImageExt = ['jpg', 'jpeg', 'png', 'webp'];
$allowedImageMime = ['image/jpeg', 'image/png', 'image/webp'];
$maxImageBytes = 3 * 1024 * 1024;

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? 'change_password');

    if ($action === 'change_password') {
        $senhaAtual = (string) ($_POST['senha_atual'] ?? '');
        $novaSenha = (string) ($_POST['nova_senha'] ?? '');
        $confirmacao = (string) ($_POST['confirmacao_senha'] ?? '');

        if (strlen($novaSenha) < 5) {
            flash('error', 'A nova senha precisa ter pelo menos 5 caracteres.');
            redirect('pages/perfil.php');
        }

        if ($novaSenha !== $confirmacao) {
            flash('error', 'A confirmação da senha não confere.');
            redirect('pages/perfil.php');
        }

        $stmt = $pdo->prepare('SELECT senha FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $hashAtual = (string) $stmt->fetchColumn();

        if (!password_verify($senhaAtual, $hashAtual)) {
            flash('error', 'Senha atual incorreta.');
            redirect('pages/perfil.php');
        }

        $update = $pdo->prepare('UPDATE users SET senha = :senha WHERE id = :id');
        $update->execute([
            ':senha' => password_hash($novaSenha, PASSWORD_DEFAULT),
            ':id' => $userId,
        ]);

        flash('success', 'Senha alterada com sucesso.');
        redirect('pages/perfil.php');
    }

    if ($action === 'delete_photo') {
        remove_profile_photo($pdo, $userId);
        flash('success', 'Foto de perfil removida.');
        redirect('pages/perfil.php');
    }

    if ($action === 'upload_photo') {
        if (!isset($_FILES['foto']) || !is_array($_FILES['foto'])) {
            flash('error', 'Selecione uma imagem para enviar.');
            redirect('pages/perfil.php');
        }

        $file = $_FILES['foto'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Falha no upload da foto.');
            redirect('pages/perfil.php');
        }

        if ((int) $file['size'] > $maxImageBytes) {
            flash('error', 'A foto deve ter no máximo 3MB.');
            redirect('pages/perfil.php');
        }

        $originalName = (string) $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedImageExt, true)) {
            flash('error', 'Formato inválido. Use JPG, PNG ou WEBP.');
            redirect('pages/perfil.php');
        }

        $tmpName = (string) $file['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!in_array($mimeType, $allowedImageMime, true)) {
            flash('error', 'Arquivo bloqueado pela validação de segurança.');
            redirect('pages/perfil.php');
        }

        $uploadDir = ensure_upload_dir();

        $newFile = 'perfil_' . bin2hex(random_bytes(16)) . '.jpg';
        $target = $uploadDir . DIRECTORY_SEPARATOR . $newFile;

        $processed = save_cropped_avatar($tmpName, $target, $mimeType);
        if (!$processed) {
            $newFile = 'perfil_' . bin2hex(random_bytes(16)) . '.' . $extension;
            $target = $uploadDir . DIRECTORY_SEPARATOR . $newFile;

            if (!move_uploaded_file($tmpName, $target)) {
                flash('error', 'Não foi possível processar sua foto.');
                redirect('pages/perfil.php');
            }
        }

        $stmtOld = $pdo->prepare('SELECT foto_perfil FROM users WHERE id = :id LIMIT 1');
        $stmtOld->execute([':id' => $userId]);
        $oldPhoto = (string) ($stmtOld->fetchColumn() ?: '');

        $update = $pdo->prepare('UPDATE users SET foto_perfil = :foto WHERE id = :id');
        $update->execute([':foto' => $newFile, ':id' => $userId]);

        if ($oldPhoto !== '') {
            $oldPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($oldPhoto);
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        flash('success', 'Foto de perfil atualizada!');
        redirect('pages/perfil.php');
    }
}

$user = current_user($pdo);
$photoUrl = !empty($user['foto_perfil']) ? url('uploads/' . $user['foto_perfil']) : null;

$active_page = 'perfil';
$page_title = 'Perfil';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content-area">
        <section class="panel">
            <div class="panel-header">
                <h1>Meu perfil</h1>
            </div>

            <div class="profile-grid">
                <article class="stat-card">
                    <h3>Foto</h3>
                    <div class="profile-avatar-large">
                        <?php if ($photoUrl): ?>
                            <img src="<?= e($photoUrl) ?>" alt="Foto de perfil">
                        <?php else: ?>
                            <span><?= e(strtoupper(substr((string) $user['nome'], 0, 1))) ?></span>
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
                <article class="stat-card">
                    <h3>Tipo de conta</h3>
                    <strong><?= e(strtoupper($user['role'])) ?></strong>
                </article>
            </div>

            <hr>

            <h2>Foto de perfil</h2>
            <form method="post" enctype="multipart/form-data" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="upload_photo">

                <label for="foto">Enviar ou trocar foto</label>
                <input id="foto" type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" required>
                <small>Formatos permitidos: JPG, PNG e WEBP (máx. 3MB). A foto será recortada automaticamente.</small>

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

            <hr>

            <h2>Alterar senha</h2>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="change_password">

                <label for="senha_atual">Senha atual</label>
                <input id="senha_atual" type="password" name="senha_atual" required>

                <label for="nova_senha">Nova senha</label>
                <input id="nova_senha" type="password" name="nova_senha" required>

                <label for="confirmacao_senha">Confirmar nova senha</label>
                <input id="confirmacao_senha" type="password" name="confirmacao_senha" required>

                <button type="submit" class="btn btn-primary">Salvar nova senha</button>
            </form>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
