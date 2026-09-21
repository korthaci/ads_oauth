<?php

/**
 * Reklam aciklamalarinin yalnizca satir sonlarina gore ayrildigini test eder.
 *
 * Calistirma: php tests/reklam-aciklamalari-ayristirma-testi.php
 */

require_once __DIR__ . '/../php/servis/kampanya-servisi.php';

$girdi = "Ilk aciklama, virgulu ile birlikte\n\n  \nIkinci aciklama\r\n\r\nUcuncu aciklama, baska bir virgullu ifade";
$beklenen = [
    'Ilk aciklama, virgulu ile birlikte',
    'Ikinci aciklama',
    'Ucuncu aciklama, baska bir virgullu ifade',
];

$sonuc = kampanya_aciklamalarini_satirlara_ayir($girdi);

if ($sonuc !== $beklenen) {
    fwrite(STDERR, "HATA: Reklam aciklamalari beklenen satir listesine donusmedi.\n");
    var_export($sonuc);
    fwrite(STDERR, "\n");
    exit(1);
}

echo "Reklam aciklamalari ayrıştırma testleri: PASS\n";