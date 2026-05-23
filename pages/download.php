<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if (($_SESSION['role'] ?? '') !== 'aluno') {
    redirect('admin/dashboard.php');
}

$materialId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($materialId <= 0) {
    flash('error', 'Material inválido para download.');
    redirect('pages/materiais.php');
}

$stmt = $pdo->prepare('SELECT id, titulo, arquivo FROM materials WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $materialId]);
$material = $stmt->fetch();

if (!$material) {
    flash('error', 'Material não encontrado.');
    redirect('pages/materiais.php');
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
