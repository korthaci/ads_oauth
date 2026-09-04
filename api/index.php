<?php

/**
 * Genel API HTTP giris noktasi.
 * Kullanici ve OAuth islemlerini ilgili servis/teknik katmanlara dispatch eder
 * ve JSON response doner; is mantigi bu dosyada tutulmaz.
 */

function api_json_dondur(array $cevap): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($cevap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

try {
    require_once __DIR__ . '/../php/servis/kullanici-servisi.php';
    require_once __DIR__ . '/../php/servis/hesap-servisi.php';
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
        case 'kayit':
            $cevap = kullanici_kayit($_POST);
            break;

        case 'giris':
            $cevap = kullanici_giris($_POST);
            break;

        case 'cikis':
            $cevap = kullanici_cikis();
            break;

        case 'oturum-kontrol':
            $cevap = kullanici_oturum_kontrolu();
            break;

        case 'oauth-baslat':
            $cevap = google_oauth_baslat();
            break;

        case 'oauth-donus':
            $cevap = google_oauth_donus();
            break;

        case 'google-hesap-kesfet':
            $cevap = google_hesaplarini_kesfet();
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