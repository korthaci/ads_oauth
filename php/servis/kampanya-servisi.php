<?php

/**
 * Dogrulanmis Google Ads non-manager hesabinin kampanyalarini read-only listeler.
 *
 * HTTP giris noktasi degildir; api/index.php tarafindan cagrilir.
 * Bu servis kampanyalar tablosuna yazmaz ve mutate cagrisi yapmaz.
 */

require_once dirname(__DIR__) . '/oturum.php';
require_once dirname(__DIR__) . '/sifreleme.php';
require_once dirname(__DIR__) . '/veritabani.php';
require_once dirname(__DIR__) . '/baglayici/google-ads-baglayici.php';

/**
 * Google baglantilarinin cagridan once/sonra guvenli durum snapshot'ini alir.
 * Refresh token degeri bu snapshot'a dahil edilmez.
 *
 * @return array{
 *     kayit_sayisi: int,
 *     aktif_kayit_no: ?int,
 *     harici_kimlik: ?string,
 *     aktif: ?int
 * }
 */
function google_baglanmis_hesap_snapshot_al(int $sahip_no): array
{
    $baglanti = veritabani_baglan();
    $say = $baglanti->prepare(
        'SELECT COUNT(*) FROM `baglanmis_hesaplar` '
        . 'WHERE `sahip_no` = :sahip_no AND `platform` = :platform'
    );
    $say->execute([
        'sahip_no' => $sahip_no,
        'platform' => 'google',
    ]);

    $son_kayit = $baglanti->prepare(
        'SELECT `no`, `harici_kimlik`, `aktif` FROM `baglanmis_hesaplar` '
        . 'WHERE `sahip_no` = :sahip_no AND `platform` = :platform '
        . 'AND `aktif` = 1 AND `refresh_token_sifreli` IS NOT NULL '
        . 'AND `refresh_token_sifreli` <> :bos_token '
        . 'ORDER BY `no` DESC LIMIT 1'
    );
    $son_kayit->execute([
        'sahip_no' => $sahip_no,
        'platform' => 'google',
        'bos_token' => '',
    ]);
    $kayit = $son_kayit->fetch();

    return [
        'kayit_sayisi' => (int) $say->fetchColumn(),
        'aktif_kayit_no' => is_array($kayit) && isset($kayit['no'])
            ? (int) $kayit['no']
            : null,
        'harici_kimlik' => is_array($kayit) && array_key_exists('harici_kimlik', $kayit)
            ? ($kayit['harici_kimlik'] === null ? null : (string) $kayit['harici_kimlik'])
            : null,
        'aktif' => is_array($kayit) && array_key_exists('aktif', $kayit)
            ? (int) $kayit['aktif']
            : null,
    ];
}

/**
 * Snapshot'in read-only cagridan sonra degismedigini kontrol eder.
 */
function google_baglanmis_hesap_snapshot_degisiklik_var_mi(
    array $once,
    array $sonra
): bool {
    return $once !== $sonra;
}

/**
 * Cagri oncesi/sonrasi baglanmis hesap durumunu credential icermeden doner.
 *
 * @return array{
 *     cagri_oncesi: array<string, mixed>,
 *     cagri_sonrasi: array<string, mixed>,
 *     degisti: bool
 * }
 */
function google_baglanmis_hesap_durumunu_raporla(
    array $once,
    array $sonra
): array {
    return [
        'cagri_oncesi' => $once,
        'cagri_sonrasi' => $sonra,
        'degisti' => google_baglanmis_hesap_snapshot_degisiklik_var_mi($once, $sonra),
    ];
}

/**
 * En guncel aktif Google OAuth baglantisini ve refresh token'ini alir.
 *
 * @return array{no: int, harici_kimlik: ?string, refresh_token_sifreli: string}|null
 */
function google_kampanya_baglantisini_al(int $sahip_no): ?array
{
    $sorgu = veritabani_baglan()->prepare(
        'SELECT `no`, `harici_kimlik`, `refresh_token_sifreli` '
        . 'FROM `baglanmis_hesaplar` '
        . 'WHERE `sahip_no` = :sahip_no AND `platform` = :platform '
        . 'AND `aktif` = 1 AND `refresh_token_sifreli` IS NOT NULL '
        . 'AND `refresh_token_sifreli` <> :bos_token '
        . 'ORDER BY `no` DESC LIMIT 1'
    );
    $sorgu->execute([
        'sahip_no' => $sahip_no,
        'platform' => 'google',
        'bos_token' => '',
    ]);
    $kayit = $sorgu->fetch();

    if (
        !is_array($kayit)
        || !isset($kayit['no'], $kayit['refresh_token_sifreli'])
    ) {
        return null;
    }

    return [
        'no' => (int) $kayit['no'],
        'harici_kimlik' => $kayit['harici_kimlik'] === null
            ? null
            : (string) $kayit['harici_kimlik'],
        'refresh_token_sifreli' => (string) $kayit['refresh_token_sifreli'],
    ];
}

