<?php

/**
 * PROMPT-23.9 sentetik testi: yeni hesap pasif başlar, pasif giriş reddedilir,
 * active=1 sonrası giriş başarılı olur.
 *
 * Çalıştırma: php tests/prompt-23.9-kayit-active-testi.php
 * Test veritabanında geçici bir kullanıcı oluşturur ve finally içinde siler.
 * Ön koşul: site_sahipleri.active migration'ı uygulanmış olmalıdır.
 */

require_once __DIR__ . '/../php/servis/kullanici-servisi.php';

$eposta = 'prompt239-' . bin2hex(random_bytes(8)) . '@example.invalid';
$sifre = 'Prompt239-Test-Sifre!';
$baglanti = veritabani_baglan();

try {
    $kayit = kullanici_kayit([
        'eposta' => $eposta,
        'sifre' => $sifre,
        'ad_soyad' => 'PROMPT-23.9 Test',
    ]);

    if (($kayit['return'] ?? 0) !== 1 || oturum_sahip_no() !== null) {
        throw new RuntimeException('Kayıt başarılı olmadı veya otomatik oturum açıldı.');
    }

    $sec = $baglanti->prepare(
        'SELECT `no`, `active` FROM `site_sahipleri` WHERE `eposta` = :eposta LIMIT 1'
    );
    $sec->execute(['eposta' => $eposta]);
    $kullanici = $sec->fetch();

    if (!is_array($kullanici) || (int) $kullanici['active'] !== 0) {
        throw new RuntimeException('Yeni hesap active=0 olarak oluşmadı.');
    }

    $pasif_giris = kullanici_giris([
        'eposta' => $eposta,
        'sifre' => $sifre,
    ]);

    if (($pasif_giris['return'] ?? 1) !== 0) {
        throw new RuntimeException('Pasif hesapla giriş reddedilmedi.');
    }

    $etkinlestir = $baglanti->prepare(
        'UPDATE `site_sahipleri` SET `active` = 1 WHERE `no` = :sahip_no'
    );
    $etkinlestir->execute(['sahip_no' => (int) $kullanici['no']]);

    $aktif_giris = kullanici_giris([
        'eposta' => $eposta,
        'sifre' => $sifre,
    ]);

    if (($aktif_giris['return'] ?? 0) !== 1
        || oturum_sahip_no() !== (int) $kullanici['no']) {
        throw new RuntimeException('Aktif hesapla giriş başarılı olmadı.');
    }

    echo "PROMPT-23.9 kayıt/active testleri: PASS\n";
} finally {
    $sil = $baglanti->prepare(
        'DELETE FROM `site_sahipleri` WHERE `eposta` = :eposta'
    );
    $sil->execute(['eposta' => $eposta]);

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }
}