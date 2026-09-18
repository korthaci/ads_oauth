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