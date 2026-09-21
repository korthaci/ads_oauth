<?php

/**
 * Genel API HTTP giris noktasi.
 * Kullanici ve OAuth islemlerini ilgili servis/teknik katmanlara dispatch eder
 * ve JSON response doner; is mantigi bu dosyada tutulmaz. 
 */

define('ADS_OAUTH_API_INDEX', true);

function api_json_dondur(array $cevap): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($cevap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

try {
    require_once __DIR__ . '/../php/servis/kullanici-servisi.php';
    require_once __DIR__ . '/../php/servis/hesap-servisi.php';
    require_once __DIR__ . '/kampanya-listele.php';
    require_once __DIR__ . '/kampanya-durdur.php';
    require_once __DIR__ . '/kampanya-bitis-tarihi.php';
    require_once __DIR__ . '/kampanya-olustur.php';
    require_once __DIR__ . '/ai-kampanya-onerisi.php';
    require_once __DIR__ . '/oauth-baslat.php';
    require_once __DIR__ . '/oauth-donus.php';
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
            $cevap = api_oauth_baslat();
            break;

        case 'oauth-donus':
            $cevap = api_oauth_donus();

            if (
                ($cevap['return'] ?? 0) === 1
                && isset($cevap['url'])
                && is_string($cevap['url'])
                && $cevap['url'] !== ''
            ) {
                header('Location: ../' . ltrim($cevap['url'], '/'));
                exit;
            }

            break;

        case 'google-hesap-sec':
            $cevap = google_oauth_hesap_sec();
            break;

        case 'google-hesap-kesfet':
            $cevap = google_hesaplarini_kesfet();
            break;

        case 'google-musteri-hesaplari':
            $cevap = google_musteri_hesaplarini_kesfet();
            break;

        case 'kampanya-listele':
            $cevap = api_kampanya_listele();
            break;

        case 'kampanya-durdur':
            $cevap = api_kampanya_durdur();
            break;

        case 'kampanya-bitis-tarihi':
            $cevap = api_kampanya_bitis_tarihi();
            break;

        case 'kampanya-olustur':
            $cevap = api_kampanya_olustur();
            break;

        case 'ai-kampanya-onerisi':
            $cevap = api_ai_kampanya_onerisi();
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
    if (function_exists('google_ads_hata_kaydi_yaz')) {
        google_ads_hata_kaydi_yaz('api.index', $hata);
    }

    // Hassas OAuth verileri response'a veya log'a yazilmaz.
    api_json_dondur([
        'return' => 0,
        'mesaj' => 'İşlem gerçekleştirilemedi.',
    ]);
}