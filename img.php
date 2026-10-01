<?php
/**
 * Görsel aracısı: logo ve fotoğrafları kendi alan adımız üzerinden sunar.
 * Yalnızca config.php'deki medya sunucusundaki dosyalara izin verilir;
 * indirilen görseller cache/img altında saklanır.
 */
$cfg = require __DIR__ . '/config.php';

$path = isset($_GET['p']) && is_string($_GET['p']) ? $_GET['p'] : '';
if ($path === '' || strlen($path) > 300 || strpos($path, '..') !== false || !preg_match('#^[A-Za-z0-9._\-/%() ]+$#', $path)) {
    http_response_code(404);
    exit;
}

$width = isset($_GET['w']) && is_string($_GET['w']) ? (int) $_GET['w'] : 0;
if (!in_array($width, [0, 160, 320, 640, 1280, 1920], true)) {
    $width = 0;
}

$dir = rtrim($cfg['cache_dir'], '/') . '/img';
$key = sha1($path);
$file = $dir . '/' . $key . '.bin';
$meta = $dir . '/' . $key . '.type';

$serve = static function (string $file, string $type) {
    $etag = '"' . md5_file($file) . '"';
    header('Content-Type: ' . $type);
    header('Cache-Control: public, max-age=2592000, immutable');
    header('ETag: ' . $etag);
    if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        exit;
    }
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
};

/**
 * GD varsa görseli istenen genişliğe küçültür (oran korunur, saydamlık
 * korunur) ve ayrı bir önbellek dosyasına yazar.
 */
$resized = static function (string $file, string $type, int $width) use ($dir, $key) {
    if ($width <= 0 || !function_exists('imagecreatetruecolor')) {
        return null;
    }
    $out = $dir . '/' . $key . '_w' . $width . '.bin';
    if (is_file($out)) {
        return $out;
    }
    $loaders = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'];
    if (!isset($loaders[$type]) || !function_exists($loaders[$type])) {
        return null;
    }
    $info = @getimagesize($file);
    if (!$info || $info[0] <= $width || $info[0] * $info[1] > 40000000) {
        return null;
    }
    $src = @$loaders[$type]($file);
    if (!$src) {
        return null;
    }
    $h = (int) round($info[1] * $width / $info[0]);
    $dst = imagecreatetruecolor($width, $h);
    if ($type !== 'image/jpeg') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $h, $info[0], $info[1]);
    $tmp = $out . '.' . uniqid('', true) . '.tmp';
    $ok = $type === 'image/jpeg' ? imagejpeg($dst, $tmp, 82) : ($type === 'image/png' ? imagepng($dst, $tmp, 7) : imagewebp($dst, $tmp, 82));
    imagedestroy($src);
    imagedestroy($dst);
    if ($ok && @rename($tmp, $out)) {
        return $out;
    }
    @unlink($tmp);
    return null;
};

if (is_file($file) && is_file($meta)) {
    $type = (string) file_get_contents($meta);
    $serve($resized($file, $type, $width) ?: $file, $type);
}

$url = rtrim($cfg['media_base'], '/') . '/' . str_replace('%2F', '/', rawurlencode(rawurldecode($path)));
$body = false;
$type = '';
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_MAXFILESIZE => 15 * 1024 * 1024,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($status !== 200) {
        $body = false;
    }
} elseif (ini_get('allow_url_fopen')) {
    $body = @file_get_contents($url);
    foreach ($http_response_header ?? [] as $h) {
        if (stripos($h, 'Content-Type:') === 0) {
            $type = trim(substr($h, 13));
        }
    }
}

if ($body === false || $body === '' || strpos(strtolower($type), 'image/') !== 0) {
    // Uzantıdan tür tahmini (bazı sunucular genel tür döndürür)
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $guess = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
    if ($body !== false && $body !== '' && isset($guess[$ext])) {
        $type = $guess[$ext];
    } else {
        http_response_code(404);
        exit;
    }
}
$type = strtok($type, ';');

if ((is_dir($dir) || @mkdir($dir, 0775, true)) && is_writable($dir)) {
    $tmp = $file . '.' . uniqid('', true) . '.tmp';
    if (@file_put_contents($tmp, $body) !== false) {
        @rename($tmp, $file);
        @file_put_contents($meta, $type);
        $serve($resized($file, $type, $width) ?: $file, $type);
    }
}

header('Content-Type: ' . $type);
header('Cache-Control: public, max-age=86400');
echo $body;
