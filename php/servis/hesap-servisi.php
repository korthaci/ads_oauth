<?php

/**
 * Bagli Google Ads hesaplarini kesfeder ve yerel bagli hesaplara kaydeder.
 *
 * HTTP giris noktasi degildir; api/index.php tarafindan cagrilir.
 */

require_once dirname(__DIR__) . '/oturum.php';
require_once dirname(__DIR__) . '/sifreleme.php';
require_once dirname(__DIR__) . '/veritabani.php';
require_once dirname(__DIR__) . '/baglayici/google-ads-baglayici.php';

/**
 * Oturum sahibinin aktif ve refresh token iceren Google OAuth baglantisini alir.
 *
 * @return array{no: int, refresh_token_sifreli: string}|null
 */
function google_aktif_baglantiyi_al(int $sahip_no): ?array
{
    $sorgu = veritabani_baglan()->prepare(
        'SELECT `no`, `refresh_token_sifreli` '
        . 'FROM `baglanmis_hesaplar` '
        . 'WHERE `sahip_no` = :sahip_no '
        . 'AND `platform` = :platform '
        . 'AND `aktif` = 1 '
        . 'AND `refresh_token_sifreli` IS NOT NULL '
        . 'AND `refresh_token_sifreli` <> :bos_token '
        . 'ORDER BY `no` DESC LIMIT 1'
    );
    $sorgu->execute([
        'sahip_no' => $sahip_no,
        'platform' => 'google',
        'bos_token' => '',
    ]);

    $baglanti = $sorgu->fetch();

    if (!is_array($baglanti) || !isset($baglanti['no'], $baglanti['refresh_token_sifreli'])) {
        return null;
    }

    return [
        'no' => (int) $baglanti['no'],
        'refresh_token_sifreli' => (string) $baglanti['refresh_token_sifreli'],
    ];
}

/**
 * Kesfedilen hesaplari duplicate olusturmadan kaydeder.
 *
 * Ilk yeni gercek hesap, OAuth callback'in olusturdugu NULL harici kimlikli
 * placeholder kaydi kullanir. Diger yeni hesaplar mevcut sifreli refresh
 * token ile eklenir. Var olan hesaplarda refresh token kolonuna dokunulmaz.
 *
 * @param array<int, array{
 *     harici_kimlik: string,
 *     hesap_adi: ?string,
 *     yonetici: bool,
 *     para_birimi: ?string,
 *     saat_dilimi: ?string
 * }> $hesaplar
 */
