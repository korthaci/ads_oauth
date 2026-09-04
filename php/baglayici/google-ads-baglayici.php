<?php

/**
 * Google Ads API cagrilarini resmi Google Ads PHP SDK'si ile sarmalar.
 *
 * Bu dosya servis katmani tarafindan kullanilir; HTTP response uretmez.
 * Hassas OAuth verileri bu katmandan disari cikartilmaz.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config.php';

use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Lib\V25\GoogleAdsClient;
use Google\Ads\GoogleAds\Lib\V25\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\V25\Services\ListAccessibleCustomersRequest;
use Google\Ads\GoogleAds\V25\Services\SearchGoogleAdsRequest;

/**
 * Google Ads kesif islemlerinde kullaniciya gosterilebilecek guvenli hata.
 */
final class GoogleAdsKesifHatasi extends RuntimeException
{
    public function __construct(
        string $mesaj,
        public readonly string $kategori = 'api',
        ?Throwable $onceki = null
    ) {
        parent::__construct($mesaj, 0, $onceki);
    }
}

/**
 * Config anahtarini token/secret degerini aciga cikarmadan okur.
 */
function google_ads_config_degeri(string $anahtar): string
{
    try {
        return trim(config($anahtar));
    } catch (Throwable $hata) {
        return '';
    }
}

/**
 * SDK exception mesajini disari aktarmadan guvenli hata kategorisi belirler.
 */
function google_ads_hata_kategorisi(Throwable $hata): string
{
    $mesaj = strtolower($hata->getMessage());

    if (
        str_contains($mesaj, 'developer token')
        || str_contains($mesaj, 'developer_token')
    ) {
        return 'developer_token';
    }

    if (
        str_contains($mesaj, 'refresh token')
        || str_contains($mesaj, 'invalid_grant')
        || str_contains($mesaj, 'oauth')
        || str_contains($mesaj, 'authentication')
        || str_contains($mesaj, 'unauthenticated')
    ) {
        return 'oauth';
    }

    if (
        str_contains($mesaj, 'authorization')
        || str_contains($mesaj, 'permission')
        || str_contains($mesaj, 'permission_denied')
        || str_contains($mesaj, 'access denied')
        || str_contains($mesaj, 'unauthorized')
    ) {
        return 'yetki';
    }

    if (
        str_contains($mesaj, 'timed out')
        || str_contains($mesaj, 'timeout')
        || str_contains($mesaj, 'could not connect')
        || str_contains($mesaj, 'connection')
        || str_contains($mesaj, 'network')
    ) {
        return 'ag';
    }

    return 'api';
}

/**
 * Exception'i hassas ayrinti icermeyen kontrollu hata mesajina cevirir.
 */
function google_ads_hata_mesaji(string $kategori): string
{
    return match ($kategori) {
        'developer_token' => 'Google Ads Developer Token geçersiz veya eksik.',
        'oauth' => 'Google OAuth kimlik bilgileri veya refresh token kullanılamadı.',
        'yetki' => 'Google Ads hesabı için yetki bulunamadı.',
        'ag' => 'Google Ads API ağına erişilemedi.',
        default => 'Google Ads API çağrısı başarısız.',
    };
}

/**
 * DB'den cozulmus refresh token ile SDK'nin V25 GoogleAdsClient nesnesini kurar.
 */
function google_ads_client_olustur(string $refresh_token): GoogleAdsClient
{
    $client_id = google_ads_config_degeri('GOOGLE_CLIENT_ID');
    $client_secret = google_ads_config_degeri('GOOGLE_CLIENT_SECRET');
    $developer_token = google_ads_config_degeri('GOOGLE_DEVELOPER_TOKEN');

    if ($client_id === '' || $client_secret === '') {
        throw new GoogleAdsKesifHatasi(
            'Google OAuth client ayarları eksik.',
            'oauth'
        );
    }

    if ($developer_token === '') {
        throw new GoogleAdsKesifHatasi(
            'Google Ads Developer Token geçersiz veya eksik.',
            'developer_token'
        );
    }

    if (trim($refresh_token) === '') {
        throw new GoogleAdsKesifHatasi(
            'Google OAuth kimlik bilgileri veya refresh token kullanılamadı.',
            'oauth'
        );
    }

    try {
        $oauth2_credential = (new OAuth2TokenBuilder())
            ->withClientId($client_id)
            ->withClientSecret($client_secret)
            ->withRefreshToken($refresh_token)
            ->build();

        return (new GoogleAdsClientBuilder())
            ->withDeveloperToken($developer_token)
            ->withOAuth2Credential($oauth2_credential)
            ->build();
    } catch (Throwable $hata) {
        $kategori = google_ads_hata_kategorisi($hata);

        throw new GoogleAdsKesifHatasi(
            google_ads_hata_mesaji($kategori),
            $kategori,
            $hata
        );
    }
}

