<?php

/**
 * PROMPT-20.3 sentetik testi: suggestGeoTargetConstants yanit isleme ve
 * belirsiz konum hata mesaji.
 *
 * Calistirma: php tests/konum-oneri-mesaj-testi.php
 * Google Ads API cagrisi yapmaz; sentetik SuggestGeoTargetConstantsResponse
 * nesneleriyle saf google_ads_konum_yanitini_isle() fonksiyonu test edilir.
 */

use Google\Ads\GoogleAds\V25\Resources\GeoTargetConstant;
use Google\Ads\GoogleAds\V25\Services\GeoTargetConstantSuggestion;
use Google\Ads\GoogleAds\V25\Services\SuggestGeoTargetConstantsResponse;

require_once __DIR__ . '/../php/servis/kampanya-servisi.php';

function konum_oneri_sabiti_olustur(
    string $kaynak,
    string $ad,
    string $hedef_tur,
    string $kanonik_ad
): GeoTargetConstant {
    return (new GeoTargetConstant())
        ->setResourceName($kaynak)
        ->setName($ad)
        ->setTargetType($hedef_tur)
        ->setCanonicalName($kanonik_ad);
}

function konum_onerisi_olustur(GeoTargetConstant $sabit): GeoTargetConstantSuggestion
{
    return (new GeoTargetConstantSuggestion())
        ->setLocale('tr')
        ->setSearchTerm('ankara')
        ->setGeoTargetConstant($sabit);
}

function konum_yaniti_olustur(array $oneriler): \Google\Ads\GoogleAds\V25\Services\SuggestGeoTargetConstantsResponse
{
    return new \Google\Ads\GoogleAds\V25\Services\SuggestGeoTargetConstantsResponse([
        'geo_target_constant_suggestions' => $oneriler,
    ]);
}

/**
 * Beklenen GoogleAdsKesifHatasi'ni dogrular: kategori 'girdi', mesaj icinde
 * $icermeli parcalari var, $icermemeli parcalari yok.
 */
function belirsizlik_hatasi_bekle(
    callable $cagri,
    array $icermeli,
    array $icermemeli,
    string $aciklama
): void {
    try {
        $cagri();
    } catch (GoogleAdsKesifHatasi $hata) {
        if ($hata->kategori !== 'girdi') {
            fwrite(STDERR, sprintf(
                "HATA (%s): kategori 'girdi' beklenirken '%s' geldi.\n",
                $aciklama,
                $hata->kategori
            ));
            exit(1);
        }

        $mesaj = $hata->getMessage();

        foreach ($icermeli as $parca) {
            if (mb_strpos($mesaj, $parca, 0, 'UTF-8') === false) {
                fwrite(STDERR, sprintf(
                    "HATA (%s): mesaj '%s' parcasini icermiyor. Mesaj: %s\n",
                    $aciklama,
                    $parca,
                    $mesaj
                ));
                exit(1);
            }
        }

        foreach ($icermemeli as $parca) {
            if (mb_strpos($mesaj, $parca) !== false) {
                fwrite(STDERR, sprintf(
                    "HATA (%s): mesaj beklenmedik parca iceriyor: %s. Mesaj: %s\n",
                    $aciklama,
                    $parca,
                    $mesaj
                ));
                exit(1);
            }
        }

        return;
    }

    fwrite(STDERR, sprintf(
        "HATA (%s): GoogleAdsKesifHatasi firlamadi.\n",
        $aciklama
    ));
    exit(1);
}

$vaka_sayisi = 0;

// 1) Tek tam eslesme: buyuk/kucuk harf duyarsiz, 4 alanli kayit doner.
$sabit_il = (new GeoTargetConstant())
    ->setResourceName('geoTargetConstants/110419')
    ->setName('Ankara')
    ->setTargetType('Province')
    ->setCanonicalName('Ankara,Ankara,Turkey');

$sonuc = google_ads_konum_yanitini_isle(
    konum_yaniti_olustur([konum_onerisi_olustur($sabit_il)]),
    '  ANKARA '
);

$beklenen = [
    'resource_name' => 'geoTargetConstants/110419',
    'name' => 'Ankara',
    'target_type' => 'Province',
    'canonical_name' => 'Ankara,Ankara,Turkey',
];

if ($sonuc !== $beklenen) {
    fwrite(STDERR, sprintf(
        "HATA (tek tam eslesme): beklenen kayit donmedi: %s\n",
        var_export($sonuc, true)
    ));
    exit(1);
}
$vaka_sayisi++;

// 2) Belirsiz cok eslesme: mesaj adaylari (target_type) ve canonical_name
//    orneklerini icerir; tam eslesme olmayan oneri listelenmez.
$sabit_ilce = (new GeoTargetConstant())
    ->setResourceName('geoTargetConstants/110421')
    ->setName('Ankara')
    ->setTargetType('County')
    ->setCanonicalName('Ankara,Kızılcahamam,Turkey');

