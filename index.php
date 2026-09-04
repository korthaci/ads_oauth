<?php
/**
 * ads_oauth - Ana web giris noktasi.
 *
 * Form isteklerini kullanici servis katmanina aktarir; gorunumu ilgili tema
 * dosyasina birakir. API istekleri icin tek giris noktasi api/index.php'dir.
 */

require_once __DIR__ . '/php/servis/kullanici-servisi.php';

$mesaj = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $form_islem = $_POST['form_islem'] ?? '';

    try {
        if ($form_islem === 'kayit') {
            $cevap = kullanici_kayit($_POST);
        } elseif ($form_islem === 'giris') {
            $cevap = kullanici_giris($_POST);
        } else {
            $cevap = [
                'return' => 0,
                'mesaj' => 'Geçersiz form işlemi.',
            ];
        }

        if (($cevap['return'] ?? 0) === 1) {
            header('Location: index.php');
            exit;
        }

        $mesaj = (string) ($cevap['mesaj'] ?? 'İşlem gerçekleştirilemedi.');
    } catch (Throwable $hata) {
        $mesaj = 'İşlem gerçekleştirilemedi.';
    }
}

if (($_GET['islem'] ?? '') === 'cikis') {
    try {
        kullanici_cikis();
    } catch (Throwable $hata) {
        // Logout response'u hassas veri icermedigi icin kullanici giris ekranina donulur.
    }

    header('Location: index.php');
    exit;
}

$sahip_no = oturum_sahip_no();

if ($sahip_no === null || $sahip_no < 1) {
    require __DIR__ . '/tema/giris.php';
    exit;
}

require __DIR__ . '/tema/panel/anasayfa.php';