/**
 * Aktif Google baglantisini gercek customer bilgisiyle dogrular ve gerekiyorsa
 * kampanyalari listeler. Bu akis yerel kampanyalar tablosuna yazmaz.
 *
 * @return array<string, mixed>
 */
function kampanyalari_listele(): array
{
    $sahip_no = oturum_sahip_no();

    if ($sahip_no === null || $sahip_no < 1) {
        return [
            'return' => 0,
            'mesaj' => 'Oturum gerekli.',
        ];
    }

    try {
        $once = google_baglanmis_hesap_snapshot_al($sahip_no);
        $baglanti = google_kampanya_baglantisini_al($sahip_no);
    } catch (Throwable $hata) {
        return [
            'return' => 0,
            'mesaj' => 'Bağlı Google Ads hesabı kontrol edilemedi.',
        ];
    }

    if ($baglanti === null) {
        return [
            'return' => 0,
            'mesaj' => 'Bağlı Google Ads hesabı bulunamadı.',
            'baglanmis_hesap_durumu' => google_baglanmis_hesap_durumunu_raporla(
                $once,
                $once
            ),
        ];
    }

    $customer_id = trim((string) ($baglanti['harici_kimlik'] ?? ''));

    if (preg_match('/^[1-9][0-9]*$/', $customer_id) !== 1) {
        return [
            'return' => 0,
            'mesaj' => 'Bağlanan Google Ads hesabının customer ID bilgisi bulunamadı.',
            'baglanmis_hesap_durumu' => google_baglanmis_hesap_durumunu_raporla(
                $once,
                $once
            ),
        ];
    }

    try {
        $refresh_token = coz($baglanti['refresh_token_sifreli']);

        if (trim($refresh_token) === '') {
            throw new GoogleAdsKesifHatasi(
                'Google OAuth kimlik bilgileri veya refresh token kullanılamadı.',
                'oauth'
            );
        }

        try {
            $customer = google_ads_musteri_bilgilerini_al($refresh_token, $customer_id);

            if ($customer['manager']) {
                $sonra = google_baglanmis_hesap_snapshot_al($sahip_no);

                return [
                    'return' => 1,
                    'mesaj' => $customer_id === '9530538405'
                        ? 'Bağlanan hesap hâlâ Manager hesabı; non-manager müşteri hesabı doğrulanmadı.'
                        : 'Bağlanan hesap Manager hesabı; kampanya listeleme yapılmadı.',
                    'hesap' => $customer,
                    'baglanmis_hesap_durumu' => google_baglanmis_hesap_durumunu_raporla(
                        $once,
                        $sonra
                    ),
                ];
            }

            $kampanyalar = google_ads_kampanyalari_listele($refresh_token, $customer_id);
        } finally {
            unset($refresh_token);
        }
    } catch (GoogleAdsKesifHatasi $hata) {
        try {
            $sonra = google_baglanmis_hesap_snapshot_al($sahip_no);
        } catch (Throwable $snapshot_hatasi) {
            $sonra = $once;
        }

        return [
            'return' => 0,
            'mesaj' => 'Google Ads hesabı veya kampanyaları sorgulanamadı.',
            'google_ads_hata_kategorisi' => $hata->kategori,
            'baglanmis_hesap_durumu' => google_baglanmis_hesap_durumunu_raporla(
                $once,
                $sonra
            ),
        ];
    } catch (Throwable $hata) {
        google_ads_hata_kaydi_yaz('kampanyalari_listele', $hata);

        try {
            $sonra = google_baglanmis_hesap_snapshot_al($sahip_no);
        } catch (Throwable $snapshot_hatasi) {
            $sonra = $once;
        }

        return [
            'return' => 0,
            'mesaj' => 'Google Ads hesabı veya kampanyaları sorgulanamadı.',
            'baglanmis_hesap_durumu' => google_baglanmis_hesap_durumunu_raporla(
                $once,
                $sonra
            ),
        ];
    }

    try {
        $sonra = google_baglanmis_hesap_snapshot_al($sahip_no);
    } catch (Throwable $hata) {
        return [
            'return' => 0,
            'mesaj' => 'Bağlı Google Ads hesabı sonrası kontrol edilemedi.',
        ];
    }

    return [
        'return' => 1,
        'mesaj' => count($kampanyalar) === 0
            ? 'Bu hesapta henüz kampanya yok.'
            : 'Google Ads kampanyaları başarıyla listelendi.',
        'hesap' => [
            'customer_id' => $customer['customer_id'],
            'descriptive_name' => $customer['descriptive_name'],
            'manager' => $customer['manager'],
            'status' => $customer['status'],
            'currency_code' => $customer['currency_code'],
            'time_zone' => $customer['time_zone'],
        ],
        'kampanyalar' => $kampanyalar,
        'baglanmis_hesap_durumu' => google_baglanmis_hesap_durumunu_raporla(
            $once,
            $sonra
        ),
    ];
}

