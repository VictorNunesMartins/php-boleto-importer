<?php
session_start();

$token = $_GET['token'] ?? '';
$info = $_SESSION['downloads'][$token] ?? null;

if (!$info || !is_file($info['path'])) {
    http_response_code(404);
    exit('Arquivo expirado ou não encontrado.');
}

$path = $info['path'];
$nome = $info['name'];
$mime = $info['mime'];

while (ob_get_level() > 0) ob_end_clean();

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $nome . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($path);

@unlink($path);
unset($_SESSION['downloads'][$token]);
