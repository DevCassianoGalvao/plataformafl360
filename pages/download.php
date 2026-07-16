<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$materialId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($materialId <= 0) {
    flash('error', 'Material inválido para download.');
    redirect('pages/materiais.php');
}

$downloadSql = db_column_exists($pdo, 'materials', 'module_id')
    ? 'SELECT mat.id, mat.titulo, mat.arquivo, COALESCE(mat.module_id, l.module_id) AS module_id
       FROM materials mat LEFT JOIN lessons l ON l.id = mat.lesson_id WHERE mat.id = :id LIMIT 1'
    : 'SELECT mat.id, mat.titulo, mat.arquivo, l.module_id
       FROM materials mat INNER JOIN lessons l ON l.id = mat.lesson_id WHERE mat.id = :id LIMIT 1';
$stmt = $pdo->prepare($downloadSql);
$stmt->execute([':id' => $materialId]);
$material = $stmt->fetch();

if (!$material) {
    flash('error', 'Material não encontrado.');
    redirect('pages/materiais.php');
}

$user = current_user($pdo);
if (($user['role'] ?? '') === 'professor' && !can_manage_module($pdo, (int) $material['module_id'], $user)) {
    http_response_code(403);
    exit('Você não tem permissão para baixar este material.');
}

$storedFile = basename((string) $material['arquivo']);
$filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $storedFile;
if (!is_file($filePath) || !is_readable($filePath)) {
    flash('error', 'Arquivo indisponível no servidor.');
    redirect('pages/materiais.php');
}

$extension = strtolower(pathinfo($storedFile, PATHINFO_EXTENSION));
$baseName = preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', (string) $material['titulo']);
$baseName = trim((string) $baseName, '-_');
if ($baseName === '') {
    $baseName = 'material-' . $materialId;
}
$downloadName = $baseName . ($extension !== '' ? '.' . $extension : '');

$asciiName = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName);
if ($asciiName === '') {
    $asciiName = 'material-' . $materialId . ($extension !== '' ? '.' . $extension : '');
}

$mimeType = 'application/octet-stream';
$finfo = finfo_open(FILEINFO_MIME_TYPE);
if ($finfo) {
    $detected = (string) finfo_file($finfo, $filePath);
    if ($detected !== '') {
        $mimeType = $detected;
    }
    finfo_close($finfo);
}

if (ob_get_level() > 0) {
    ob_end_clean();
}

header('X-Content-Type-Options: nosniff');
header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('Content-Length: ' . (string) filesize($filePath));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit;
