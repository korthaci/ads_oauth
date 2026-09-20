<?php

/**
 * ads_oauth - Site sahibi olusturma (yalnizca CLI).
 *
 * ARCHITECTURE.md §1 geregi kayit herkese acik degildir; yeni kullanici
 * erisimi manuel/davet yoluyla verilir (PROMPT-22). Web'den erisilemez:
 * (1) .htaccess ile bin/ engellenmistir, (2) script yalnizca PHP_SAPI=cli
 * iken calisir.
 *
 * Kullanim:
 *   php bin/kullanici-olustur.php <eposta> <sifre> "Ad Soyad"
 *
 * Ornek:
 *   php bin/kullanici-olustur.php kisi@ornek.com 'GucluBirSifre123' "Ayse Yilmaz"
 *
 * Dogrulama ve sifre hash'leme mantigi kopyalanmaz; kullanici_kayit()
 * birebir cagrilir. NOT: sifre komut satiri argumanindan gectigi icin
 * shell gecmisine dustugu bilinir; gerektiginde gecmisi temizleyin.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu script yalnızca sunucuda komut satırından çalıştırılabilir.\n");
}

if ($argc !== 4) {
    fwrite(
        STDERR,
        "Kullanım: php bin/kullanici-olustur.php <eposta> <sifre> \"Ad Soyad\"\n"
    );
    exit(1);
}

require_once dirname(__DIR__) . '/php/servis/kullanici-servisi.php';

$cevap = kullanici_kayit([
    'eposta' => $argv[1],
    'sifre' => $argv[2],
    'ad_soyad' => $argv[3],
]);

if (($cevap['return'] ?? 0) === 1) {
    echo 'Site sahibi oluşturuldu: '
        . kullanici_eposta_normalize_et($argv[1]) . "\n";

    exit(0);
}

fwrite(
    STDERR,
    'Oluşturulamadı: ' . (string) ($cevap['mesaj'] ?? 'Bilinmeyen hata.') . "\n"
);

exit(1);
