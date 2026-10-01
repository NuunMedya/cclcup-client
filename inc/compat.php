<?php
/**
 * Eski/kısıtlı hosting ortamları için yedekler.
 *
 * Bazı paylaşımlı hostinglerde "mbstring" eklentisi kapalı gelir; o durumda
 * mb_* fonksiyonları tanımsız olduğu için her sayfa 500 hatası verirdi.
 * Burada sitenin kullandığı kadarını UTF-8 güvenli şekilde tanımlıyoruz.
 */

if (!function_exists('mb_internal_encoding')) {
    function mb_internal_encoding($encoding = null)
    {
        return $encoding === null ? 'UTF-8' : true;
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null, $encoding = null)
    {
        $chars = preg_split('//u', (string) $string, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode('', $length === null ? array_slice($chars, $start) : array_slice($chars, $start, $length));
    }
}

if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($string, $encoding = null)
    {
        return strtoupper(strtr((string) $string, [
            'ç' => 'Ç', 'ğ' => 'Ğ', 'ı' => 'I', 'i' => 'İ', 'ö' => 'Ö', 'ş' => 'Ş', 'ü' => 'Ü',
        ]));
    }
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = null)
    {
        return strtolower(strtr((string) $string, [
            'Ç' => 'ç', 'Ğ' => 'ğ', 'I' => 'ı', 'İ' => 'i', 'Ö' => 'ö', 'Ş' => 'ş', 'Ü' => 'ü',
        ]));
    }
}
