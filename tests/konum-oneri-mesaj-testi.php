<?php

/**
 * PROMPT-20.3/20.4/21 sentetik testi: suggestGeoTargetConstants yanit isleme,
 * belirsiz konum aday listesi, secim kullanilabilirlik, yaklasik toplam butce
 * bitis tarihi ve TTPA zorunlu alan dogrulamasi.
 *
 * Calistirma: php tests/konum-oneri-mesaj-testi.php
 * Google Ads API cagrisi yapmaz; sentetik SuggestGeoTargetConstantsResponse
 * nesneleriyle saf fonksiyonlar test edilir; kaynak taramalariyla mutate akisi
 * dogrulanir.
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
    'durum' => 'tek',
    'konum' => [
        'resource_name' => 'geoTargetConstants/110419',
        'name' => 'Ankara',
        'target_type' => 'Province',
        'canonical_name' => 'Ankara,Ankara,Turkey',
    ],
    'adaylar' => [],
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

// 2) PROMPT-21: belirsiz cok eslesme artik hata FIRLATMAZ; 'belirsiz' durumuyla
//    adaylarin tam listesi doner. Tam eslesme olmayan oneri (Ankaray) listede
//    yer almaz; secim kullanicidan istenir (sessiz best-match yok).
$belirsiz_sonuc = google_ads_konum_yanitini_isle(
    konum_yaniti_olustur([
        konum_onerisi_olustur($sabit_il),
        konum_onerisi_olustur($sabit_ilce),
        $yakin_oneri,
    ]),
    'Ankara'
);

if (
    ($belirsiz_sonuc['durum'] ?? '') !== 'belirsiz'
    || $belirsiz_sonuc['konum'] !== null
) {
    fwrite(STDERR, "HATA (belirsiz cok eslesme): durum 'belirsiz' ve konum null bekleniyordu.\n");
    exit(1);
}

if (count($belirsiz_sonuc['adaylar']) !== 2) {
    fwrite(STDERR, sprintf(
        "HATA (belirsiz cok eslesme): 2 aday beklenirken %d geldi.\n",
        count($belirsiz_sonuc['adaylar'])
    ));
    exit(1);
}

if (
    $belirsiz_sonuc['adaylar'][0]['resource_name'] !== 'geoTargetConstants/110419'
    || $belirsiz_sonuc['adaylar'][0]['target_type'] !== 'Province'
    || $belirsiz_sonuc['adaylar'][0]['canonical_name'] !== 'Ankara,Ankara,Turkey'
    || $belirsiz_sonuc['adaylar'][1]['resource_name'] !== 'geoTargetConstants/110421'
    || $belirsiz_sonuc['adaylar'][1]['target_type'] !== 'County'
    || $belirsiz_sonuc['adaylar'][1]['canonical_name'] !== 'Ankara,Kızılcahamam,Turkey'
) {
    fwrite(STDERR, "HATA (belirsiz cok eslesme): aday listesi beklenenden farkli.\n");
    exit(1);
}

foreach ($belirsiz_sonuc['adaylar'] as $aday) {
    if ($aday['name'] !== 'Ankara') {
        fwrite(STDERR, sprintf(
            "HATA (belirsiz cok eslesme): tam eslesme olmayan aday listede: %s\n",
            $aday['name']
        ));
        exit(1);
    }
}

$vaka_sayisi++;

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

// 3) PROMPT-21: belirsiz ve canonical_name'siz adaylar — aday listesinde
//    canonical_name bos string olarak döner; secim bilgisiyle kullaniciya
//    gosterilir, sessiz best-match yapilmaz.
$kanoniksiz_sonuc = google_ads_konum_yanitini_isle(
    konum_yaniti_olustur([
        konum_onerisi_olustur($kanoniksiz_il),
        konum_onerisi_olustur($kanoniksiz_ilce),
    ]),
    'Ankara'
);

if (
    ($kanoniksiz_sonuc['durum'] ?? '') !== 'belirsiz'
    || count($kanoniksiz_sonuc['adaylar']) !== 2
    || $kanoniksiz_sonuc['adaylar'][0]['target_type'] !== 'Province'
    || $kanoniksiz_sonuc['adaylar'][1]['target_type'] !== 'County'
) {
    fwrite(STDERR, "HATA (kanoniksiz belirsizlik): aday listesi beklenenden farkli.\n");
    exit(1);
}

foreach ($kanoniksiz_sonuc['adaylar'] as $aday) {
    if ($aday['canonical_name'] !== '') {
        fwrite(STDERR, "HATA (kanoniksiz belirsizlik): canonical_name bos degil.\n");
        exit(1);
    }
}

$vaka_sayisi++;

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

// 4) PROMPT-21: 6 adayli belirsizlik — aday listesi TAM olarak doner (eskiden
//    hata mesaji 5 adayla sinirliydi; artik secim listesinin tamami gider).
$cok_aday_sonuc = google_ads_konum_yanitini_isle(
    konum_yaniti_olustur($cok_aday),
    'Ankara'
);

if (
    ($cok_aday_sonuc['durum'] ?? '') !== 'belirsiz'
    || count($cok_aday_sonuc['adaylar']) !== 6
) {
    fwrite(STDERR, sprintf(
        "HATA (6 aday): 'belirsiz' durum ve 6 aday beklenirken %d geldi.\n",
        count($cok_aday_sonuc['adaylar'] ?? [])
    ));
    exit(1);
}

foreach ($cok_aday_sonuc['adaylar'] as $indeks => $aday) {
    if ($aday['canonical_name'] !== 'Ankara,Ilce' . ($indeks + 1) . ',Turkey') {
        fwrite(STDERR, sprintf(
            "HATA (6 aday): %d. aday canonical_name beklenenden farkli.\n",
            $indeks + 1
        ));
        exit(1);
    }
}

$vaka_sayisi++;

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
    "'/campaigns/-2'" => 5,
    "'/adGroups/-3'" => 3,
    "use Google\\Ads\\GoogleAds\\V25\\Enums\\EuPoliticalAdvertisingStatusEnum\\EuPoliticalAdvertisingStatus;" => 1,
    "EuPoliticalAdvertisingStatus::DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING" => 1,
    "setContainsEuPoliticalAdvertising(" => 1,
    "\$sonuclar = \$yanit->getMutateOperationResponses();" => 1,
    "->getResults()" => 1,
    '->setNegative(true)' => 1,
    '->setEndDateTime(' => 1,
    "'durum' => 'tek'" => 1,
    "'durum' => 'belirsiz'" => 1,
    'Hariç tutulan konum kaynak adı geçersiz.' => 1,
    'Kampanya bitiş tarihi geçersiz (beklenen format: YYYY-MM-DD).' => 1,
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

// 10) GOREV-B (PROMPT-20.4): TTPA zorunlu kampanya alani dogrulamasi.
//     Enum ve sabit vendor'da mevcut olmali (V25); adapter'da sabit deger
//     tam olarak bir kez beyan edilmeli.
$eu_reklam_enum = \Google\Ads\GoogleAds\V25\Enums\EuPoliticalAdvertisingStatusEnum\EuPoliticalAdvertisingStatus::class;

if (!class_exists($eu_reklam_enum)) {
    fwrite(STDERR, "HATA (SDK): EuPoliticalAdvertisingStatus enum sinifi vendor'da yok.\n");
    exit(1);
}

if (!defined($eu_reklam_enum . '::DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING')) {
    fwrite(STDERR, "HATA (SDK): DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING sabiti vendor'da yok.\n");
    exit(1);
}

$vaka_sayisi++;

// 11) GOREV-B (PROMPT-20.5): Mutate yaniti okuma dogrulamasi.
//     MutateGoogleAdsResponse getResults() sunmaz; getMutateOperationResponses()
//     kullanilir. Her yanit elemani MutateOperationResponse olup tipine gore
//     getCampaignResult() vb. getter'lar sunar (vendor V25'ten dogrulandi).
//     Sentetik yanitla operation sirasi -> index-1 = kampanya varsayimi da
//     test edilir; hicbir API cagrisi yapilmaz.
$mutate_yanit_sinifi = \Google\Ads\GoogleAds\V25\Services\MutateGoogleAdsResponse::class;
$mutate_islem_yaniti_sinifi = \Google\Ads\GoogleAds\V25\Services\MutateOperationResponse::class;

if (!class_exists($mutate_yanit_sinifi) || !class_exists($mutate_islem_yaniti_sinifi)) {
    fwrite(STDERR, "HATA (SDK): MutateGoogleAdsResponse/MutateOperationResponse vendor'da yok.\n");
    exit(1);
}

if (method_exists($mutate_yanit_sinifi, 'getResults')) {
    fwrite(STDERR, "HATA (SDK): MutateGoogleAdsResponse beklenmedik getResults() sunuyor.\n");
    exit(1);
}

foreach (
    [
        'getPartialFailureError',
        'getMutateOperationResponses',
        'setMutateOperationResponses',
    ] as $metot) {
    if (!method_exists($mutate_yanit_sinifi, $metot)) {
        fwrite(STDERR, sprintf(
            "HATA (SDK): %s sinifinda %s metodu yok.\n",
            $mutate_yanit_sinifi,
            $metot
        ));
        exit(1);
    }
}

foreach (
    [
        'getCampaignBudgetResult',
        'getCampaignResult',
        'getCampaignCriterionResult',
        'getAdGroupResult',
        'getAdGroupCriterionResult',
        'getAdGroupAdResult',
    ] as $getter) {
    if (!method_exists($mutate_islem_yaniti_sinifi, $getter)) {
        fwrite(STDERR, sprintf(
            "HATA (SDK): MutateOperationResponse sinifinda %s getter'i yok.\n",
            $getter
        ));
        exit(1);
    }
}

$sentetik_yanit = new $mutate_yanit_sinifi();
$sentetik_yanit->setMutateOperationResponses([
    (new $mutate_islem_yaniti_sinifi())->setCampaignBudgetResult(
        (new \Google\Ads\GoogleAds\V25\Services\MutateCampaignBudgetResult())
            ->setResourceName('customers/1234567890/campaignBudgets/111')
    ),
    (new $mutate_islem_yaniti_sinifi())->setCampaignResult(
        (new \Google\Ads\GoogleAds\V25\Services\MutateCampaignResult())
            ->setResourceName('customers/1234567890/campaigns/222')
    ),
    (new $mutate_islem_yaniti_sinifi())->setCampaignCriterionResult(
        (new \Google\Ads\GoogleAds\V25\Services\MutateCampaignCriterionResult())
            ->setResourceName('customers/1234567890/campaignCriteria/222~333')
    ),
    (new $mutate_islem_yaniti_sinifi())->setAdGroupResult(
        (new \Google\Ads\GoogleAds\V25\Services\MutateAdGroupResult())
            ->setResourceName('customers/1234567890/adGroups/444')
    ),
    (new $mutate_islem_yaniti_sinifi())->setAdGroupCriterionResult(
        (new \Google\Ads\GoogleAds\V25\Services\MutateAdGroupCriterionResult())
            ->setResourceName('customers/1234567890/adGroupCriteria/444~555')
    ),
    (new $mutate_islem_yaniti_sinifi())->setAdGroupAdResult(
        (new \Google\Ads\GoogleAds\V25\Services\MutateAdGroupAdResult())
            ->setResourceName('customers/1234567890/adGroupAds/444~666')
    ),
]);

// Adapter'daki okuma mantiginin aynisi: operation sirasi (butce -> kampanya ->
// kampanya kriteri -> reklam grubu -> kelimeler -> reklam) yant dizisini de
// ayni sirayla dondurdugu icin index-1 kampanya sonucunu tasir.
$sentetik_sonuclar = $sentetik_yanit->getMutateOperationResponses();
$sentetik_kaynak = '';

if (
    count($sentetik_sonuclar) === 6
    && $sentetik_sonuclar[1]->getCampaignResult() !== null
) {
    $sentetik_kaynak = (string) $sentetik_sonuclar[1]->getCampaignResult()
        ->getResourceName();
}

if (
    $sentetik_kaynak !== 'customers/1234567890/campaigns/222'
    || preg_match('/^customers\/[0-9]+\/campaigns\/[0-9]+$/', $sentetik_kaynak) !== 1
) {
    fwrite(STDERR, "HATA (sentetik mutate yanit): index-1 kampanya kaynagi beklenen degil.\n");
    exit(1);
}

$vaka_sayisi++;

// 12) PROMPT-21 GOREV-A: secim kullanilabilirlik kontrolu (saf fonksiyon).
//     (c) gecerli kaynak + eslesen metin -> kullanilir; metin degistiyse,
//     format bozuksa veya bos secimse suggest yeniden yapilir.
if (kampanya_konum_secimi_kullanilabilir('geoTargetConstants/110419', 'Ankara', 'Ankara') !== true) {
    fwrite(STDERR, "HATA (Gorev-A): gecerli secim kullanilabilir olmali.\n");
    exit(1);
}

if (kampanya_konum_secimi_kullanilabilir('geoTargetConstants/110419', 'Ankara', 'İstanbul') !== false) {
    fwrite(STDERR, "HATA (Gorev-A): konum metni degistiginde secim gecersiz olmali.\n");
    exit(1);
}

if (kampanya_konum_secimi_kullanilabilir('geoTargetConstants/abc', 'Ankara', 'Ankara') !== false) {
    fwrite(STDERR, "HATA (Gorev-A): gecersiz kaynak adli secim kabul edilmemeli.\n");
    exit(1);
}

if (kampanya_konum_secimi_kullanilabilir('', 'Ankara', 'Ankara') !== false) {
    fwrite(STDERR, "HATA (Gorev-A): bos secim kabul edilmemeli.\n");
    exit(1);
}

$vaka_sayisi += 4;

// 13) PROMPT-21 GOREV-C: yaklasik toplam butce -> bitis tarihi (saf fonksiyon).
//     (e) toplam < gunluk -> dogrulama hatasi (sessizce yuvarlanmaz);
//     (f) gunluk 100 TL, toplam 1000 TL -> 10 gun sonrasi.
try {
    kampanya_bitis_tarihi_hesapla('2026-09-20', 50_000_000, 100_000_000);

    fwrite(STDERR, "HATA (Gorev-C): toplam < gunluk butce hatasiz kabul edildi.\n");
    exit(1);
} catch (InvalidArgumentException $hata) {
    if (mb_strpos($hata->getMessage(), 'Toplam bütçe günlük bütçeden küçük olamaz') === false) {
        fwrite(STDERR, sprintf(
            "HATA (Gorev-C): beklenen mesaj gelmedi: %s\n",
            $hata->getMessage()
        ));
        exit(1);
    }
}

if (kampanya_bitis_tarihi_hesapla('2026-09-20', 1000_000_000, 100_000_000) !== '2026-09-30') {
    fwrite(STDERR, "HATA (Gorev-C): 1000 TL / 100 TL icin 10 gun sonrasi bekleniyordu.\n");
    exit(1);
}

if (kampanya_bitis_tarihi_hesapla('2026-09-20', 100_000_000, 100_000_000) !== '2026-09-21') {
    fwrite(STDERR, "HATA (Gorev-C): esit butce icin 1 gun sonrasi bekleniyordu.\n");
    exit(1);
}

if (kampanya_bitis_tarihi_hesapla('2026-09-20', 999_000_000, 100_000_000) !== '2026-09-29') {
    fwrite(STDERR, "HATA (Gorev-C): floor(999/100)=9 gun sonrasi bekleniyordu.\n");
    exit(1);
}

$vaka_sayisi += 4;

// 14) PROMPT-21 kaynak taramasi: servis (konum secenekleri akisi, hariç
//     konumlar, bitis tarihi) ve sihirbaz (yeni alanlar + secim arayuzu).
//     PROMPT-23 CampaignService yanitinda getResults() kullanir; bu nedenle
//     servis taramasinda bu desenin bulunmasi beklenir.
$servis_kod = file_get_contents(__DIR__ . '/../php/servis/kampanya-servisi.php');
$sihirbaz_kod = file_get_contents(__DIR__ . '/../tema/panel/kampanya-sihirbazi.php');

if ($servis_kod === false || $sihirbaz_kod === false) {
    fwrite(STDERR, "HATA (kaynak tarama): servis/sihirbaz dosyasi okunamadi.\n");
    exit(1);
}

foreach ([
    'function kampanya_konum_secimi_kullanilabilir' => 1,
    'function kampanya_bitis_tarihi_hesapla' => 1,
    'function kampanya_konum_secimi_istegi_dondur' => 1,
    "'konum_secenekleri' =>" => 1,
    "'konum_secenekleri_baglam' =>" => 1,
    "'konum_secenekleri_metin' =>" => 1,
    'hedef_konum_resource_name' => 1,
    'haric_konum_resource_name' => 1,
    'hedef_konum_kaynak_metin' => 1,
    'haric_konum_kaynak_metin' => 1,
    'Aynı konum hem hedef hem hariç tutulan olamaz' => 1,
    "'haric_konum_kaynaklari' =>" => 1,
    "'bitis_tarihi' =>" => 1,
] as $desen => $beklenen_adet) {
    $adet = substr_count($servis_kod, $desen);

    if ($adet !== $beklenen_adet) {
        fwrite(STDERR, sprintf(
            "HATA (Gorev-A/B/C servis tarama): '%s' %d kez bulundu, %d bekleniyordu.\n",
            $desen,
            $adet,
            $beklenen_adet
        ));
        exit(1);
    }
}

$vaka_sayisi++;

foreach ([
    'name="haric_konumlar"' => 1,
    'name="toplam_butce"' => 1,
    'name="hedef_konum_resource_name"' => 2,
    'name="hedef_konum_kaynak_metin"' => 2,
    'name="haric_konum_resource_name"' => 2,
    'name="haric_konum_kaynak_metin"' => 2,
    'id="konum-secim"' => 1,
    'konum_secenekleri' => 6,
    'konum_secim' => 2,
    'kesin bir toplam harcama garantisi değildir' => 1,
] as $desen => $beklenen_adet) {
    $adet = substr_count($sihirbaz_kod, $desen);

    if ($adet !== $beklenen_adet) {
        fwrite(STDERR, sprintf(
            "HATA (Gorev-A/C sihirbaz tarama): '%s' %d kez bulundu, %d bekleniyordu.\n",
            $desen,
            $adet,
            $beklenen_adet
        ));
        exit(1);
    }
}

$vaka_sayisi++;

printf(
    "PROMPT-20.3/20.4/21 konum, TTPA ve yeni alan dogrulama testleri: PASS (%d vaka)\n",
    $vaka_sayisi
);