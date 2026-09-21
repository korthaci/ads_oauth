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
 * Reklam aciklamalarini yalnizca satir sonlarina gore listeye ayirir.
 * Virguller aciklama metninin parcasi olarak korunur; bos satirlar atilir.
 *
 * @return array<int, string>
 */
function kampanya_aciklamalarini_satirlara_ayir(string $metin): array
{
    $satirlar = preg_split('/\r\n|\r|\n/u', $metin) ?: [];
    $aciklamalar = [];

    foreach ($satirlar as $satir) {
        $aciklama = trim($satir);

        if ($aciklama !== '') {
            $aciklamalar[] = $aciklama;
        }
    }

    return $aciklamalar;
}

/**
 * Yaklasik toplam butceden kampanya bitis tarihini hesaplar (PROMPT-21 §3).
 *
 * bitis = bugun + floor(toplam_micros / gunluk_micros) gun. floor, micros
 * cinsinden tam sayi bolmesiyle (intdiv) hesaplanir; toplam butce gunluk
 * butceden kucukse InvalidArgumentException firlatir (sessizce 0/1 güne
 * yuvarlanmaz). Donen tarih kesin bir toplam harcama garantisi DEGILDIR;
 * yalnizca kampanyanin belirtilen gunde otomatik durmasini saglar (Google
 * gunluk butceyi bazi gunlerde asabilir ve ayi ortalayabilir).
 *
 * $bugun 'Y-m-d' formatinda verilmelidir; hesap deterministik olsun diye
 * UTC kabul edilir.
 *
 * @throws InvalidArgumentException
 */
function kampanya_bitis_tarihi_hesapla(
    string $bugun,
    int $toplam_micros,
    int $gunluk_micros
): string {
    if ($gunluk_micros <= 0) {
        throw new InvalidArgumentException('Günlük bütçe 0’dan büyük olmalıdır.');
    }

    if ($toplam_micros < $gunluk_micros) {
        throw new InvalidArgumentException(
            'Toplam bütçe günlük bütçeden küçük olamaz; en az bir günlük bütçeye '
            . 'eşit olmalı. Yaklaşık bitiş tarihi hesaplanamadı.'
        );
    }

    $gun = intdiv($toplam_micros, $gunluk_micros);

    if ($gun < 1) {
        $gun = 1;
    }

    $bugun_zamani = DateTime::createFromFormat('!Y-m-d', $bugun, new DateTimeZone('UTC'));

    if ($bugun_zamani === false) {
        throw new InvalidArgumentException('Bugünün tarihi çözümlenemedi.');
    }

    $bugun_zamani->modify('+' . $gun . ' days');

    return $bugun_zamani->format('Y-m-d');
}

/**
 * Kullanicinin onceki belirsizlik adiminda sectigi geoTargetConstant kaynak
 * adinin bu istekte kullanilabilir olup olmadigini soyler (PROMPT-21 §1.1.4).
 * Kaynak adi gecerli formatta olmali ve secimin hangi konum metni icin
 * yapildigi su an istenen metinle ayni olmali; metin degistiyse secim
 * gecersizdir ve suggest cagrisi yeniden yapilir.
 */
function kampanya_konum_secimi_kullanilabilir(
    string $secilen_kaynak,
    string $secilen_metin,
    string $aktif_metin
): bool {
    return $secilen_kaynak !== ''
        && preg_match('/^geoTargetConstants\/[0-9]+$/', $secilen_kaynak) === 1
        && $secilen_metin === $aktif_metin;
}

/**
 * Belirsiz konum icin frontend'e secenek listesi dondurur; mutate HIC
 * denenmez (PROMPT-21 §1.1.2).
 *
 * @param list<array{
 *     resource_name: string,
 *     name: string,
 *     target_type: string,
 *     canonical_name: string
 * }> $adaylar
 *
 * @return array<string, mixed>
 */