$yakin_oneri = konum_onerisi_olustur(
    (new GeoTargetConstant())
        ->setResourceName('geoTargetConstants/900001')
        ->setName('Ankaray')
        ->setTargetType('University')
        ->setCanonicalName('Ankaray,Turkey')
);

try {
    google_ads_konum_yanitini_isle(
        konum_yaniti_olustur([
            konum_onerisi_olustur($sabit_il),
            konum_onerisi_olustur($sabit_ilce),
            $yakin_oneri,
        ]),
        'Ankara'
    );

    fwrite(STDERR, "HATA (belirsiz cok eslesme): hata firlamadi.\n");
    exit(1);
} catch (GoogleAdsKesifHatasi $hata) {
    $mesaj = $hata->getMessage();

    foreach ([
        'Aynı adlı birden fazla Google Ads konumu bulundu',
        'Ankara (Province), Ankara (County)',
        "'canonical_name'",
        'Ankara,Ankara,Turkey',
        'Ankara,Kızılcahamam,Turkey',
    ] as $parca) {
        if (mb_strpos($mesaj, $parca) === false) {
            fwrite(STDERR, sprintf(
                "HATA (belirsiz mesaj): mesaj '%s' icermiyor. Mesaj: %s\n",
                $parca,
                $mesaj
            ));
            exit(1);
        }
    }

    if (mb_strpos($mesaj, 'Ankaray') !== false) {
        fwrite(STDERR, sprintf(
            "HATA (belirsiz mesaj): tam eslesme olmayan aday listelendi. Mesaj: %s\n",
            $mesaj
        ));
        exit(1);
    }

    if ($hata->kategori !== 'girdi') {
        fwrite(STDERR, "HATA (belirsiz mesaj): kategori 'girdi' degil.\n");
        exit(1);
    }

    $vaka_sayisi++;
}

// 3) Belirsiz ama canonical_name bos: hedef turleri yine listelenir,
//    canonical orneklemesi yerine genel yonlendirme verilir.
$kanoniksiz_il = (new GeoTargetConstant())
    ->setResourceName('geoTargetConstants/110419')
    ->setName('Ankara')
    ->setTargetType('Province');
$kanoniksiz_ilce = (new GeoTargetConstant())
    ->setResourceName('geoTargetConstants/110421')
    ->setName('Ankara')
    ->setTargetType('County');

try {
    google_ads_konum_yanitini_isle(
        konum_yaniti_olustur([
            konum_onerisi_olustur($kanoniksiz_il),
            konum_onerisi_olustur($kanoniksiz_ilce),
        ]),
        'Ankara'
    );

    fwrite(STDERR, "HATA (kanoniksiz belirsizlik): hata firlamadi.\n");
    exit(1);
} catch (GoogleAdsKesifHatasi $hata) {
    $mesaj = $hata->getMessage();

    if (
        mb_strpos($mesaj, 'Ankara (Province), Ankara (County)') === false
        || mb_strpos($mesaj, 'daha belirgin yazın') === false
        || mb_strpos($mesaj, 'canonical_name') !== false
    ) {
        fwrite(STDERR, sprintf(
            "HATA (kanoniksiz belirsizlik): mesaj bekleneni icermiyor: %s\n",
            $mesaj
        ));
        exit(1);
    }

    $vaka_sayisi++;
}

// 4) 6 adayli belirsizlik: mesaj en fazla 5 canonical ornegi listeler.
$cok_aday = [];

for ($i = 1; $i <= 6; $i++) {
    $cok_aday[] = konum_onerisi_olustur(
        (new GeoTargetConstant())
            ->setResourceName('geoTargetConstants/11050' . $i)
            ->setName('Ankara')
            ->setTargetType('County')
            ->setCanonicalName('Ankara,Ilce' . $i . ',Turkey')
    );
}

try {
    google_ads_konum_yanitini_isle(
        konum_yaniti_olustur($cok_aday),
        'Ankara'
    );

    fwrite(STDERR, "HATA (6 aday): hata firlamadi.\n");
    exit(1);
} catch (GoogleAdsKesifHatasi $hata) {
    $mesaj = $hata->getMessage();

    foreach ([1, 2, 3, 4, 5] as $i) {
        if (mb_strpos($mesaj, 'Ankara,Ilce' . $i . ',Turkey') === false) {
            fwrite(STDERR, sprintf(
                "HATA (6 aday): %d. aday mesajda yok. Mesaj: %s\n",
                $i,
                $mesaj
            ));
            exit(1);
        }
    }

    if (mb_strpos($mesaj, 'Ankara,Ilce6,Turkey') !== false) {
        fwrite(STDERR, sprintf(
            "HATA (6 aday): mesaj 5 adayla sinirli kalmadi: %s\n",
            $mesaj
        ));
        exit(1);
    }

    $vaka_sayisi++;
}

