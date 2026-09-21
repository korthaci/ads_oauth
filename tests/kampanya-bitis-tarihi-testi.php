<?php

/**
 * Kampanya bitis tarihi degistirme akisinin sentetik, API'siz kontrolleri.
 */

require_once __DIR__ . '/../php/servis/kampanya-servisi.php';

$gecersiz_girdiler = [
    ['', '2026-12-31'],
    ['24268992914', ''],
    ['24268992914', '2026-02-30'],
    ['24268992914', '2026-1-01'],
];

foreach ($gecersiz_girdiler as [$kampanya_id, $bitis_tarihi]) {
    $cevap = kampanya_bitis_tarihini_degistir([
        'kampanya_id' => $kampanya_id,
        'bitis_tarihi' => $bitis_tarihi,
    ]);

    if (($cevap['return'] ?? 1) !== 0) {
        throw new RuntimeException('Gecersiz bitis tarihi girdisi kabul edildi.');
    }
}

$servis = file_get_contents(__DIR__ . '/../php/servis/kampanya-servisi.php');
$adapter = file_get_contents(__DIR__ . '/../php/baglayici/google-ads-baglayici.php');
$panel = file_get_contents(__DIR__ . '/../tema/panel/kampanyalarim.php');
$api = file_get_contents(__DIR__ . '/../api/index.php');

if ($servis === false || $adapter === false || $panel === false || $api === false) {
    throw new RuntimeException('Bitis tarihi kaynak dosyalarindan biri okunamadi.');
}

$kontroller = [
    [$servis, 'function kampanya_bitis_tarihini_degistir', 'servis bitis tarihi fonksiyonu'],
    [$servis, "'bitis_tarihi'", 'servis bitis tarihi girdisi'],
    [$servis, 'new DateTimeImmutable', 'sunucu tarih dogrulamasi'],
    [$adapter, 'function google_ads_kampanya_bitis_tarihini_degistir', 'adapter bitis tarihi fonksiyonu'],
    [$adapter, 'setEndDateTime', 'Google Ads end_date_time guncellemesi'],
    [$adapter, "setPaths(['end_date_time'])", 'yalnizca bitis tarihi update mask'],
    [$adapter, "'CampaignService::mutateCampaigns end_date_time'", 'bitis tarihi hata logu'],
    [$panel, 'type=\'date\'', 'standart date input'],
    [$panel, "f.append('bitis_tarihi'", 'frontend bitis tarihi gonderimi'],
    [$panel, "islem=kampanya-bitis-tarihi", 'frontend bitis tarihi endpointi'],
    [$api, "case 'kampanya-bitis-tarihi'", 'API dispatch'],
];

foreach ($kontroller as [$kaynak, $desen, $aciklama]) {
    if (strpos($kaynak, $desen) === false) {
        throw new RuntimeException(sprintf('Eksik bitis tarihi deseni: %s', $aciklama));
    }
}

$end_date_start = strpos($adapter, '->setEndDateTime($google_bitis_tarihi)');
$end_date_end = strpos($adapter, "setPaths(['end_date_time'])", $end_date_start);

if ($end_date_start === false || $end_date_end === false) {
    throw new RuntimeException('Bitis tarihi update mask akisi bulunamadi.');
}

echo "Kampanya bitis tarihi testleri: PASS\n";