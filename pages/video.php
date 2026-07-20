<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$lessonId = (int) ($_GET['id'] ?? 0);
if ($lessonId <= 0) {
    http_response_code(404);
    exit;
}

$stmt = $pdo->prepare('SELECT video_url FROM lessons WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $lessonId]);
$videoValue = (string) ($stmt->fetchColumn() ?: '');
$filename = local_lesson_video_filename($videoValue);

if ($filename === '') {
    http_response_code(404);
    exit;
}

$filePath = lesson_video_storage_dir() . DIRECTORY_SEPARATOR . $filename;
if (!is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit;
}

$size = filesize($filePath);
if ($size === false || $size <= 0) {
    http_response_code(404);
    exit;
}

$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimeType = $extension === 'webm' ? 'video/webm' : 'video/mp4';
$start = 0;
$end = $size - 1;
$status = 200;
$rangeHeader = (string) ($_SERVER['HTTP_RANGE'] ?? '');

if ($rangeHeader !== '') {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($rangeHeader), $matches)) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }

    if ($matches[1] === '' && $matches[2] !== '') {
        $suffixLength = min((int) $matches[2], $size);
        $start = $size - $suffixLength;
    } else {
        $start = (int) $matches[1];
        if ($matches[2] !== '') {
            $end = min((int) $matches[2], $end);
        }
    }

    if ($start < 0 || $start > $end || $start >= $size) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }
    $status = 206;
}

session_write_close();
while (ob_get_level() > 0) {
    ob_end_clean();
}

$length = $end - $start + 1;
http_response_code($status);
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $length);
header('Accept-Ranges: bytes');
header('Content-Disposition: inline; filename="video-aula.' . $extension . '"');
header('Cache-Control: private, max-age=3600, no-transform');
header('X-Content-Type-Options: nosniff');
if ($status === 206) {
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD') {
    exit;
}

$handle = fopen($filePath, 'rb');
if ($handle === false) {
    http_response_code(500);
    exit;
}

fseek($handle, $start);
$remaining = $length;
$chunkSize = 1024 * 1024;

while ($remaining > 0 && !feof($handle) && connection_status() === CONNECTION_NORMAL) {
    $chunk = fread($handle, min($chunkSize, $remaining));
    if ($chunk === false || $chunk === '') {
        break;
    }
    echo $chunk;
    flush();
    $remaining -= strlen($chunk);
}

fclose($handle);
exit;
