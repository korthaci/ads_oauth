<?php
/**
 * ads_oauth - Ana web giris noktasi.
 *
 * Form isteklerini kullanici servis katmanina aktarir; gorunumu ilgili tema
 * dosyasina birakir. API istekleri icin tek giris noktasi api/index.php'dir.
 */

require_once __DIR__ . '/php/servis/kullanici-servisi.php';
require_once __DIR__ . '/php/servis/hesap-servisi.php';
require_once __DIR__ . '/php/teshis-log.php';

// PROMPT-22: Oturuma özel dinamik panel içeriği asla önbelleklenmemelidir
// (LiteSpeed/CDN HTML cache riski). POST işlenmeden önce, her yanıtta gönderilir.
header('Cache-Control: no-store, private');
header('Pragma: no-cache');

$mesaj = '';

oturum_baslat();
teshis_logla('index-giris', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'uri' => $_SERVER['REQUEST_URI'] ?? '-',
    'sahip_no' => (string) (oturum_sahip_no() ?? 'null'),
]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $form_islem = $_POST['form_islem'] ?? '';

    try {
        if ($form_islem === 'kayit') {
            // PROMPT-22 Görev B: Public kayıt kapalıdır (ARCHITECTURE.md §1:
            // "Kayıt herkese açık değildir; yeni kullanıcı erişimi
            // manuel/davet yoluyla verilir"). Yeni hesap yalnızca sunucuda
            // bin/kullanici-olustur.php (CLI) ile oluşturulur.
            $cevap = [
                'return' => 0,
                'mesaj' => 'Kayıt şu anda davetle sınırlıdır.',
            ];
        } elseif ($form_islem === 'giris') {
            teshis_logla('index-post-giris-denemesi');
            $cevap = kullanici_giris($_POST);
        } else {
            $cevap = [
                'return' => 0,
                'mesaj' => 'Geçersiz form işlemi.',
            ];
        }

        teshis_logla('index-post-sonuc', [
            'form_islem' => $form_islem,
            'return' => (string) ($cevap['return'] ?? 0),
            'sahip_no' => (string) (oturum_sahip_no() ?? 'null'),
        ]);

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

if (($_GET['islem'] ?? '') === 'kampanya-sihirbazi') {
    require __DIR__ . '/tema/panel/kampanya-sihirbazi.php';
    exit;
}

if (($_GET['islem'] ?? '') === 'kampanyalarim') {
    require __DIR__ . '/tema/panel/kampanyalarim.php';
    exit;
}

$google_panel_baglantisi = null;
$google_panel_baglanti_kontrol_hatasi = false;

try {
    $google_panel_baglantisi = google_panel_baglantisini_al($sahip_no);
} catch (Throwable $hata) {
    $google_panel_baglanti_kontrol_hatasi = true;
}

require __DIR__ . '/tema/panel/anasayfa.php';