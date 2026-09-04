<?php

require_once __DIR__ . '/config.php';

/**
 * Veriyi AES-256-CBC ile sifreler ve IV ile birlikte tek bir base64 metin doner.
 */
function sifrele(string $veri): string
{
    $algoritma = 'AES-256-CBC';
    $anahtar = hash('sha256', config('SIFRELEME_ANAHTARI'), true);
    $iv_uzunlugu = openssl_cipher_iv_length($algoritma);

    if ($iv_uzunlugu === false) {
        throw new RuntimeException('Sifreleme algoritmasi icin IV uzunlugu alinamadi.');
    }

    $guvenli = false;
    $iv = openssl_random_pseudo_bytes($iv_uzunlugu, $guvenli);

    if ($iv === false || !$guvenli) {
        throw new RuntimeException('Guvenli rastgele IV uretilemedi.');
    }

    $sifreli_veri = openssl_encrypt($veri, $algoritma, $anahtar, OPENSSL_RAW_DATA, $iv);

    if ($sifreli_veri === false) {
        throw new RuntimeException('Veri sifrelenemedi.');
    }

    return base64_encode($iv . $sifreli_veri);
}

/**
 * IV bilgisini ayirarak base64 sifreli veriyi cozer.
 */
function coz(string $sifreli_veri): string
{
    $algoritma = 'AES-256-CBC';
    $anahtar = hash('sha256', config('SIFRELEME_ANAHTARI'), true);
    $iv_uzunlugu = openssl_cipher_iv_length($algoritma);

    if ($iv_uzunlugu === false) {
        throw new RuntimeException('Sifreleme algoritmasi icin IV uzunlugu alinamadi.');
    }

    $kodlanmis_veri = base64_decode($sifreli_veri, true);

    if ($kodlanmis_veri === false || strlen($kodlanmis_veri) <= $iv_uzunlugu) {
        throw new RuntimeException('Sifreli veri gecersiz.');
    }

    $iv = substr($kodlanmis_veri, 0, $iv_uzunlugu);
    $sifreli_icerik = substr($kodlanmis_veri, $iv_uzunlugu);
    $veri = openssl_decrypt($sifreli_icerik, $algoritma, $anahtar, OPENSSL_RAW_DATA, $iv);

    if ($veri === false) {
        throw new RuntimeException('Sifreli veri cozulemedi.');
    }

    return $veri;
}