/**
 * Kullanicinin serbest metnini satir ve virgul sinirlarina gore listeye ayirir.
 * Bos parcalar atilir; siralama korunur.
 *
 * @return array<int, string>
 */
function kampanya_metnini_listeye_ayir(string $metin): array
{
    $satirlar = preg_split('/\r\n|\r|\n/u', $metin) ?: [];
    $parcalar = [];

    foreach ($satirlar as $satir) {
        foreach (explode(',', $satir) as $parca) {
            $parcalar[] = trim($parca);
        }
    }

    return array_values(array_filter(
        $parcalar,
        static fn (string $parca): bool => $parca !== ''
    ));
}

/**
 * Kullanicinin TL cinsinden gunluk butce girdisini Google Ads
 * budget_amount_micros degerine cevirir (TL * 1.000.000).
 *
 * Float matematigi yerine string tabanli cevrim kullanilir; aksi halde
 * 19.99 gibi degerler 19989999 micros olarak yanlis hesaplanabilir. "500 TL"
 * gibi son ekler, ondalik virgul ("12,50") ve nokta ("12.50") kabul edilir.
 *
 * @throws InvalidArgumentException
 */
function kampanya_butcesini_microsa_cevir(mixed $girdi): int
{
    $metin = '';

    if (is_int($girdi) || is_float($girdi)) {
        $metin = (string) $girdi;
    } elseif (is_string($girdi)) {
        $metin = trim($girdi);
    }

    if ($metin === '') {
        throw new InvalidArgumentException('Günlük bütçe zorunludur.');
    }

    $metin = trim((string) preg_replace('/\s*(?:tl|₺)\s*$/iu', '', $metin));
    $metin = str_replace(',', '.', $metin);

    if (preg_match('/^(\d{1,12})(?:\.(\d{1,2}))?$/', $metin, $parcalar) !== 1) {
        throw new InvalidArgumentException(
            'Günlük bütçe geçerli bir sayı değil (örnek: 500 veya 12,50).'
        );
    }

    $ondalik = array_key_exists(2, $parcalar)
        ? str_pad($parcalar[2], 6, '0')
        : '000000';

    $micros = ((int) $parcalar[1]) * 1000000 + (int) $ondalik;

    if ($micros <= 0) {
        throw new InvalidArgumentException('Günlük bütçe 0’dan büyük olmalıdır.');
    }

    return $micros;
}


/**
 * Sihirbaz formundan gelen girdileri dogrular ve mutate planini uretir.
 *
 * Kurallar (PROMPT-20 §4): web sitesi URL (https:// eksikse eklenir), kampanya
 * adi 1-255 karakter, basliklar 3-15 adet ve her biri 30 karakter, aciklamalar
 * 2-4 adet ve her biri 90 karakter, anahtar kelimeler 1-20 adet ve her biri
 * 80 karakter, gunluk butce pozitif sayi, hedef konum 1-100 karakter.
 *
 * @return array{
 *     kampanya_adi: string,
 *     web_sitesi: string,
 *     basliklar: array<int, string>,
 *     aciklamalar: array<int, string>,
 *     anahtar_kelimeler: array<int, string>,
 *     butce_micros: int,
 *     gunluk_butce_tl: string,
 *     hedef_konum: string
 * }
 *
 * @throws InvalidArgumentException
 */
