<?php

/**
 * Proje kokundeki .env dosyasini okur ve ayarlari dizi olarak doner.
 *
 * @return array<string, string>
 */
function config_yukle(): array
{
    $env_yolu = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

    if (!is_file($env_yolu)) {
        throw new RuntimeException('.env dosyasi bulunamadi: ' . $env_yolu);
    }

    $satirlar = file($env_yolu, FILE_IGNORE_NEW_LINES);

    if ($satirlar === false) {
        throw new RuntimeException('.env dosyasi okunamadi: ' . $env_yolu);
    }

    $ayarlar = [];

    foreach ($satirlar as $satir_no => $satir) {
        $satir = trim($satir);

        if ($satir === '' || str_starts_with($satir, '#')) {
            continue;
        }

        $ayrac_no = strpos($satir, '=');

        if ($ayrac_no === false) {
            throw new RuntimeException('.env dosyasinda gecersiz satir: ' . ($satir_no + 1));
        }

        $anahtar = trim(substr($satir, 0, $ayrac_no));
        $deger = trim(substr($satir, $ayrac_no + 1));

        if ($anahtar === '') {
            throw new RuntimeException('.env dosyasinda bos anahtar: ' . ($satir_no + 1));
        }

        $deger_uzunlugu = strlen($deger);
        $ilk_karakter = $deger[0] ?? '';
        $son_karakter = $deger[$deger_uzunlugu - 1] ?? '';

        if ($deger_uzunlugu >= 2 && (($ilk_karakter === '"' && $son_karakter === '"') || ($ilk_karakter === "'" && $son_karakter === "'"))) {
            $deger = substr($deger, 1, -1);
        }

        $ayarlar[$anahtar] = $deger;
    }

    $zorunlu_anahtarlar = [
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'DB_PASS',
        'SIFRELEME_ANAHTARI',
    ];

    foreach ($zorunlu_anahtarlar as $anahtar) {
        if (!array_key_exists($anahtar, $ayarlar) || $ayarlar[$anahtar] === '') {
            throw new RuntimeException('.env dosyasinda zorunlu anahtar eksik veya bos: ' . $anahtar);
        }
    }

    return $ayarlar;
}

$ayarlar = config_yukle();

/**
 * Bir ortam ayarini dondurur.
 */
function config(string $anahtar): string
{
    global $ayarlar;

    if (!array_key_exists($anahtar, $ayarlar)) {
        throw new InvalidArgumentException('Tanimli olmayan config anahtari: ' . $anahtar);
    }

    return $ayarlar[$anahtar];
}