// 5) Tam eslesme yok ama oneriler var: ornek onerilerle hata (davranis korunur).
$yakin_yanit = konum_yaniti_olustur([
    konum_onerisi_olustur(
        (new GeoTargetConstant())
            ->setResourceName('geoTargetConstants/900001')
            ->setName('Ankaray')
            ->setTargetType('University')
            ->setCanonicalName('Ankaray,Turkey')
    ),
]);

try {
    google_ads_konum_yanitini_isle($yakin_yanit, 'Ankara');

    fwrite(STDERR, "HATA (tam eslesme yok): hata firlamadi.\n");
    exit(1);
} catch (GoogleAdsKesifHatasi $hata) {
    if (
        $hata->kategori !== 'girdi'
        || mb_strpos($hata->getMessage(), 'Örnek öneriler: Ankaray') === false
    ) {
        fwrite(STDERR, sprintf(
            "HATA (tam eslesme yok): beklenen mesaj gelmedi: %s\n",
            $hata->getMessage()
        ));
        exit(1);
    }

    $vaka_sayisi++;
}

// 6) Bos yanit: bulunamadi mesaji korunur.
try {
    google_ads_konum_yanitini_isle(konum_yaniti_olustur([]), 'Ankara');

    fwrite(STDERR, "HATA (bos yanit): hata firlamadi.\n");
    exit(1);
} catch (GoogleAdsKesifHatasi $hata) {
    if (
        $hata->kategori !== 'girdi'
        || $hata->getMessage() !== 'Girilen konum bulunamadı; farklı yazmayı deneyin.'
    ) {
        fwrite(STDERR, sprintf(
            "HATA (bos yanit): beklenen mesaj gelmedi: %s\n",
            $hata->getMessage()
        ));
        exit(1);
    }

    $vaka_sayisi++;
}

// 7) null sabit ve gecersiz kaynak adli oneriler sessizce atlanir.
$bos_sabitsiz = new GeoTargetConstantSuggestion();
$gecersiz_kaynakli = konum_onerisi_olustur(
    (new GeoTargetConstant())
        ->setResourceName('geoTargetConstants/abc')
        ->setName('Ankara')
        ->setTargetType('Province')
        ->setCanonicalName('Ankara,Ankara,Turkey')
);

try {
    google_ads_konum_yanitini_isle(
        konum_yaniti_olustur([$bos_sabitsiz, $gecersiz_kaynakli]),
        'Ankara'
    );

    fwrite(STDERR, "HATA (gecersiz oneriler): hata firlamadi.\n");
    exit(1);
} catch (GoogleAdsKesifHatasi $hata) {
    if (mb_strpos($hata->getMessage(), 'Girilen konum bulunamadı') === false) {
        fwrite(STDERR, sprintf(
            "HATA (gecersiz oneriler): beklenen mesaj gelmedi: %s\n",
            $hata->getMessage()
        ));
        exit(1);
    }

    $vaka_sayisi++;
}

// 8) GOREV-A SDK dogrulamasi: temp kaynak adlarini atamak icin uc resource
//    sinifinin da setResourceName metodu mevcut olmali (V25 vendor).
foreach (
    [
        \Google\Ads\GoogleAds\V25\Resources\CampaignBudget::class,
        \Google\Ads\GoogleAds\V25\Resources\Campaign::class,
        \Google\Ads\GoogleAds\V25\Resources\AdGroup::class,
    ] as $sinif_adi
) {
    if (!method_exists($sinif_adi, 'setResourceName')) {
        fwrite(STDERR, sprintf(
            "HATA (SDK): %s sinifinda setResourceName metodu yok.\n",
            $sinif_adi
        ));
        exit(1);
    }
}

// 9) GOREV-A kaynak taramasi: her temp ID referansinin tam bir resource_name
//    tanimi olmali (1 butce + 1 kampanya + 1 reklam grubu).
$adapter_kod = file_get_contents(__DIR__ . '/../php/baglayici/google-ads-baglayici.php');

if ($adapter_kod === false) {
    fwrite(STDERR, "HATA (kaynak tarama): adapter dosyasi okunamadi.\n");
    exit(1);
}

foreach ([
    "setResourceName(\$on_ek . '/campaignBudgets/-1')" => 1,
    "setResourceName(\$on_ek . '/campaigns/-2')" => 1,
    "setResourceName(\$on_ek . '/adGroups/-3')" => 1,
    "'/campaignBudgets/-1'" => 2,
    "'/campaigns/-2'" => 4,
    "'/adGroups/-3'" => 3,
] as $desen => $beklenen_adet) {
    $adet = substr_count($adapter_kod, $desen);

    if ($adet !== $beklenen_adet) {
        fwrite(STDERR, sprintf(
            "HATA (Gorev-A kaynak tarama): '%s' %d kez bulundu, %d bekleniyordu.\n",
            $desen,
            $adet,
            $beklenen_adet
        ));
        exit(1);
    }
}

$vaka_sayisi++;

printf(
    "PROMPT-20.3 konum oneri yanit testleri: PASS (%d vaka)\n",
    $vaka_sayisi
);