function kampanya_girdilerini_dogrula(array $girdiler): array
{
    $web_sitesi = trim((string) ($girdiler['web_sitesi'] ?? ''));

    if ($web_sitesi === '') {
        throw new InvalidArgumentException('Web sitesi zorunludur.');
    }

    if (preg_match('#^https?://#iu', $web_sitesi) !== 1) {
        $web_sitesi = 'https://' . $web_sitesi;
    }

    $host = parse_url($web_sitesi, PHP_URL_HOST);

    if (
        filter_var($web_sitesi, FILTER_VALIDATE_URL) === false
        || !is_string($host)
        || $host === ''
    ) {
        throw new InvalidArgumentException(
            'Web sitesi geçerli bir URL değil (örnek: ornek.com).'
        );
    }

    $kampanya_adi = trim((string) ($girdiler['kampanya_adi'] ?? ''));

    if ($kampanya_adi === '') {
        throw new InvalidArgumentException('Kampanya adı zorunludur.');
    }

    if (mb_strlen($kampanya_adi) > 255) {
        throw new InvalidArgumentException(
            'Kampanya adı en fazla 255 karakter olabilir.'
        );
    }

    $basliklar = kampanya_metnini_listeye_ayir(
        (string) ($girdiler['basliklar'] ?? '')
    );

    if (count($basliklar) < 3) {
        throw new InvalidArgumentException('En az 3 reklam başlığı gerekir.');
    }

    if (count($basliklar) > 15) {
        throw new InvalidArgumentException('En fazla 15 reklam başlığı kabul edilir.');
    }

    foreach ($basliklar as $baslik) {
        if (mb_strlen($baslik) > 30) {
            throw new InvalidArgumentException(
                'Reklam başlıkları en fazla 30 karakter olabilir: '
                . mb_substr($baslik, 0, 31)
            );
        }
    }

    $aciklamalar = kampanya_metnini_listeye_ayir(
        (string) ($girdiler['aciklamalar'] ?? '')
    );

    if (count($aciklamalar) < 2) {
        throw new InvalidArgumentException('En az 2 reklam açıklaması gerekir.');
    }

    if (count($aciklamalar) > 4) {
        throw new InvalidArgumentException('En fazla 4 reklam açıklaması kabul edilir.');
    }

    foreach ($aciklamalar as $aciklama) {
        if (mb_strlen($aciklama) > 90) {
            throw new InvalidArgumentException(
                'Reklam açıklamaları en fazla 90 karakter olabilir.'
            );
        }
    }

    $anahtar_kelimeler = kampanya_metnini_listeye_ayir(
        (string) ($girdiler['anahtar_kelimeler'] ?? '')
    );

    if (count($anahtar_kelimeler) < 1) {
        throw new InvalidArgumentException('En az 1 anahtar kelime gerekir.');
    }

    if (count($anahtar_kelimeler) > 20) {
        throw new InvalidArgumentException('En fazla 20 anahtar kelime kabul edilir.');
    }

    foreach ($anahtar_kelimeler as $kelime) {
        if (mb_strlen($kelime) > 80) {
            throw new InvalidArgumentException(
                'Anahtar kelimeler en fazla 80 karakter olabilir.'
            );
        }
    }

    $butce_micros = kampanya_butcesini_microsa_cevir(
        $girdiler['gunluk_butce'] ?? null
    );

    $hedef_konum = trim((string) ($girdiler['hedef_konum'] ?? ''));

    if ($hedef_konum === '') {
        throw new InvalidArgumentException('Hedef konum zorunludur.');
    }

    if (mb_strlen($hedef_konum) > 100) {
        throw new InvalidArgumentException(
            'Hedef konum en fazla 100 karakter olabilir.'
        );
    }

    return [
        'kampanya_adi' => $kampanya_adi,
        'web_sitesi' => $web_sitesi,
        'basliklar' => $basliklar,
        'aciklamalar' => $aciklamalar,
        'anahtar_kelimeler' => $anahtar_kelimeler,
        'butce_micros' => $butce_micros,
        'gunluk_butce_tl' => number_format($butce_micros / 1000000, 2, '.', ''),
        'hedef_konum' => $hedef_konum,
    ];
}

/**
 * Sihirbaz formuyla gelen verilerle bagli non-manager Google Ads hesabinda
 * yeni bir Search kampanyasi olusturur (PROMPT-20 akisi).
 *
 * Guvenlik sirasi: girdi dogrulama (saf) -> aktif baglanti okuma -> refresh
 * token cozme -> taze manager kontrolu (salt-okunur customer sorgusu) ->
 * konum cozumleme (salt-okunur suggestGeoTargetConstants) -> tek atomik
 * mutate istegi. Manager hesaba hicbir mutate denenmez; kampanya her zaman
 * PAUSED olusturulur.
 *
 * @param array<string, mixed> $girdiler
 *
 * @return array<string, mixed>
 */
