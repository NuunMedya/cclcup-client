<?php
/** Görünüm yardımcıları. */

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $page, array $params = []): string
{
    $params = array_filter($params, static function ($v) {
        return $v !== null && $v !== '';
    });
    return $page . ($params ? '?' . http_build_query($params) : '');
}

function team_url(int $id): string   { return url('takim.php', ['id' => $id]); }
function player_url(int $id): string { return url('oyuncu.php', ['id' => $id]); }
function match_url(int $id): string  { return url('mac.php', ['id' => $id]); }

const TR_MONTHS = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
const TR_MONTHS_SHORT = ['', 'Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
const TR_DAYS = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];

/** API tarihleri "2026-09-27T00:00:00.000Z" biçiminde; yalnızca gün kısmı anlamlı. */
function match_ts(array $m): ?int
{
    $date = substr((string) ($m['date'] ?? ''), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }
    return strtotime($date . ' 12:00:00') ?: null;
}

function fmt_date(array $m, bool $withDay = false): string
{
    $ts = match_ts($m);
    if (!$ts) {
        return 'Tarih belirlenecek';
    }
    $out = (int) date('j', $ts) . ' ' . TR_MONTHS[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    return $withDay ? $out . ', ' . TR_DAYS[(int) date('w', $ts)] : $out;
}

function fmt_date_short(array $m): array
{
    $ts = match_ts($m);
    if (!$ts) {
        return ['day' => '–', 'month' => '', 'weekday' => ''];
    }
    return [
        'day' => date('d', $ts),
        'month' => TR_MONTHS_SHORT[(int) date('n', $ts)],
        'weekday' => TR_DAYS[(int) date('w', $ts)],
    ];
}

function fmt_time(array $m): string
{
    $t = substr((string) ($m['time'] ?? ''), 0, 5);
    return preg_match('/^\d{2}:\d{2}$/', $t) && $t !== '00:00' ? $t : '';
}

function initials(string $name): string
{
    $words = preg_split('/\s+/u', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($words, 0, 2) as $w) {
        $out .= mb_substr($w, 0, 1, 'UTF-8');
    }
    return mb_strtoupper($out ?: '?', 'UTF-8');
}

/** Takım logosu ya da baş harflerden oluşan rozet. */
function team_badge(?string $logo, string $name, string $size = 'md'): string
{
    if ($logo) {
        return '<span class="badge badge-' . e($size) . '"><img src="' . e($logo) . '" alt="' . e($name) . ' logosu" loading="lazy"></span>';
    }
    return '<span class="badge badge-' . e($size) . ' badge-initials" aria-hidden="true">' . e(initials($name)) . '</span>';
}

function player_avatar(?string $img, string $name, string $size = 'md'): string
{
    if ($img) {
        return '<span class="avatar avatar-' . e($size) . '"><img src="' . e($img) . '" alt="' . e($name) . '" loading="lazy"></span>';
    }
    return '<span class="avatar avatar-' . e($size) . ' avatar-initials" aria-hidden="true">' . e(initials($name)) . '</span>';
}

function team_logo(int $id): ?string
{
    return ccl_team_map()[$id]['logo'] ?? null;
}

/** "W D L" dizisini form rozetlerine çevirir. */
function form_badges(?string $last5): string
{
    $map = ['W' => ['G', 'win'], 'D' => ['B', 'draw'], 'L' => ['M', 'loss']];
    $out = '';
    foreach (str_split(strtoupper(preg_replace('/[^WDL]/i', '', (string) $last5))) as $c) {
        if (isset($map[$c])) {
            $out .= '<span class="form form-' . $map[$c][1] . '">' . $map[$c][0] . '</span>';
        }
    }
    return $out ?: '<span class="muted">–</span>';
}

/** Maç olay kodlarının Türkçe karşılıkları ve ikon sınıfları. */
function event_info(string $code): array
{
    $c = strtoupper(trim($code));
    $goals = [
        'GOAL' => 'Gol', 'GOAL_RIGHT_FOOT' => 'Gol (sağ ayak)', 'GOAL_LEFT_FOOT' => 'Gol (sol ayak)',
        'GOAL_HEADER' => 'Gol (kafa)', 'GOAL_PENALTY' => 'Gol (penaltı)', 'PEN_GOAL' => 'Gol (penaltı)',
        'GOAL_FREEKICK' => 'Gol (serbest vuruş)', 'GOAL_LONG_RIGHT' => 'Gol (uzaktan, sağ)',
        'GOAL_LONG_LEFT' => 'Gol (uzaktan, sol)', 'GOL' => 'Gol',
    ];
    if (isset($goals[$c])) {
        return ['label' => $goals[$c], 'type' => 'goal', 'key' => true];
    }
    $map = [
        'OG' => ['Kendi kalesine', 'own-goal', true], 'OWN_GOAL' => ['Kendi kalesine', 'own-goal', true],
        'KENDI_KALESINE' => ['Kendi kalesine', 'own-goal', true],
        'YELLOW' => ['Sarı kart', 'yellow', true], 'YELLOW_CARD' => ['Sarı kart', 'yellow', true], 'SARI_KART' => ['Sarı kart', 'yellow', true],
        'RED' => ['Kırmızı kart', 'red', true], 'RED_CARD' => ['Kırmızı kart', 'red', true], 'KIRMIZI_KART' => ['Kırmızı kart', 'red', true],
        'SUBSTITUTION' => ['Oyuncu değişikliği', 'sub', true],
        'ASSIST' => ['Asist', 'assist', false], 'SAVE' => ['Kurtarış', 'save', false],
        'CHANCE_CREATED' => ['Pozisyon üretti', 'chance', false], 'CRITICAL_BLOCK' => ['Kritik blok', 'block', false],
        'AERIAL_WON' => ['Hava topu', 'aerial', false], 'DUEL_WON' => ['İkili mücadele', 'duel', false],
        'FOUL' => ['Faul', 'foul', false], 'INJURY' => ['Sakatlık', 'injury', false],
    ];
    if (isset($map[$c])) {
        return ['label' => $map[$c][0], 'type' => $map[$c][1], 'key' => $map[$c][2]];
    }
    return ['label' => ucfirst(strtolower(str_replace('_', ' ', $c))), 'type' => 'other', 'key' => false];
}

function event_icon(string $type): string
{
    $icons = [
        'goal' => '⚽', 'own-goal' => '⚽', 'yellow' => '', 'red' => '', 'sub' => '⇄',
        'assist' => '👟', 'save' => '🧤', 'chance' => '✦', 'block' => '⛨', 'aerial' => '↑',
        'duel' => '⚔', 'foul' => '!', 'injury' => '✚', 'other' => '•',
    ];
    if ($type === 'yellow' || $type === 'red') {
        return '<span class="card-icon card-' . $type . '" aria-hidden="true"></span>';
    }
    return '<span class="ev-icon ev-' . e($type) . '" aria-hidden="true">' . ($icons[$type] ?? '•') . '</span>';
}

function position_short(?string $pos): string
{
    $p = mb_strtolower(trim((string) $pos), 'UTF-8');
    if ($p === '') return '';
    if (strpos($p, 'kaleci') !== false) return 'KL';
    if (strpos($p, 'defans') !== false) return 'DF';
    if (strpos($p, 'orta') !== false) return 'OS';
    if (strpos($p, 'forvet') !== false || strpos($p, 'hücum') !== false) return 'FV';
    return mb_strtoupper(mb_substr($p, 0, 2, 'UTF-8'), 'UTF-8');
}

function num($v, int $decimals = 0): string
{
    if ($v === null || $v === '') return '–';
    return number_format((float) $v, $decimals, ',', '.');
}

/** Türkçe alfabeye göre karşılaştırma (intl yoksa basit karşılaştırma). */
function tr_compare(string $a, string $b): int
{
    static $collator = null;
    if ($collator === null) {
        $collator = class_exists('Collator') ? new Collator('tr_TR') : false;
    }
    return $collator ? (int) $collator->compare($a, $b) : strcmp(mb_strtolower($a, 'UTF-8'), mb_strtolower($b, 'UTF-8'));
}

/** Takım listelerini ada göre sıralamak için (uasort/usort). */
function compare_team_names(array $a, array $b): int
{
    return tr_compare((string) $a['name'], (string) $b['name']);
}

/** Güvenli query string okuma (dizi gönderilirse yok sayılır). */
function qs(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function qs_int(string $key): int
{
    $v = qs($key);
    return ctype_digit($v) ? (int) $v : 0;
}