function kampanya_konum_secimi_istegi_dondur(
    string $metin,
    string $baglam,
    array $adaylar
): array {
    $secenekler = [];

    foreach ($adaylar as $aday) {
        $secenekler[] = [
            'resource_name' => (string) $aday['resource_name'],
            'ad' => (string) $aday['name'],
            'tip' => (string) $aday['target_type'],
            'canonical_name' => (string) $aday['canonical_name'],
        ];
    }

    return [
        'return' => 0,
        'mesaj' => 'Konum belirsiz, lütfen birini seçin.',
        'konum_secenekleri' => $secenekler,
        'konum_secenekleri_baglam' => $baglam,
        'konum_secenekleri_metin' => $metin,
    ];
}

/**
 * Kullanicinin TL cinsinden gunluk butce girdisini Google Ads
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
function kampanya_butcesini_microsa_cevir(mixed $girdi, string $etiket = 'Günlük bütçe'): int
{
    $metin = '';

    if (is_int($girdi) || is_float($girdi)) {
        $metin = (string) $girdi;
    } elseif (is_string($girdi)) {
        $metin = trim($girdi);
    }

    if ($metin === '') {
        throw new InvalidArgumentException($etiket . ' zorunludur.');
    }

    $metin = trim((string) preg_replace('/\s*(?:tl|₺)\s*$/iu', '', $metin));
    $metin = str_replace(',', '.', $metin);

    if (preg_match('/^(\d{1,12})(?:\.(\d{1,2}))?$/', $metin, $parcalar) !== 1) {
        throw new InvalidArgumentException(
            $etiket . ' geçerli bir sayı değil (örnek: 500 veya 12,50).'
        );
    }

    $ondalik = array_key_exists(2, $parcalar)
        ? str_pad($parcalar[2], 6, '0')
        : '000000';

    $micros = ((int) $parcalar[1]) * 1000000 + (int) $ondalik;

    if ($micros <= 0) {
        throw new InvalidArgumentException($etiket . ' 0’dan büyük olmalıdır.');
    }

    return $micros;
}


/**
 * Sihirbaz formundan gelen girdileri dogrular ve mutate planini uretir.
 *
 * Kurallar (PROMPT-20 §4, PROMPT-21): web sitesi URL (https:// eksikse eklenir),
 * kampanya adi 1-255 karakter, basliklar 3-15 adet ve her biri 30 karakter,
 * aciklamalar 2-4 adet ve her biri 90 karakter, anahtar kelimeler 1-20 adet ve
 * negatif anahtar kelimeler 0-20 adet ve her biri 80 karakter, gunluk butce pozitif sayi,
 * hedef konum 1-100 karakter,
 * haric tutulan konumlar 0-20 adet (opsiyonel; her biri 1-100 karakter),
 * toplam butce opsiyonel pozitif sayi (gunluk butceden kucukse bitis tarihi
 * hesaplarken reddedilir), bitis tarihi opsiyonel ve bugun/ileri tarih olmalidir.
 *
 * @return array{
 *     kampanya_adi: string,
 *     web_sitesi: string,
 *     basliklar: array<int, string>,
 *     aciklamalar: array<int, string>,
 *     anahtar_kelimeler: array<int, string>,
 *     negatif_anahtar_kelimeler: array<int, string>,
 *     butce_micros: int,
 *     gunluk_butce_tl: string,
 *     hedef_konum: string,
 *     haric_konumlar: list<string>,
 *     toplam_butce_micros: ?int,
 *     bitis_tarihi: ?string
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

    $aciklamalar = kampanya_aciklamalarini_satirlara_ayir(
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

    $negatif_anahtar_kelimeler = kampanya_metnini_listeye_ayir(
        (string) ($girdiler['negatif_anahtar_kelimeler'] ?? '')
    );

    if (count($negatif_anahtar_kelimeler) > 20) {
        throw new InvalidArgumentException(
            'En fazla 20 negatif anahtar kelime kabul edilir.'
        );
    }

    foreach ($negatif_anahtar_kelimeler as $kelime) {
        if (mb_strlen($kelime) > 80) {
            throw new InvalidArgumentException(
                'Negatif anahtar kelimeler en fazla 80 karakter olabilir.'
            );
        }
    }

    $pozitif_kelime_indeksi = [];

    foreach ($anahtar_kelimeler as $kelime) {
        $pozitif_kelime_indeksi[mb_strtolower(trim($kelime), 'UTF-8')] = true;
    }

    foreach ($negatif_anahtar_kelimeler as $kelime) {
        if (isset($pozitif_kelime_indeksi[mb_strtolower(trim($kelime), 'UTF-8')])) {
            throw new InvalidArgumentException(
                'Aynı kelime hem pozitif hem negatif anahtar kelime olamaz: ' . $kelime
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

    // PROMPT-21: opsiyonel hariç tutulan konumlar — her biri hedef konumla aynı
    // çözümleme mantığından geçer; her biri en fazla 100 karakter, toplamda en
    // fazla 20 adet.
    $haric_konumlar = kampanya_metnini_listeye_ayir(
        (string) ($girdiler['haric_konumlar'] ?? '')
    );

    if (count($haric_konumlar) > 20) {
        throw new InvalidArgumentException(
            'En fazla 20 hariç tutulan konum kabul edilir.'
        );
    }

    foreach ($haric_konumlar as $haric_konum) {
        if (mb_strlen($haric_konum) > 100) {
            throw new InvalidArgumentException(
                'Hariç tutulan konumlar en fazla 100 karakter olabilir.'
            );
        }
    }

    // PROMPT-21: opsiyonel yaklaşık toplam bütçe (TL). Boşsa kampanya bitiş
    // tarihsiz (süresiz) oluşturulur; doldurulursa end_date hesaplanır.
    $toplam_butce_metni = trim((string) ($girdiler['toplam_butce'] ?? ''));
    $toplam_butce_micros = $toplam_butce_metni === ''
        ? null
        : kampanya_butcesini_microsa_cevir($toplam_butce_metni, 'Toplam bütçe');

    $bitis_tarihi = trim((string) ($girdiler['bitis_tarihi'] ?? ''));

    if ($bitis_tarihi !== '') {
        $tarih = DateTimeImmutable::createFromFormat('!Y-m-d', $bitis_tarihi);
        $tarih_hatalari = DateTimeImmutable::getLastErrors();

        if (
            $tarih === false
            || ($tarih_hatalari !== false
                && ($tarih_hatalari['warning_count'] > 0 || $tarih_hatalari['error_count'] > 0))
            || $tarih->format('Y-m-d') !== $bitis_tarihi
        ) {
            throw new InvalidArgumentException(
                'Bitiş tarihi geçerli bir tarih olmalıdır (YYYY-MM-DD).'
            );
        }

        if ($tarih < new DateTimeImmutable('today')) {
            throw new InvalidArgumentException(
                'Bitiş tarihi bugün veya daha ileri bir tarih olmalıdır.'
            );
        }
    } else {
        $bitis_tarihi = null;
    }

    return [
        'kampanya_adi' => $kampanya_adi,
        'web_sitesi' => $web_sitesi,
        'basliklar' => $basliklar,
        'aciklamalar' => $aciklamalar,
        'anahtar_kelimeler' => $anahtar_kelimeler,
        'negatif_anahtar_kelimeler' => $negatif_anahtar_kelimeler,
        'butce_micros' => $butce_micros,
        'gunluk_butce_tl' => number_format($butce_micros / 1000000, 2, '.', ''),
        'hedef_konum' => $hedef_konum,
        'haric_konumlar' => $haric_konumlar,
        'toplam_butce_micros' => $toplam_butce_micros,
        'bitis_tarihi' => $bitis_tarihi,
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
 * PAUSED olusturulur. Konum belirsizse (birden fazla tam eslesme) mutate HIC
 * denenmez; cagiran tarafa aday listesi (konum_secenekleri) dondurulur ve
 * kullanici secimi frontend'de alinir (PROMPT-21).
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

        // PROMPT-21 §1.1.3: kullanıcının önceki belirsizlik adımında seçtiği
        // kaynak ad yalnızca hedef konum metni değişmemişse kullanılır; metin
        // değiştiyse seçim geçersizdir ve suggest çağrısı yeniden yapılır.
        $secilen_hedef_kaynagi = trim(
            (string) ($girdiler['hedef_konum_resource_name'] ?? '')
        );
        $secilen_hedef_metni = trim(
            (string) ($girdiler['hedef_konum_kaynak_metin'] ?? '')
        );

        if (
            kampanya_konum_secimi_kullanilabilir(
                $secilen_hedef_kaynagi,
                $secilen_hedef_metni,
                $plan['hedef_konum']
            )
        ) {
            $hedef_konum = [
                'resource_name' => $secilen_hedef_kaynagi,
                'name' => $plan['hedef_konum'],
                'target_type' => 'kullanici_secimi',
                'canonical_name' => '',
            ];
        } else {
            $konum_cozumu = google_ads_konum_onerilerini_al(
                $refresh_token,
                $plan['hedef_konum']
            );

            if ($konum_cozumu['durum'] === 'belirsiz') {
                return kampanya_konum_secimi_istegi_dondur(
                    $plan['hedef_konum'],
                    'hedef',
                    $konum_cozumu['adaylar']
                );
            }

            $hedef_konum = $konum_cozumu['konum'];
        }

        // PROMPT-21 §2.2: her hariç tutulan konum hedef konumla aynı çözümleme
        // mantığından geçer. Basit tutulur: ilk belirsiz olan için seçim istenir,
        // o çözülünce bir sonraki istekte varsa diğer belirsiz konum istenir.
        $haric_konum_kaynaklari = [];

        foreach ($plan['haric_konumlar'] as $haric_metin) {
            $secilen_haric_kaynagi = trim(
                (string) ($girdiler['haric_konum_resource_name'] ?? '')
            );
            $secilen_haric_metni = trim(
                (string) ($girdiler['haric_konum_kaynak_metin'] ?? '')
            );

            if (
                kampanya_konum_secimi_kullanilabilir(
                    $secilen_haric_kaynagi,
                    $secilen_haric_metni,
                    $haric_metin
                )
            ) {
                $haric_konum_kaynaklari[] = $secilen_haric_kaynagi;
                continue;
            }

            $haric_cozumu = google_ads_konum_onerilerini_al(
                $refresh_token,
                $haric_metin
            );

            if ($haric_cozumu['durum'] === 'belirsiz') {
                return kampanya_konum_secimi_istegi_dondur(
                    $haric_metin,
                    'haric',
                    $haric_cozumu['adaylar']
                );
            }

            $haric_konum_kaynaklari[] = $haric_cozumu['konum']['resource_name'];
        }

        // PROMPT-21 §2.4: hedef konumla aynı bir yer hariç tutulmaya
        // çalışılırsa anlamlı hata; sessizce yok sayılmaz.
        foreach ($plan['haric_konumlar'] as $indeks => $haric_metin) {
            if ($haric_konum_kaynaklari[$indeks] === $hedef_konum['resource_name']) {
                return [
                    'return' => 0,
                    'mesaj' => 'Aynı konum hem hedef hem hariç tutulan olamaz: '
                        . $haric_metin . '. Farklı bir hariç konum yazın.',
                ];
            }
        }

        if (
            count(array_unique($haric_konum_kaynaklari))
            !== count($haric_konum_kaynaklari)
        ) {
            return [
                'return' => 0,
                'mesaj' => 'Hariç tutulan konumlar arasında aynı konum birden '
                    . 'fazla kez geçiyor; listeyi düzeltin.',
            ];
        }

        // Açıkça girilen tarih önceliklidir. Tarih boş bırakılırsa mevcut
        // toplam bütçe -> yaklaşık bitiş tarihi davranışı korunur.
        $bitis_tarihi = $plan['bitis_tarihi'];

        if ($bitis_tarihi === null && $plan['toplam_butce_micros'] !== null) {
            try {
                $bitis_tarihi = kampanya_bitis_tarihi_hesapla(
                    date('Y-m-d'),
                    $plan['toplam_butce_micros'],
                    $plan['butce_micros']
                );
            } catch (InvalidArgumentException $hata) {
                return [
                    'return' => 0,
                    'mesaj' => $hata->getMessage(),
                ];
            }
        }

        $sonuc = google_ads_kampanya_olustur($refresh_token, $customer_id, [
            'kampanya_adi' => $plan['kampanya_adi'],
            'butce_micros' => $plan['butce_micros'],
            'konum_kaynagi' => $hedef_konum['resource_name'],
            'haric_konum_kaynaklari' => $haric_konum_kaynaklari,
            'bitis_tarihi' => $bitis_tarihi,
            'basliklar' => $plan['basliklar'],
            'aciklamalar' => $plan['aciklamalar'],
            'anahtar_kelimeler' => $plan['anahtar_kelimeler'],
            'negatif_anahtar_kelimeler' => $plan['negatif_anahtar_kelimeler'],
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

/**
 * Tek bir kampanyanin status alanini ENABLED, PAUSED veya REMOVED yapar (PROMPT-23/24).
 *
 * Guvenlik akisi: girdi dogrulama -> oturum -> aktif baglanti -> TAZE manager
 * kontrolu (mutate oncesi; manager hesaba asla mutate denmez) -> kampanyanin
 * bu hesaba aidiyeti salt-okunur sorguyla dogrulanir (ID baska hesaba aitse
 * veya yoksa mutate HIC denenmez) -> tek update mutate (yalnizca status).
 *
 * Girdi: kampanya_id (zorunlu), hedef_durum (yalnizca 'ENABLED'/'PAUSED'/'REMOVED';
 * baska deger — buyuk/kucuk harf farkli dahil — kabul edilmez).
 *
 * @param array<string, mixed> $istek
 * @return array<string, mixed>
 */
