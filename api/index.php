<?php

/**
 * Genel API HTTP giris noktasi.
 * OAuth islemlerini php/oauth/google-oauth.php katmanina dispatch eder ve JSON
 * response doner; teknik OAuth mantigi bu dosyada tutulmaz.
 */

function api_json_dondur(array $cevap): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($cevap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

try {
    require_once __DIR__ . '/../php/oauth/google-oauth.php';

    $islem = $_GET['islem'] ?? null;

    if (!is_string($islem) || $islem === '') {
        api_json_dondur([
            'return' => 0,
            'mesaj' => 'Geçerli bir API işlemi belirtilmedi.',
        ]);
        exit;
    }

    switch ($islem) {
        case 'oauth-baslat':
            $cevap = google_oauth_baslat();
            break;

        case 'oauth-donus':
            $cevap = google_oauth_donus();
            break;

        default:
            $cevap = [
                'return' => 0,
                'mesaj' => 'Geçersiz API işlemi.',
            ];
            break;
    }

    api_json_dondur($cevap);
} catch (Throwable $hata) {
    // Hassas OAuth verileri response'a veya log'a yazilmaz.
    api_json_dondur([
        'return' => 0,
        'mesaj' => 'İşlem gerçekleştirilemedi.',
    ]);
}