/**
 * API sonucundaki customer resource adindan sayisal customer ID'yi alir.
 */
function google_ads_customer_id_al(string $resource_name): ?string
{
    if (preg_match('/^customers\/([0-9]+)$/', $resource_name, $eslesme) !== 1) {
        return null;
    }

    $customer_id = $eslesme[1];

    return ltrim($customer_id, '0') === '' ? '0' : ltrim($customer_id, '0');
}

/**
 * OAuth kullanicisinin dogrudan erisebildigi customer kaynaklarini ve temel
 * customer bilgilerini Google Ads API'den alir.
 *
 * CustomerService ile donen manager/MCC kaynaklari da API'nin verdigi
 * `customer.manager` bilgisiyle ayirt edilerek sonuca dahil edilir. Mevcut
 * DB semasinda hesap turu alani olmadigi icin bu ayrim response'da korunur.
 *
 * @return array<int, array{
 *     harici_kimlik: string,
 *     hesap_adi: ?string,
 *     yonetici: bool,
 *     para_birimi: ?string,
 *     saat_dilimi: ?string
 * }>
 */
function google_ads_hesaplarini_kesfet(string $refresh_token): array
{
    $client = google_ads_client_olustur($refresh_token);

    try {
        $customer_service = $client->getCustomerServiceClient();
        $accessible_response = $customer_service->listAccessibleCustomers(
            new ListAccessibleCustomersRequest()
        );

        $google_ads_service = $client->getGoogleAdsServiceClient();
        $hesaplar = [];
        $gaql = 'SELECT customer.id, customer.descriptive_name, '
            . 'customer.currency_code, customer.time_zone, customer.manager '
            . 'FROM customer LIMIT 1';

        foreach ($accessible_response->getResourceNames() as $resource_name) {
            $resource_name = (string) $resource_name;
            $customer_id = google_ads_customer_id_al($resource_name);

            if ($customer_id === null || $customer_id === '0') {
                continue;
            }

            $search_response = $google_ads_service->search(
                SearchGoogleAdsRequest::build($customer_id, $gaql)
            );

            foreach ($search_response->getResults() as $row) {
                $customer = $row->getCustomer();

                if ($customer === null) {
                    continue;
                }

                $sonuc_customer_id = trim((string) $customer->getId());
                if ($sonuc_customer_id === '' || $sonuc_customer_id === '0') {
                    $sonuc_customer_id = $customer_id;
                }

                $hesap_adi = trim((string) $customer->getDescriptiveName());
                $para_birimi = trim((string) $customer->getCurrencyCode());
                $saat_dilimi = trim((string) $customer->getTimeZone());

                $hesaplar[$sonuc_customer_id] = [
                    'harici_kimlik' => $sonuc_customer_id,
                    'hesap_adi' => $hesap_adi === '' ? null : $hesap_adi,
                    'yonetici' => (bool) $customer->getManager(),
                    'para_birimi' => $para_birimi === '' ? null : $para_birimi,
                    'saat_dilimi' => $saat_dilimi === '' ? null : $saat_dilimi,
                ];

                break;
            }
        }

        return array_values($hesaplar);
    } catch (GoogleAdsKesifHatasi $hata) {
        throw $hata;
    } catch (Throwable $hata) {
        $kategori = google_ads_hata_kategorisi($hata);

        throw new GoogleAdsKesifHatasi(
            google_ads_hata_mesaji($kategori),
            $kategori,
            $hata
        );
    }
}