function kampanya_durumunu_degistir(array $istek): array
{
    $hedef_durum = is_string($istek['hedef_durum'] ?? null)
        ? trim($istek['hedef_durum'])
        : '';

    if ($hedef_durum !== 'ENABLED' && $hedef_durum !== 'PAUSED' && $hedef_durum !== 'REMOVED') {
        return [
            'return' => 0,
            'mesaj' => 'Hedef durum geçersiz; yalnızca ENABLED, PAUSED veya REMOVED kabul edilir.',
        ];
    }

    $kampanya_id = trim((string) ($istek['kampanya_id'] ?? ''));

    if (preg_match('/^[1-9][0-9]*$/', $kampanya_id) !== 1) {
        return [
            'return' => 0,
            'mesaj' => 'Geçerli bir kampanya ID belirtilmedi.',
        ];
    }

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
            // Taze manager kontrolü: her mutate öncesi tekrarlanır (PROMPT-16).
            $customer = google_ads_musteri_bilgilerini_al($refresh_token, $customer_id);

            if ($customer['manager']) {
                $sonra = google_baglanmis_hesap_snapshot_al($sahip_no);

                return [
                    'return' => 0,
                    'mesaj' => 'Bağlı hesap Manager hesabı; kampanyaya müdahale edilmedi.',
                    'hesap' => $customer,
                    'baglanmis_hesap_durumu' => google_baglanmis_hesap_durumunu_raporla(
                        $once,
                        $sonra
                    ),
                ];
            }

            // Sahiplik doğrulaması: ID bu hesapta yoksa mutate hiç denenmez.
            $ayrinti = google_ads_kampanya_ayrintilari_al(
                $refresh_token,
                $customer_id,
                $kampanya_id
            );

            if ($ayrinti === null) {
                $sonra = google_baglanmis_hesap_snapshot_al($sahip_no);

                return [
                    'return' => 0,
                    'mesaj' => 'Bu kampanya bağlı hesapta bulunamadı; durum değiştirilmedi.',
                    'baglanmis_hesap_durumu' => google_baglanmis_hesap_durumunu_raporla(
                        $once,
                        $sonra
                    ),
                ];
            }

            if ($hedef_durum === 'REMOVED') {
                $kaldirma_onayi = is_string($istek['kaldirma_onayi'] ?? null)
                    ? $istek['kaldirma_onayi']
                    : '';

                if ($kaldirma_onayi !== $ayrinti['name']) {
                    $sonra = google_baglanmis_hesap_snapshot_al($sahip_no);

                    return [
                        'return' => 0,
                        'mesaj' => 'Kaldırma işlemi için kampanya adını tam olarak yazmalısınız; kampanya kaldırılmadı.',
                        'baglanmis_hesap_durumu' => google_baglanmis_hesap_durumunu_raporla(
                            $once,
                            $sonra
                        ),
                    ];
                }
            }

            $sonuc = google_ads_kampanya_durumunu_degistir(
                $refresh_token,
                $customer_id,
                $kampanya_id,
                $hedef_durum
            );
        } finally {
            unset($refresh_token);
        }
    } catch (GoogleAdsKesifHatasi $hata) {
        return [
            'return' => 0,
            'mesaj' => 'Kampanya durumu değiştirilemedi.',
            'google_ads_hata_kategorisi' => $hata->kategori,
            'google_ads_hata' => $hata->getMessage(),
        ];
    } catch (Throwable $hata) {
        google_ads_hata_kaydi_yaz('kampanya_durumunu_degistir', $hata);

        return [
            'return' => 0,
            'mesaj' => 'Kampanya durumu değiştirilemedi.',
        ];
    }

    $sonra = google_baglanmis_hesap_snapshot_al($sahip_no);

    // Yerel kampanyalar kaydina non-fatal yansima (PROMPT-20 deseni; yerel
    // yazma hatasi Google basarisini icermez).
    $yerel_kayit = 'yazildi';

    try {
        $guncelle = veritabani_baglan()->prepare(
            'UPDATE `kampanyalar` SET `durum` = :durum '
            . 'WHERE `hesap_no` = :hesap_no AND `platform` = :platform '
            . 'AND `harici_kampanya_id` = :kampanya_id'
        );
        $guncelle->execute([
            'durum' => $hedef_durum === 'ENABLED'
                ? 'yayinda'
                : ($hedef_durum === 'REMOVED' ? 'kaldirildi' : 'duraklatildi'),
            'hesap_no' => (int) $baglanti['no'],
            'platform' => 'google',
            'kampanya_id' => $sonuc['kampanya_id'],
        ]);
    } catch (Throwable $hata) {
        google_ads_hata_kaydi_yaz('kampanya_durumunu_degistir.yerel_kayit', $hata);
        $yerel_kayit = 'yazilamadi';
    }

    return [
        'return' => 1,
        'mesaj' => $hedef_durum === 'ENABLED'
            ? 'Kampanya yayına alındı (ENABLED). Reklamların yayına girmesi '
                . 'Google tarafında birkaç dakika sürebilir; gerçek harcama '
                . 'başlamıştır.'
            : ($hedef_durum === 'REMOVED'
                ? 'Kampanya kalıcı olarak kaldırıldı (REMOVED); bu işlem geri alınamaz.'
                : 'Kampanya duraklatıldı (PAUSED); harcama durduruldu.'),
        'kampanya_kaynagi' => $sonuc['kampanya_kaynagi'],
        'kampanya_id' => $sonuc['kampanya_id'],
        'kampanya_adi' => $ayrinti['name'],
        'oncelikli_durum' => $ayrinti['status'],
        'durum' => $hedef_durum,
        'yerel_kayit' => $yerel_kayit,
        'baglanmis_hesap_durumu' => google_baglanmis_hesap_durumunu_raporla(
            $once,
            $sonra
        ),
    ];
}

