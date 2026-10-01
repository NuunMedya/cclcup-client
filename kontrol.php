<?php
/*
 * Kurulum kontrol sayfası — cclcup.com/kontrol.php
 *
 * Site 500 hatası verirse bu sayfayı açın: sunucunun gereksinimleri karşılayıp
 * karşılamadığını ve ana sayfanın tam olarak hangi hatayı verdiğini gösterir.
 * Bu dosya bilerek en eski PHP sürümlerinde bile çalışan sözdizimiyle yazıldı.
 * Sorun çözüldükten sonra silebilirsiniz.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');

$checks = array();
$add = function ($label, $ok, $detail) use (&$checks) {
    $checks[] = array($label, $ok, $detail);
};

$add('PHP sürümü (en az 7.1)', version_compare(PHP_VERSION, '7.1.0', '>='), PHP_VERSION);
$add('json eklentisi', function_exists('json_decode'), function_exists('json_decode') ? 'var' : 'YOK');
$add('curl eklentisi', function_exists('curl_init'), function_exists('curl_init') ? 'var' : 'yok (allow_url_fopen kullanılacak)');
$add('allow_url_fopen', function_exists('curl_init') || ini_get('allow_url_fopen'), ini_get('allow_url_fopen') ? 'açık' : 'kapalı');
$add('mbstring eklentisi', true, function_exists('mb_substr') ? 'var' : 'yok (yedek fonksiyonlar kullanılacak)');
$cacheDir = __DIR__ . '/cache';
$add('cache/ klasörü yazılabilir', is_dir($cacheDir) && is_writable($cacheDir), is_dir($cacheDir) ? (is_writable($cacheDir) ? 'evet' : 'HAYIR — chmod 775 cache') : 'klasör YOK');
$add('config.php', is_file(__DIR__ . '/config.php'), is_file(__DIR__ . '/config.php') ? 'var' : 'YOK');

$apiOk = false;
$apiDetail = '';
if (version_compare(PHP_VERSION, '7.1.0', '>=') && is_file(__DIR__ . '/config.php')) {
    $cfg = require __DIR__ . '/config.php';
    $url = $cfg['api_base'] . '/api/meta/seasons?cityId=' . $cfg['city_id'] . '&leagueId=' . $cfg['league_id'];
    $body = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $body = curl_exec($ch);
        $apiDetail = $body === false ? 'curl hatası: ' . curl_error($ch) : 'HTTP ' . curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $body = @file_get_contents($url);
        $apiDetail = $body === false ? 'bağlantı kurulamadı' : 'ok';
    }
    $apiOk = $body !== false && strpos((string) $body, 'seasons') !== false;
    $apiDetail .= ' — ' . htmlspecialchars($cfg['api_base']);
}
$add('elitlig-server bağlantısı', $apiOk, $apiDetail);
?>
<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>CCL CUP — Kurulum kontrolü</title>
<style>
body{font-family:system-ui,sans-serif;max-width:760px;margin:24px auto;padding:0 16px;color:#0f172a;background:#f3f5fa}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden}
td{padding:10px 12px;border-bottom:1px solid #e3e7f0}.ok{color:#11804c;font-weight:700}.no{color:#c0262a;font-weight:700}
pre{background:#0a1230;color:#fff;padding:14px;border-radius:10px;white-space:pre-wrap;word-break:break-word}
</style></head><body>
<h1>CCL CUP — Kurulum kontrolü</h1>
<table>
<?php foreach ($checks as $c) { ?>
<tr><td><?php echo $c[0]; ?></td><td class="<?php echo $c[1] ? 'ok' : 'no'; ?>"><?php echo $c[1] ? '✔' : '✘'; ?></td><td><?php echo $c[2]; ?></td></tr>
<?php } ?>
</table>

<h2>Ana sayfa testi</h2>
<?php
if (version_compare(PHP_VERSION, '7.1.0', '<')) {
    echo '<p class="no">PHP sürümü çok eski. Hosting panelinden PHP 8.x seçin.</p>';
} else {
    ob_start();
    try {
        include __DIR__ . '/index.php';
        $html = ob_get_clean();
        echo '<p class="ok">Ana sayfa hatasız oluşturuldu (' . strlen($html) . ' bayt).</p>';
    } catch (Throwable $e) {
        ob_end_clean();
        echo '<p class="no">Ana sayfa hata verdi:</p><pre>' . htmlspecialchars(get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()) . '</pre>';
    }
}
?>
<p>Bu sayfanın ekran görüntüsünü paylaşırsanız sorunu hemen tespit edebiliriz.</p>
</body></html>
