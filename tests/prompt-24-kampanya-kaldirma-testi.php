<?php

/**
 * PROMPT-24 sentetik testi: REMOVED hedefi ve geri alinamaz kaldirma akisini
 * kontrol eder. Google Ads mutate cagrisi yapmaz.
 */

require_once __DIR__ . '/../php/servis/kampanya-servisi.php';

$gecersiz_hedefler = ['', 'DELETED', 'enabled', 'ARCHIVED', 'REMOVEDX'];

foreach ($gecersiz_hedefler as $hedef) {
    $cevap = kampanya_durumunu_degistir([
        'hedef_durum' => $hedef,
        'kampanya_id' => '24268992914',
    ]);

    if (($cevap['return'] ?? 1) !== 0) {
        throw new RuntimeException(
            sprintf('Gecersiz hedef durum kabul edildi: %s', var_export($hedef, true))
        );
    }
}

$removed_cevap = kampanya_durumunu_degistir([
    'hedef_durum' => 'REMOVED',
    'kampanya_id' => '24268992914',
]);

if (($removed_cevap['mesaj'] ?? '') === 'Hedef durum geçersiz; yalnızca ENABLED, PAUSED veya REMOVED kabul edilir.') {
    throw new RuntimeException('REMOVED hedefi gecersiz durum olarak reddedildi.');
}

$servis = file_get_contents(__DIR__ . '/../php/servis/kampanya-servisi.php');
$adapter = file_get_contents(__DIR__ . '/../php/baglayici/google-ads-baglayici.php');
$panel = file_get_contents(__DIR__ . '/../tema/panel/kampanyalarim.php');

if ($servis === false || $adapter === false || $panel === false) {
    throw new RuntimeException('PROMPT-24 kaynak dosyalarindan biri okunamadi.');
}

$kontroller = [
    [$servis, '&& $hedef_durum !== \'REMOVED\'', 'servis REMOVED dogrulamasi'],
    [$adapter, '&& $hedef_durum !== \'REMOVED\'', 'adapter REMOVED dogrulamasi'],
    [$servis, "'kaldirma_onayi'", 'sunucu tarafli kaldirma onayi'],
    [$servis, '\'durum\' => $hedef_durum === \'ENABLED\'', 'yerel durum yansimasi'],
    [$adapter, '->setRemove($kaynak)', 'Google Ads remove operation'],
    [$adapter, 'if ($hedef_durum === \'REMOVED\')', 'REMOVED operation ayrimi'],
    [$adapter, "'CampaignService::mutateCampaigns '", 'hedefe gore hata logu'],
    [$panel, 'value="active" selected', 'varsayilan kaldirilanlar filtresi'],
    [$panel, "filtre.value==='all'||k.status!=='REMOVED'", 'frontend filtreleme'],
    [$panel, "f.append('kaldirma_onayi'", 'frontend kaldirma onayi gonderimi'],
    [$panel, 'GER', 'geri alinamaz uyari'],
];

foreach ($kontroller as [$kaynak, $desen, $aciklama]) {
    if (strpos($kaynak, $desen) === false) {
        throw new RuntimeException(sprintf('Eksik PROMPT-24 deseni: %s', $aciklama));
    }
}

$removed_baslangic = strpos($adapter, 'if ($hedef_durum === \'REMOVED\')');

if ($removed_baslangic !== false) {
    $removed_kisim = substr($adapter, $removed_baslangic);
    $removed_sinir = strpos($removed_kisim, '} else {');

    if ($removed_sinir === false || strpos(substr($removed_kisim, 0, $removed_sinir), 'setUpdateMask') !== false) {
        throw new RuntimeException('REMOVED operation icinde update/update_mask kullaniliyor.');
    }
}

if (strpos($panel, "k.status!=='REMOVED'") === false
    || strpos($panel, "onayKutusu(k,'REMOVED')") === false) {
    throw new RuntimeException('Kaldir butonu REMOVED kampanyalar icin gizlenmiyor veya onaya baglanmiyor.');
}

echo "PROMPT-24 kampanya kaldirma/filtre testleri: PASS\n";