function google_kesfedilen_hesaplari_kaydet(
    int $sahip_no,
    string $refresh_token_sifreli,
    array $hesaplar
): void {
    $baglanti = veritabani_baglan();
    $baglanti->beginTransaction();

    try {
        $mevcut_sorgu = $baglanti->prepare(
            'SELECT `no`, `harici_kimlik` '
            . 'FROM `baglanmis_hesaplar` '
            . 'WHERE `sahip_no` = :sahip_no AND `platform` = :platform '
            . 'FOR UPDATE'
        );
        $mevcut_sorgu->execute([
            'sahip_no' => $sahip_no,
            'platform' => 'google',
        ]);

        $mevcut_hesaplar = [];
        $placeholder_no = null;

        foreach ($mevcut_sorgu->fetchAll() as $mevcut_hesap) {
            $mevcut_no = (int) $mevcut_hesap['no'];
            $harici_kimlik = $mevcut_hesap['harici_kimlik'];

            if ($harici_kimlik === null || (string) $harici_kimlik === '') {
                if ($placeholder_no === null) {
                    $placeholder_no = $mevcut_no;
                }

                continue;
            }

            $mevcut_hesaplar[(string) $harici_kimlik] = $mevcut_no;
        }

        $guncelle = $baglanti->prepare(
            'UPDATE `baglanmis_hesaplar` SET `hesap_adi` = :hesap_adi, `aktif` = 1 '
            . 'WHERE `no` = :no AND `sahip_no` = :sahip_no AND `platform` = :platform'
        );
        $placeholder_guncelle = $baglanti->prepare(
            'UPDATE `baglanmis_hesaplar` SET `harici_kimlik` = :harici_kimlik, '
            . '`hesap_adi` = :hesap_adi, `aktif` = 1 '
            . 'WHERE `no` = :no AND `sahip_no` = :sahip_no AND `platform` = :platform '
            . 'AND (`harici_kimlik` IS NULL OR `harici_kimlik` = :bos_harici_kimlik)'
        );
        $ekle = $baglanti->prepare(
            'INSERT INTO `baglanmis_hesaplar` '
            . '(`sahip_no`, `platform`, `harici_kimlik`, `hesap_adi`, '
            . '`refresh_token_sifreli`, `erisim_token_sifreli`, `token_bitis`, `aktif`) '
            . 'VALUES (:sahip_no, :platform, :harici_kimlik, :hesap_adi, '
            . ':refresh_token_sifreli, NULL, NULL, 1)'
        );

        foreach ($hesaplar as $hesap) {
            $harici_kimlik = (string) $hesap['harici_kimlik'];
            $hesap_adi = $hesap['hesap_adi'];

            if (isset($mevcut_hesaplar[$harici_kimlik])) {
                $guncelle->execute([
                    'hesap_adi' => $hesap_adi,
                    'no' => $mevcut_hesaplar[$harici_kimlik],
                    'sahip_no' => $sahip_no,
                    'platform' => 'google',
                ]);
                continue;
            }

            if ($placeholder_no !== null) {
                $placeholder_guncelle->execute([
                    'harici_kimlik' => $harici_kimlik,
                    'hesap_adi' => $hesap_adi,
                    'no' => $placeholder_no,
                    'sahip_no' => $sahip_no,
                    'platform' => 'google',
                    'bos_harici_kimlik' => '',
                ]);
                $mevcut_hesaplar[$harici_kimlik] = $placeholder_no;
                $placeholder_no = null;
                continue;
            }

            $ekle->execute([
                'sahip_no' => $sahip_no,
                'platform' => 'google',
                'harici_kimlik' => $harici_kimlik,
                'hesap_adi' => $hesap_adi,
                'refresh_token_sifreli' => $refresh_token_sifreli,
            ]);
            $mevcut_hesaplar[$harici_kimlik] = (int) $baglanti->lastInsertId();
        }

        $baglanti->commit();
    } catch (Throwable $hata) {
        if ($baglanti->inTransaction()) {
            $baglanti->rollBack();
        }

        throw $hata;
    }
}

/**
 * Giris yapmis kullanicinin Google Ads hesaplarini kesfeder ve kaydeder.
 *
 * @return array{return: int, mesaj: string, hesaplar?: array<int, array<string, mixed>>}
 */
function google_hesaplarini_kesfet(): array
{
    $sahip_no = oturum_sahip_no();

    if ($sahip_no === null || $sahip_no < 1) {
        return [
            'return' => 0,
            'mesaj' => 'Oturum gerekli.',
        ];
    }

    try {
        $baglanti = google_aktif_baglantiyi_al($sahip_no);
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

    try {
        $refresh_token = coz($baglanti['refresh_token_sifreli']);

        if (trim($refresh_token) === '') {
            throw new GoogleAdsKesifHatasi(
                'Google OAuth kimlik bilgileri veya refresh token kullanılamadı.',
                'oauth'
            );
        }

        try {
            $hesaplar = google_ads_hesaplarini_kesfet($refresh_token);
        } finally {
            unset($refresh_token);
        }

        google_kesfedilen_hesaplari_kaydet(
            $sahip_no,
            $baglanti['refresh_token_sifreli'],
            $hesaplar
        );
    } catch (GoogleAdsKesifHatasi $hata) {
        return [
            'return' => 0,
            'mesaj' => $hata->getMessage(),
        ];
    } catch (Throwable $hata) {
        return [
            'return' => 0,
            'mesaj' => 'Google Ads hesapları kaydedilemedi.',
        ];
    }

    $cevap_hesaplari = array_map(
        static function (array $hesap): array {
            return [
                'harici_kimlik' => $hesap['harici_kimlik'],
                'hesap_adi' => $hesap['hesap_adi'],
                'yonetici' => $hesap['yonetici'],
            ];
        },
        $hesaplar
    );

    return [
        'return' => 1,
        'mesaj' => 'Google Ads hesapları başarıyla keşfedildi.',
        'hesaplar' => $cevap_hesaplari,
    ];
}