/**
 * Tek bir kampanyanin bitis tarihini degistirir.
 *
 * Guvenlik akisi durum mutate'iyle aynidir: girdi dogrulama -> oturum -> aktif
 * baglanti -> taze manager kontrolu -> kampanyanin bu hesaba aidiyeti -> mutate.
 * Bitis tarihi YYYY-MM-DD olarak alinir; gecmis tarihler reddedilir.
 *
 * @param array<string, mixed> $istek
 * @return array<string, mixed>
 */
function kampanya_bitis_tarihini_degistir(array $istek): array
{
    $kampanya_id = trim((string) ($istek['kampanya_id'] ?? ''));
    $bitis_tarihi = is_string($istek['bitis_tarihi'] ?? null)
        ? trim($istek['bitis_tarihi'])
        : '';

    if (preg_match('/^[1-9][0-9]*$/', $kampanya_id) !== 1) {
        return [
            'return' => 0,
            'mesaj' => 'Geçerli bir kampanya ID belirtilmedi.',
        ];
    }

    $tarih = DateTimeImmutable::createFromFormat('!Y-m-d', $bitis_tarihi);
    $tarih_hatalari = DateTimeImmutable::getLastErrors();
    $tarih_gecersiz = $tarih === false
        || ($tarih_hatalari !== false
            && ($tarih_hatalari['warning_count'] > 0 || $tarih_hatalari['error_count'] > 0))
        || ($tarih !== false && $tarih->format('Y-m-d') !== $bitis_tarihi);

    if ($tarih_gecersiz) {
        return [
            'return' => 0,
            'mesaj' => 'Geçerli bir bitiş tarihi seçin.',
        ];
    }

    if ($tarih < new DateTimeImmutable('today')) {
        return [
            'return' => 0,
            'mesaj' => 'Bitiş tarihi bugün veya daha ileri bir tarih olmalıdır.',
        ];
    }

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
            'baglanmis_hesap_durumu' => google_baglanmis_hesap_durumunu_raporla($once, $once),
        ];
    }

    $customer_id = trim((string) ($baglanti['harici_kimlik'] ?? ''));

    if (preg_match('/^[1-9][0-9]*$/', $customer_id) !== 1) {
        return [
            'return' => 0,
            'mesaj' => 'Bağlanan Google Ads hesabının customer ID bilgisi bulunamadı.',
            'baglanmis_hesap_durumu' => google_baglanmis_hesap_durumunu_raporla($once, $once),
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
                    'return' => 0,
                    'mesaj' => 'Bağlı hesap Manager hesabı; kampanyaya müdahale edilmedi.',
                    'hesap' => $customer,
                    'baglanmis_hesap_durumu' => google_baglanmis_hesap_durumunu_raporla($once, $sonra),
                ];
            }

            $ayrinti = google_ads_kampanya_ayrintilari_al(
                $refresh_token,
                $customer_id,
                $kampanya_id
            );

            if ($ayrinti === null) {
                $sonra = google_baglanmis_hesap_snapshot_al($sahip_no);

                return [
                    'return' => 0,
                    'mesaj' => 'Bu kampanya bağlı hesapta bulunamadı; bitiş tarihi değiştirilmedi.',
                    'baglanmis_hesap_durumu' => google_baglanmis_hesap_durumunu_raporla($once, $sonra),
                ];
            }

            $sonuc = google_ads_kampanya_bitis_tarihini_degistir(
                $refresh_token,
                $customer_id,
                $kampanya_id,
                $bitis_tarihi
            );
        } finally {
            unset($refresh_token);
        }
    } catch (GoogleAdsKesifHatasi $hata) {
        return [
            'return' => 0,
            'mesaj' => 'Kampanya bitiş tarihi değiştirilemedi.',
            'google_ads_hata_kategorisi' => $hata->kategori,
            'google_ads_hata' => $hata->getMessage(),
        ];
    } catch (Throwable $hata) {
        google_ads_hata_kaydi_yaz('kampanya_bitis_tarihini_degistir', $hata);

        return [
            'return' => 0,
            'mesaj' => 'Kampanya bitiş tarihi değiştirilemedi.',
        ];
    }

    $sonra = google_baglanmis_hesap_snapshot_al($sahip_no);

    return [
        'return' => 1,
        'mesaj' => 'Kampanya bitiş tarihi güncellendi.',
        'kampanya_kaynagi' => $sonuc['kampanya_kaynagi'],
        'kampanya_id' => $sonuc['kampanya_id'],
        'kampanya_adi' => $ayrinti['name'],
        'bitis_tarihi' => $bitis_tarihi,
        'end_date_time' => $sonuc['end_date_time'],
        'baglanmis_hesap_durumu' => google_baglanmis_hesap_durumunu_raporla($once, $sonra),
    ];
}
