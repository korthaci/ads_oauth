<?php

/**
 * PROMPT-20 birim testi: TL -> budget_amount_micros cevrimi.
 *
 * Calistirma: php tests/butce-micros-test.php
 * Google Ads API cagrisi yapmaz; yalnizca saf fonksiyon test edilir.
 */

require_once __DIR__ . '/../php/servis/kampanya-servisi.php';

$gecerli_vakalar = [
    ['500', 500000000],
    ['500.00', 500000000],
    ['500 TL', 500000000],
    [' 500 tl ', 500000000],
    [500, 500000000],
    [500.0, 500000000],
    ['12,50', 12500000],
    ['12.50', 12500000],
    ['12.5', 12500000],
    [12.50, 12500000],
    ['19,99', 19990000],
    [19.99, 19990000],
    ['0,5', 500000],
    [0.5, 500000],
    ['1', 1000000],
    ['1000000', 1000000000000],
];

foreach ($gecerli_vakalar as $vaka) {
    [$girdi, $beklenen] = $vaka;
    $sonuc = kampanya_butcesini_microsa_cevir($girdi);

    if ($sonuc !== $beklenen) {
        fwrite(STDERR, sprintf(
            "HATA: %s (%s) girdisi %d beklenirken %d dondu.\n",
            var_export($girdi, true),
            gettype($girdi),
            $beklenen,
            $sonuc
        ));
        exit(1);
    }
}

$gecersiz_vakalar = [
    '',
    '   ',
    null,
    false,
    'abc',
    '12a',
    -5,
    -0.01,
    0,
    0.0,
    '0',
    '0,00',
    '1.234',
    '12,345',
    'NaN',
    '1e3',
];

foreach ($gecersiz_vakalar as $girdi) {
    try {
        kampanya_butcesini_microsa_cevir($girdi);
    } catch (InvalidArgumentException $hata) {
        continue;
    }

    fwrite(STDERR, sprintf(
        "HATA: gecersiz girdi hata beklenirken kabul edildi: %s\n",
        var_export($girdi, true)
    ));
    exit(1);
}

printf(
    "PROMPT-20 butce micros testleri: PASS (%d gecerli, %d gecersiz vaka)\n",
    count($gecerli_vakalar),
    count($gecersiz_vakalar)
);