function kampanya_olustur(array $girdiler): array
{
    $sahip_no = oturum_sahip_no();

    if ($sahip_no === null || $sahip_no < 1) {
        return [
            'return' => 0,
            'mesaj' => 'Kampanya oluşturmak için giriş yapmalısınız.',
        ];
    }

    try {
        $plan = kampanya_girdilerini_dogrula($girdiler);
    } catch (InvalidArgumentException $hata) {
        return [
            'return' => 0,
            'mesaj' => $hata->getMessage(),
        ];
    }

    try {
        $baglanti = google_kampanya_baglantisini_al($sahip_no);
    } catch (Throwable $hata) {
        return [
            'return' => 0,
            'mesaj' => 'Bağlı Google Ads hesabı kontrol edilemedi.',
        ];
    }

    if ($baglanti === null) {
        return [
            'return' => 0,
            'mesaj' => 'Bağlı Google Ads hesabı bulunamadı.',
        ];
    }

    $customer_id = trim((string) ($baglanti['harici_kimlik'] ?? ''));

    if (preg_match('/^[1-9][0-9]*$/', $customer_id) !== 1) {
        return [
            'return' => 0,
            'mesaj' => 'Bağlı Google Ads müşteri kimliği geçersiz.',
        ];
    }

    try {
        $refresh_token = coz((string) $baglanti['refresh_token_sifreli']);

        if (trim($refresh_token) === '') {
            throw new GoogleAdsKesifHatasi(
                'Google OAuth kimlik bilgileri veya refresh token kullanılamadı.',
                'oauth'
            );
        }

        $musteri = google_ads_musteri_bilgilerini_al($refresh_token, $customer_id);

        if (!empty($musteri['manager'])) {
            return [
                'return' => 0,
                'mesaj' => 'Bağlı hesap Manager (MCC) hesabı; kampanya yalnızca '
                    . 'non-manager müşteri hesabında oluşturulabilir.',
            ];
        }

        $konum = google_ads_konum_onerilerini_al($refresh_token, $plan['hedef_konum']);

        $sonuc = google_ads_kampanya_olustur($refresh_token, $customer_id, [
            'kampanya_adi' => $plan['kampanya_adi'],
            'butce_micros' => $plan['butce_micros'],
            'konum_kaynagi' => $konum['resource_name'],
            'basliklar' => $plan['basliklar'],
            'aciklamalar' => $plan['aciklamalar'],
            'anahtar_kelimeler' => $plan['anahtar_kelimeler'],
            'web_sitesi' => $plan['web_sitesi'],
        ]);
    } catch (GoogleAdsKesifHatasi $hata) {
        return [
            'return' => 0,
            'mesaj' => 'Kampanya oluşturulamadı.',
            'google_ads_hata_kategorisi' => $hata->kategori,
            'google_ads_hata' => $hata->getMessage(),
        ];
    } catch (Throwable $hata) {
        google_ads_hata_kaydi_yaz('kampanya_olustur', $hata);

        return [
            'return' => 0,
            'mesaj' => 'Kampanya oluşturulamadı.',
        ];
    } finally {
        unset($refresh_token);
    }

    // Google tarafinda basari saglandi; yerel kampanyalar kaydi yedek amacli
    // yazilir. Yerel yazma hatasi Google basarisini icermez ve non-fatal'dir.
    $yerel_kayit = 'yazildi';

    try {
        $ekle = veritabani_baglan()->prepare(
            'INSERT INTO `kampanyalar` '
            . '(`hesap_no`, `platform`, `harici_kampanya_id`, `kampanya_adi`, '
            . '`gunluk_butce`, `hedef_url`, `durum`) '
            . 'VALUES (:hesap_no, :platform, :harici_kampanya_id, :kampanya_adi, '
            . ':gunluk_butce, :hedef_url, :durum)'
        );
        $ekle->execute([
            'hesap_no' => (int) $baglanti['no'],
            'platform' => 'google',
            'harici_kampanya_id' => $sonuc['kampanya_id'],
            'kampanya_adi' => $plan['kampanya_adi'],
            'gunluk_butce' => $plan['gunluk_butce_tl'],
            'hedef_url' => $plan['web_sitesi'],
            'durum' => 'duraklatildi',
        ]);
    } catch (Throwable $hata) {
        google_ads_hata_kaydi_yaz('kampanya_olustur.yerel_kayit', $hata);
        $yerel_kayit = 'yazilamadi';
    }

    return [
        'return' => 1,
        'mesaj' => 'Kampanya duraklatılmış (PAUSED) olarak oluşturuldu. '
            . 'Yayına almak için ayrı bir onay adımı gerekiyor.',
        'kampanya_kaynagi' => $sonuc['kampanya_kaynagi'],
        'kampanya_id' => $sonuc['kampanya_id'],
        'durum' => 'PAUSED',
        'yerel_kayit' => $yerel_kayit,
    ];
}
