<?php

/**
 * PROMPT-22 Gorev-A gecici izolasyon teshis loglayici.
 * KALICI DEGILDIR: kok neden dogrulandiktan sonra bu dosya ve tum
 * teshis_logla() cagrilarinin kaldirilmasi zorunludur (DURUM.md'ye yazilir).
 *
 * Guvenlik kurallari: ham session cookie degeri YAZILMAZ (yalnizca
 * sha256'nin ilk 8 karakteri), sifre/token/harici credential YAZILMAZ.
 * Log dosyasi web kokunden erisilemeyen PHP gecici dizinine yazilir.
 */

function teshis_logla(string $olay, array $alanlar = []): void
{
    $parcalar = [gmdate('Y-m-d H:i:s'), $olay];

    foreach ($alanlar as $anahtar => $deger) {
        $parcalar[] = $anahtar . '=' . $deger;
    }

    $parcalar[] = 'sid=' . substr(hash('sha256', session_id()), 0, 8);

    @file_put_contents(
        sys_get_temp_dir() . '/ads-oauth-izolasyon-teshis.log',
        implode(' | ', $parcalar) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}
