<?php

/**
 * Google Ads API cagrilarini resmi Google Ads PHP SDK'si ile sarmalar.
 *
 * Bu dosya servis katmani tarafindan kullanilir; HTTP response uretmez.
 * Hassas OAuth verileri bu katmandan disari cikartilmaz.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/veritabani.php';

use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Lib\V25\GoogleAdsException;
use Google\Ads\GoogleAds\Lib\V25\GoogleAdsClient;
use Google\Ads\GoogleAds\Lib\V25\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\V25\Common\AdTextAsset;
use Google\Ads\GoogleAds\V25\Common\KeywordInfo;
use Google\Ads\GoogleAds\V25\Common\LanguageInfo;
use Google\Ads\GoogleAds\V25\Common\LocationInfo;
use Google\Ads\GoogleAds\V25\Common\ResponsiveSearchAdInfo;
use Google\Ads\GoogleAds\V25\Common\TargetSpend;
use Google\Ads\GoogleAds\V25\Enums\AdGroupAdStatusEnum\AdGroupAdStatus;
use Google\Ads\GoogleAds\V25\Enums\AdGroupCriterionStatusEnum\AdGroupCriterionStatus;
use Google\Ads\GoogleAds\V25\Enums\AdGroupStatusEnum\AdGroupStatus;
use Google\Ads\GoogleAds\V25\Enums\AdGroupTypeEnum\AdGroupType;
use Google\Ads\GoogleAds\V25\Enums\AdvertisingChannelTypeEnum\AdvertisingChannelType;
use Google\Ads\GoogleAds\V25\Enums\BudgetDeliveryMethodEnum\BudgetDeliveryMethod;
use Google\Ads\GoogleAds\V25\Enums\CampaignStatusEnum\CampaignStatus;
use Google\Ads\GoogleAds\V25\Enums\CustomerStatusEnum\CustomerStatus;
use Google\Ads\GoogleAds\V25\Enums\KeywordMatchTypeEnum\KeywordMatchType;
use Google\Ads\GoogleAds\V25\Resources\AdGroup;
use Google\Ads\GoogleAds\V25\Resources\AdGroupAd;
use Google\Ads\GoogleAds\V25\Resources\AdGroupCriterion;
use Google\Ads\GoogleAds\V25\Resources\Ad;
use Google\Ads\GoogleAds\V25\Resources\Campaign;
use Google\Ads\GoogleAds\V25\Resources\CampaignBudget;
use Google\Ads\GoogleAds\V25\Resources\CampaignCriterion;
use Google\Ads\GoogleAds\V25\Resources\Campaign\NetworkSettings;
use Google\Ads\GoogleAds\V25\Services\AdGroupAdOperation;
use Google\Ads\GoogleAds\V25\Services\AdGroupCriterionOperation;
use Google\Ads\GoogleAds\V25\Services\AdGroupOperation;
use Google\Ads\GoogleAds\V25\Services\CampaignBudgetOperation;
use Google\Ads\GoogleAds\V25\Services\CampaignCriterionOperation;
use Google\Ads\GoogleAds\V25\Services\CampaignOperation;
use Google\Ads\GoogleAds\V25\Services\ListAccessibleCustomersRequest;
use Google\Ads\GoogleAds\V25\Services\SuggestGeoTargetConstantsRequest\LocationNames;
use Google\Ads\GoogleAds\V25\Services\MutateGoogleAdsRequest;
use Google\Ads\GoogleAds\V25\Services\MutateOperation;
use Google\Ads\GoogleAds\V25\Services\SearchGoogleAdsRequest;
use Google\Ads\GoogleAds\V25\Services\SuggestGeoTargetConstantsRequest;
use Google\ApiCore\ApiException;

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
 * Hata mesajindaki e-posta, token ve kimlik bilgisi benzeri degerleri maskeler.
 *
 * Bu fonksiyon request/response govdesi veya metadata almaz; yalnizca mesaji
 * kayda alinmadan once guvenli hale getirir.
 */
function google_ads_hata_mesajini_sanitize_et(string $mesaj): string
{
    $mesaj = preg_replace(
        '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',
        '[redacted]',
        $mesaj
    ) ?? '';

    $mesaj = preg_replace(
        '/(authorization\s*:\s*(?:bearer\s+)?|bearer\s+)[^\s,;]+/iu',
        '$1[redacted]',
        $mesaj
    ) ?? '';

    $mesaj = preg_replace(
        '/((?:refresh|access|developer)[_\- ]?token|client[_\- ]?(?:secret|id)|api[_\- ]?key|password)\s*[:=]\s*["\']?[^\s,;"\']+/iu',
        '$1=[redacted]',
        $mesaj
    ) ?? '';

    $mesaj = preg_replace(
        '/((?:refresh|access|developer)[_\- ]?token|client[_\- ]?(?:secret|id)|api[_\- ]?key|password)\s+[^\s,;]+/iu',
        '$1 [redacted]',
        $mesaj
    ) ?? '';

    foreach (['GOOGLE_DEVELOPER_TOKEN', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_CLIENT_ID'] as $anahtar) {
        $gizli_deger = google_ads_config_degeri($anahtar);

        if ($gizli_deger !== '') {
            $mesaj = str_replace($gizli_deger, '[redacted]', $mesaj);
        }
    }

    $mesaj = preg_replace('/[\r\n\t]+/u', ' ', $mesaj) ?? '';

    return trim(mb_substr($mesaj, 0, 4000));
}

/**
 * GoogleAdsError icindeki dolu oneof alanini alt tip ve enum adi olarak alir.
 *
 * V25'te yeni bir hata alt tipi eklense bile alan adi ve ilgili enum sinifi
 * ayni SDK konvansiyonuyla uretilir; bu nedenle sabit bir alt tip listesine
 * baglanmadan tum dolu alanlar desteklenir.
 */
function google_ads_error_kodunu_al(object $error): ?string
{
    $error_code = $error->getErrorCode();

    if (!is_object($error_code)) {
        return null;
    }

    foreach (get_class_methods($error_code) as $metot) {
        if (preg_match('/^has([A-Z][A-Za-z0-9]*)$/', $metot, $eslesme) !== 1) {
            continue;
        }

        $alan = $eslesme[1];
        $getir_metodu = 'get' . $alan;

        if (!method_exists($error_code, $getir_metodu)) {
            continue;
        }

        try {
            $yansima = new ReflectionMethod($error_code, $metot);

            if ($yansima->getNumberOfRequiredParameters() > 0) {
                continue;
            }
        } catch (Throwable $hata) {
            continue;
        }

        try {
            if (!$error_code->{$metot}()) {
                continue;
            }

            $deger = $error_code->{$getir_metodu}();
            $enum_sinifi = 'Google\\Ads\\GoogleAds\\V25\\Errors\\'
                . $alan . 'Enum\\' . $alan;

            if (is_int($deger) && class_exists($enum_sinifi) && method_exists($enum_sinifi, 'name')) {
                $deger = $enum_sinifi::name($deger);
            }

            return lcfirst($alan) . '=' . (string) $deger;
        } catch (Throwable $hata) {
            return lcfirst($eslesme[1]) . '=[unknown]';
        }
    }

    return null;
}

/**
 * Google Ads/API exception'ini metadata ve govde toplamadan ayristirir.
 *
 * @return array{
 *     exception_sinifi: string,
 *     status: ?string,
 *     kod: ?int,
 *     mesaj: string,
 *     request_id: ?string,
 *     hatalar: array<int, array{error_code: ?string, mesaj: string}>
 * }
 */
function google_ads_hata_ayristir(Throwable $hata): array
{
    $kaynak = $hata;

    while (
        !($kaynak instanceof GoogleAdsException)
        && !($kaynak instanceof ApiException)
        && $kaynak->getPrevious() instanceof Throwable
    ) {
        $kaynak = $kaynak->getPrevious();
    }

    $status = null;
    $kod = null;
    $mesaj = $hata->getMessage();
    $request_id = null;
    $hatalar = [];

    if ($kaynak instanceof GoogleAdsException) {
        $status = $kaynak->getStatus() === null
            ? null
            : (string) $kaynak->getStatus();
        $kod = (int) $kaynak->getCode();
        $request_id = $kaynak->getRequestId();

        foreach ($kaynak->getGoogleAdsFailure()->getErrors() as $error) {
            $hatalar[] = [
                'error_code' => google_ads_error_kodunu_al($error),
                'mesaj' => google_ads_hata_mesajini_sanitize_et(
                    (string) $error->getMessage()
                ),
            ];
        }
    } elseif ($kaynak instanceof ApiException) {
        $status = $kaynak->getStatus() === null
            ? null
            : (string) $kaynak->getStatus();
        $kod = (int) $kaynak->getCode();
        $mesaj = (string) $kaynak->getBasicMessage();
    }

    if ($request_id !== null) {
        $request_id = preg_replace('/[^A-Za-z0-9._:\-]/', '', (string) $request_id);
        $request_id = $request_id === '' ? null : mb_substr($request_id, 0, 255);
    }

    return [
        'exception_sinifi' => get_class($kaynak),
        'status' => $status === null ? null : mb_substr($status, 0, 100),
        'kod' => $kod,
        'mesaj' => google_ads_hata_mesajini_sanitize_et((string) $mesaj),
        'request_id' => $request_id,
        'hatalar' => $hatalar,
    ];
}

/**
 * Ayristirilmis API hatasini sadece exception akisi icinde DB'ye yazar.
 * Log yazma basarisiz olsa bile asil API hatasinin kullanici response'u degismez.
 */
function google_ads_hata_kaydi_yaz(
    string $api_cagrisi,
    Throwable $hata,
    ?PDO $baglanti = null
): void
{
    try {
        $kayit = google_ads_hata_ayristir($hata);
        $hatalar_json = $kayit['hatalar'] === []
            ? null
            : json_encode(
                $kayit['hatalar'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        $baglanti ??= veritabani_baglan();
        $sorgu = $baglanti->prepare(
            'INSERT INTO `api_hata_kayitlari` '
            . '(`api_cagrisi`, `exception_sinifi`, `status`, `kod`, `mesaj`, '
            . '`hatalar`, `request_id`) '
            . 'VALUES (:api_cagrisi, :exception_sinifi, :status, :kod, :mesaj, '
            . ':hatalar, :request_id)'
        );
        $sorgu->execute([
            'api_cagrisi' => mb_substr($api_cagrisi, 0, 150),
            'exception_sinifi' => mb_substr($kayit['exception_sinifi'], 0, 255),
            'status' => $kayit['status'],
            'kod' => $kayit['kod'],
            'mesaj' => $kayit['mesaj'],
            'hatalar' => $hatalar_json,
            'request_id' => $kayit['request_id'],
        ]);
    } catch (Throwable $log_hatasi) {
        // Log hatasi asil Google Ads response'unu veya kullanici mesajini bozmaz.
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
function google_ads_client_olustur(
    string $refresh_token,
    ?int $login_customer_id = null
): GoogleAdsClient
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

        $builder = (new GoogleAdsClientBuilder())
            ->withDeveloperToken($developer_token)
            ->withOAuth2Credential($oauth2_credential);

        if ($login_customer_id !== null) {
            if ($login_customer_id < 1) {
                throw new InvalidArgumentException('Google Ads login customer ID geçersiz.');
            }

            $builder->withLoginCustomerId($login_customer_id);
        }

        return $builder->build();
    } catch (Throwable $hata) {
        google_ads_hata_kaydi_yaz('GoogleAdsClientBuilder::build', $hata);
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
        $ilk_hesap_hatasi = null;
        $gaql = 'SELECT customer.id, customer.descriptive_name, '
            . 'customer.currency_code, customer.time_zone, customer.manager '
            . 'FROM customer LIMIT 1';

        foreach ($accessible_response->getResourceNames() as $resource_name) {
            $resource_name = (string) $resource_name;
            $customer_id = google_ads_customer_id_al($resource_name);

            if ($customer_id === null || $customer_id === '0') {
                continue;
            }

            try {
                $search_response = $google_ads_service->search(
                    SearchGoogleAdsRequest::build($customer_id, $gaql)
                );
            } catch (Throwable $hata) {
                google_ads_hata_kaydi_yaz('GoogleAdsService::search', $hata);
                $ilk_hesap_hatasi ??= $hata;
                continue;
            }

            foreach ($search_response as $row) {
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

        if ($hesaplar === [] && $ilk_hesap_hatasi !== null) {
            $kategori = google_ads_hata_kategorisi($ilk_hesap_hatasi);

            throw new GoogleAdsKesifHatasi(
                google_ads_hata_mesaji($kategori),
                $kategori,
                $ilk_hesap_hatasi
            );
        }

        return array_values($hesaplar);
    } catch (GoogleAdsKesifHatasi $hata) {
        throw $hata;
    } catch (Throwable $hata) {
        google_ads_hata_kaydi_yaz('CustomerService::listAccessibleCustomers', $hata);
        $kategori = google_ads_hata_kategorisi($hata);

        throw new GoogleAdsKesifHatasi(
            google_ads_hata_mesaji($kategori),
            $kategori,
            $hata
        );
    }
}

/**
 * CustomerClient kaydinin enum status degerini V25 adina cevirir.
 */
function google_ads_musteri_durumunu_al(int $status): string
{
    try {
        return CustomerStatus::name($status);
    } catch (Throwable $hata) {
        return 'UNKNOWN';
    }
}

/**
 * Manager hesabinin CustomerClient alt hesaplarini read-only olarak sorgular.
 *
 * Google Ads Manager sorgusunda customer ID ve SDK'nin destekledigi
 * login-customer-id mevcut Manager hesabindan alinir. Bu akis login-customer-id
 * olmadan da API tarafinda basarili oldugu icin zorunlu varsayilmaz. Bu fonksiyon
 * mutate servisi kullanmaz; yalnizca GoogleAdsService.search() cagirir.
 *
 * @return array<int, array{
 *     harici_kimlik: string,
 *     hesap_adi: ?string,
 *     yonetici: bool,
 *     durum: string,
 *     para_birimi: ?string,
 *     saat_dilimi: ?string,
 *     seviye: int
 * }>
 */
function google_ads_musteri_hesaplarini_kesfet(
    string $refresh_token,
    string $manager_customer_id
): array {
    if (preg_match('/^[1-9][0-9]*$/', $manager_customer_id) !== 1) {
        throw new GoogleAdsKesifHatasi(
            'Google Ads manager hesabı bulunamadı.',
            'api'
        );
    }

    $client = google_ads_client_olustur($refresh_token, (int) $manager_customer_id);

    try {
        $gaql = 'SELECT customer_client.id, customer_client.descriptive_name, '
            . 'customer_client.manager, customer_client.status, '
            . 'customer_client.currency_code, customer_client.time_zone, '
            . 'customer_client.level '
            . 'FROM customer_client';

        $search_response = $client->getGoogleAdsServiceClient()->search(
            SearchGoogleAdsRequest::build($manager_customer_id, $gaql)
        );
        $hesaplar = [];

        foreach ($search_response as $row) {
            $customer_client = $row->getCustomerClient();

            if ($customer_client === null) {
                continue;
            }

            $customer_id = trim((string) $customer_client->getId());

            if ($customer_id === '' || $customer_id === '0') {
                $customer_id = google_ads_customer_id_al(
                    (string) $customer_client->getClientCustomer()
                ) ?? '';
            }

            if ($customer_id === '' || $customer_id === '0') {
                continue;
            }

            $hesap_adi = trim((string) $customer_client->getDescriptiveName());
            $para_birimi = trim((string) $customer_client->getCurrencyCode());
            $saat_dilimi = trim((string) $customer_client->getTimeZone());

            $hesaplar[$customer_id] = [
                'harici_kimlik' => $customer_id,
                'hesap_adi' => $hesap_adi === '' ? null : $hesap_adi,
                'yonetici' => (bool) $customer_client->getManager(),
                'durum' => google_ads_musteri_durumunu_al(
                    (int) $customer_client->getStatus()
                ),
                'para_birimi' => $para_birimi === '' ? null : $para_birimi,
                'saat_dilimi' => $saat_dilimi === '' ? null : $saat_dilimi,
                'seviye' => (int) $customer_client->getLevel(),
            ];
        }

        return array_values($hesaplar);
    } catch (GoogleAdsKesifHatasi $hata) {
        throw $hata;
    } catch (Throwable $hata) {
        google_ads_hata_kaydi_yaz('GoogleAdsService::search customer_client', $hata);
        $kategori = google_ads_hata_kategorisi($hata);

        throw new GoogleAdsKesifHatasi(
            google_ads_hata_mesaji($kategori),
            $kategori,
            $hata
        );
    }
}

/**
 * Tek bir customer kaydinin read-only temel bilgilerini sorgular.
 *
 * Bu fonksiyon yalnizca GoogleAdsService.search() kullanir. CustomerService,
 * mutate veya hesap olusturma cagrisi yapmaz.
 *
 * @return array{
 *     customer_id: string,
 *     descriptive_name: ?string,
 *     manager: bool,
 *     status: string,
 *     currency_code: ?string,
 *     time_zone: ?string
 * }
 */
function google_ads_musteri_bilgilerini_al(
    string $refresh_token,
    string $customer_id
): array {
    if (preg_match('/^[1-9][0-9]*$/', $customer_id) !== 1) {
        throw new GoogleAdsKesifHatasi('Google Ads customer ID geçersiz.', 'api');
    }

    $client = google_ads_client_olustur($refresh_token, (int) $customer_id);

    try {
        $gaql = 'SELECT customer.id, customer.descriptive_name, customer.manager, '
            . 'customer.status, customer.currency_code, customer.time_zone '
            . 'FROM customer LIMIT 1';
        $search_response = $client->getGoogleAdsServiceClient()->search(
            SearchGoogleAdsRequest::build($customer_id, $gaql)
        );

        foreach ($search_response as $row) {
            $customer = $row->getCustomer();

            if ($customer === null) {
                continue;
            }

            $id = trim((string) $customer->getId());
            $descriptive_name = trim((string) $customer->getDescriptiveName());
            $currency_code = trim((string) $customer->getCurrencyCode());
            $time_zone = trim((string) $customer->getTimeZone());

            return [
                'customer_id' => $id === '' ? $customer_id : $id,
                'descriptive_name' => $descriptive_name === '' ? null : $descriptive_name,
                'manager' => (bool) $customer->getManager(),
                'status' => CustomerStatus::name((int) $customer->getStatus()),
                'currency_code' => $currency_code === '' ? null : $currency_code,
                'time_zone' => $time_zone === '' ? null : $time_zone,
            ];
        }

        throw new GoogleAdsKesifHatasi(
            'Google Ads customer bilgisi bulunamadı.',
            'api'
        );
    } catch (GoogleAdsKesifHatasi $hata) {
        throw $hata;
    } catch (Throwable $hata) {
        google_ads_hata_kaydi_yaz('GoogleAdsService::search customer', $hata);
        $kategori = google_ads_hata_kategorisi($hata);

        throw new GoogleAdsKesifHatasi(
            google_ads_hata_mesaji($kategori),
            $kategori,
            $hata
        );
    }
}

/**
 * Bir non-manager customer hesabinin kampanyalarini read-only olarak listeler.
 *
 * Bu fonksiyon yalnizca GoogleAdsService.search() kullanir ve API'den gelen
 * verileri yerel kampanyalar tablosuna yazmaz.
 *
 * @return array<int, array{
 *     id: string,
 *     name: string,
 *     status: string,
 *     advertising_channel_type: string,
 *     budget_amount_micros: int|string|null
 * }>
 */
function google_ads_kampanyalari_listele(
    string $refresh_token,
    string $customer_id
): array {
    if (preg_match('/^[1-9][0-9]*$/', $customer_id) !== 1) {
        throw new GoogleAdsKesifHatasi('Google Ads customer ID geçersiz.', 'api');
    }

    $client = google_ads_client_olustur($refresh_token, (int) $customer_id);

    try {
        $gaql = 'SELECT campaign.id, campaign.name, campaign.status, '
            . 'campaign.advertising_channel_type, campaign_budget.amount_micros '
            . 'FROM campaign ORDER BY campaign.id';
        $search_response = $client->getGoogleAdsServiceClient()->search(
            SearchGoogleAdsRequest::build($customer_id, $gaql)
        );
        $kampanyalar = [];

        foreach ($search_response as $row) {
            $campaign = $row->getCampaign();

            if ($campaign === null) {
                continue;
            }

            $budget = $row->getCampaignBudget();
            $budget_amount_micros = null;

            if ($budget !== null && $budget->hasAmountMicros()) {
                $budget_amount_micros = $budget->getAmountMicros();
            }

            $kampanyalar[] = [
                'id' => (string) $campaign->getId(),
                'name' => (string) $campaign->getName(),
                'status' => CampaignStatus::name((int) $campaign->getStatus()),
                'advertising_channel_type' => AdvertisingChannelType::name(
                    (int) $campaign->getAdvertisingChannelType()
                ),
                'budget_amount_micros' => $budget_amount_micros,
            ];
        }

        return $kampanyalar;
    } catch (GoogleAdsKesifHatasi $hata) {
        throw $hata;
    } catch (Throwable $hata) {
        google_ads_hata_kaydi_yaz('GoogleAdsService::search campaign', $hata);
        $kategori = google_ads_hata_kategorisi($hata);

        throw new GoogleAdsKesifHatasi(
            google_ads_hata_mesaji($kategori),
            $kategori,
            $hata
        );
    }
}

/**
 * Girilen serbest metin konum adini Google Ads geoTargetConstant kaynak adina
 * cevirir. Salt-okunur calisir: GeoTargetConstantService.suggestGeoTargetConstants
 * kullanir; mutate cagrisi yapmaz.
 *
 * Yalnizca tam (buyuk/kucuk harf duyarsiz) isim eslesmesi kabul edilir; en yakin
 * eslesme sessizce secilmez. Tam eslesme yoksa, bulunabilirse ornek onerilerle
 * birlikte hata firlatilir.
 *
 * @return array{resource_name: string, name: string}
 */
function google_ads_konum_onerilerini_al(
    string $refresh_token,
    string $konum
): array {
    $konum = trim($konum);

    if ($konum === '') {
        throw new GoogleAdsKesifHatasi('Hedef konum boş olamaz.', 'girdi');
    }

    $client = google_ads_client_olustur($refresh_token);

    try {
        $istek = (new SuggestGeoTargetConstantsRequest())
            ->setLocale('tr')
            ->setCountryCode('TR')
            ->setLocationNames(
                (new LocationNames())->setNames([$konum])
            );

        $yanit = $client->getGeoTargetConstantServiceClient()
            ->suggestGeoTargetConstants($istek);

        $oneriler = [];
        $tam_eslesenler = [];
        $arama_ad = mb_strtolower($konum, 'UTF-8');
        $tarama_limiti = 0;

        foreach ($yanit as $oneri) {
            if (++$tarama_limiti > 50) {
                break;
            }

            $sabit = $oneri->getGeoTargetConstant();

            if ($sabit === null) {
                continue;
            }

            $ad = trim((string) $sabit->getName());
            $kaynak = trim((string) $sabit->getResourceName());

            if (
                $ad === ''
                || $kaynak === ''
                || preg_match('/^geoTargetConstants\/[0-9]+$/', $kaynak) !== 1
            ) {
                continue;
            }

            $kayit = [
                'resource_name' => $kaynak,
                'name' => $ad,
            ];

            $oneriler[] = $kayit;

            if (mb_strtolower($ad, 'UTF-8') === $arama_ad) {
                $tam_eslesenler[] = $kayit;
            }
        }

        if (count($tam_eslesenler) === 1) {
            return $tam_eslesenler[0];
        }

        if (count($tam_eslesenler) > 1) {
            throw new GoogleAdsKesifHatasi(
                'Aynı adlı birden fazla Google Ads konumu bulundu; kampanya için '
                . 'konumu daha belirgin yazın.',
                'girdi'
            );
        }

        if ($oneriler === []) {
            throw new GoogleAdsKesifHatasi(
                'Girilen konum bulunamadı; farklı yazmayı deneyin.',
                'girdi'
            );
        }

        $ornekler = implode(', ', array_column(
            array_slice($oneriler, 0, 5),
            'name'
        ));

        throw new GoogleAdsKesifHatasi(
            'Girilen konum için tam eşleşme bulunamadı. Örnek öneriler: '
            . $ornekler,
            'girdi'
        );
    } catch (GoogleAdsKesifHatasi $hata) {
        throw $hata;
    } catch (Throwable $hata) {
        google_ads_hata_kaydi_yaz(
            'GeoTargetConstantService::suggestGeoTargetConstants',
            $hata
        );
        $kategori = google_ads_hata_kategorisi($hata);

        throw new GoogleAdsKesifHatasi(
            google_ads_hata_mesaji($kategori),
            $kategori,
            $hata
        );
    }
}

/**
 * mutate yanitindaki kampanya kaynak adindan sayisal kampanya ID'sini alir.
 */
function google_ads_kampanya_id_al(string $kampanya_kaynagi): ?string
{
    if (
        preg_match(
            '/^customers\/[0-9]+\/campaigns\/([0-9]+)$/',
            $kampanya_kaynagi,
            $eslesme
        ) !== 1
    ) {
        return null;
    }

    $kampanya_id = $eslesme[1];

    return ltrim($kampanya_id, '0') === ''
        ? '0'
        : ltrim($kampanya_id, '0');
}

/**
 * Bagli non-manager Google Ads hesabinda yayina hazir bir Search kampanyasi
 * olusturur: butce, kampanya, konum/dil hedefleri, reklam grubu, anahtar
 * kelimeler ve Responsive Search Ad tek atomik GoogleAdsService::mutate
 * isteginde (geici kaynak adlariyla) olusturulur. partial_failure kapalidir;
 * tek bir hata tum istegi geri alir, yari kalmis kaynak birakmaz.
 *
 * Kampanya HER ZAMAN PAUSED olarak olusturulur; bu fonksiyon ENABLED durumunu
 * hicbir kosulda set etmez. createCustomerClient veya hesap olusturma cagrisi
 * yapmaz; mutate yalnizca kampanya/butce/kriter/reklam kaynaklari icindir.
 *
 * @param array{
 *     kampanya_adi: string,
 *     butce_micros: int,
 *     konum_kaynagi: string,
 *     basliklar: array<int, string>,
 *     aciklamalar: array<int, string>,
 *     anahtar_kelimeler: array<int, string>,
 *     web_sitesi: string
 * } $plan
 *
 * @return array{kampanya_kaynagi: string, kampanya_id: string}
 */
function google_ads_kampanya_olustur(
    string $refresh_token,
    string $customer_id,
    array $plan
): array {
    if (preg_match('/^[1-9][0-9]*$/', $customer_id) !== 1) {
        throw new GoogleAdsKesifHatasi('Google Ads customer ID geçersiz.', 'api');
    }

    foreach (['kampanya_adi', 'konum_kaynagi', 'web_sitesi'] as $metin_alan) {
        if (
            !isset($plan[$metin_alan])
            || trim((string) $plan[$metin_alan]) === ''
        ) {
            throw new GoogleAdsKesifHatasi(
                'Kampanya oluşturma planı eksik alan içeriyor: ' . $metin_alan,
                'girdi'
            );
        }
    }

    if (
        !isset($plan['butce_micros'])
        || !is_int($plan['butce_micros'])
        || $plan['butce_micros'] <= 0
    ) {
        throw new GoogleAdsKesifHatasi(
            'Günlük bütçe micros değeri geçersiz.',
            'girdi'
        );
    }

    foreach (['basliklar', 'aciklamalar', 'anahtar_kelimeler'] as $liste_alan) {
        if (
            !isset($plan[$liste_alan])
            || !is_array($plan[$liste_alan])
            || $plan[$liste_alan] === []
        ) {
            throw new GoogleAdsKesifHatasi(
                'Kampanya oluşturma planı eksik alan içeriyor: ' . $liste_alan,
                'girdi'
            );
        }
    }

    $client = google_ads_client_olustur($refresh_token);
    $on_ek = 'customers/' . $customer_id;

    try {
        $islemler = [];

        $butce_islemi = new MutateOperation();
        $butce_islemi->setCampaignBudgetOperation((new CampaignBudgetOperation())->setCreate(
            (new CampaignBudget())
                ->setAmountMicros($plan['butce_micros'])
                ->setDeliveryMethod(BudgetDeliveryMethod::STANDARD)
                ->setExplicitlyShared(false)
        ));
        $islemler[] = $butce_islemi;

        $kampanya_islemi = new MutateOperation();
        $kampanya_islemi->setCampaignOperation((new CampaignOperation())->setCreate(
            (new Campaign())
                ->setName($plan['kampanya_adi'])
                ->setAdvertisingChannelType(AdvertisingChannelType::SEARCH)
                ->setStatus(CampaignStatus::PAUSED)
                ->setCampaignBudget($on_ek . '/campaignBudgets/-1')
                ->setTargetSpend(new TargetSpend())
                ->setNetworkSettings(
                    (new NetworkSettings())
                        ->setTargetGoogleSearch(true)
                        ->setTargetSearchNetwork(false)
                        ->setTargetContentNetwork(false)
                        ->setTargetPartnerSearchNetwork(false)
                )
        ));
        $islemler[] = $kampanya_islemi;


        $konum_islemi = new MutateOperation();
        $konum_islemi->setCampaignCriterionOperation((new CampaignCriterionOperation())->setCreate(
            (new CampaignCriterion())
                ->setCampaign($on_ek . '/campaigns/-2')
                ->setLocation(
                    (new LocationInfo())
                        ->setGeoTargetConstant($plan['konum_kaynagi'])
                )
        ));
        $islemler[] = $konum_islemi;

        $dil_islemi = new MutateOperation();
        $dil_islemi->setCampaignCriterionOperation((new CampaignCriterionOperation())->setCreate(
            (new CampaignCriterion())
                ->setCampaign($on_ek . '/campaigns/-2')
                ->setLanguage(
                    (new LanguageInfo())
                        ->setLanguageConstant('languageConstants/1017')
                )
        ));
        $islemler[] = $dil_islemi;

        $reklam_grubu_islemi = new MutateOperation();
        $reklam_grubu_islemi->setAdGroupOperation((new AdGroupOperation())->setCreate(
            (new AdGroup())
                ->setName($plan['kampanya_adi'] . ' - Reklam Grubu 1')
                ->setCampaign($on_ek . '/campaigns/-2')
                ->setStatus(AdGroupStatus::ENABLED)
                ->setType(AdGroupType::SEARCH_STANDARD)
        ));
        $islemler[] = $reklam_grubu_islemi;

        foreach ($plan['anahtar_kelimeler'] as $anahtar_kelime) {
            $kelime_islemi = new MutateOperation();
            $kelime_islemi->setAdGroupCriterionOperation((new AdGroupCriterionOperation())->setCreate(
                (new AdGroupCriterion())
                    ->setAdGroup($on_ek . '/adGroups/-3')
                    ->setStatus(AdGroupCriterionStatus::ENABLED)
                    ->setKeyword(
                        (new KeywordInfo())
                            ->setText((string) $anahtar_kelime)
                            ->setMatchType(KeywordMatchType::PHRASE)
                    )
            ));
            $islemler[] = $kelime_islemi;
        }

        $basliklar = [];
        foreach ($plan['basliklar'] as $baslik) {
            $basliklar[] = (new AdTextAsset())->setText((string) $baslik);
        }

        $aciklamalar = [];
        foreach ($plan['aciklamalar'] as $aciklama) {
            $aciklamalar[] = (new AdTextAsset())->setText((string) $aciklama);
        }

        $reklam_islemi = new MutateOperation();
        $reklam_islemi->setAdGroupAdOperation((new AdGroupAdOperation())->setCreate(
            (new AdGroupAd())
                ->setAdGroup($on_ek . '/adGroups/-3')
                ->setAd(
                    (new Ad())
                        ->setResponsiveSearchAd(
                            (new ResponsiveSearchAdInfo())
                                ->setHeadlines($basliklar)
                                ->setDescriptions($aciklamalar)
                        )
                        ->setFinalUrls([$plan['web_sitesi']])
                )
                ->setStatus(AdGroupAdStatus::ENABLED)
        ));
        $islemler[] = $reklam_islemi;

        $istek = MutateGoogleAdsRequest::build($customer_id, $islemler);
        $istek->setPartialFailure(false);

        $yanit = $client->getGoogleAdsServiceClient()->mutate($istek);
        $sonuclar = $yanit->getResults();
        $kampanya_kaynagi = '';

        if (
            count($sonuclar) > 1
            && $sonuclar[1]->getCampaignResult() !== null
        ) {
            $kampanya_kaynagi = (string) $sonuclar[1]->getCampaignResult()
                ->getResourceName();
        }

        if (
            $kampanya_kaynagi === ''
            || preg_match('/^customers\/[0-9]+\/campaigns\/[0-9]+$/', $kampanya_kaynagi) !== 1
        ) {
            throw new RuntimeException('Mutate yanıtında kampanya kaynak adı doğrulanamadı.');
        }

        $kampanya_id = google_ads_kampanya_id_al($kampanya_kaynagi);

        if ($kampanya_id === null) {
            throw new RuntimeException('Kampanya ID çözümlenemedi.');
        }

        return [
            'kampanya_kaynagi' => $kampanya_kaynagi,
            'kampanya_id' => $kampanya_id,
        ];
    } catch (GoogleAdsException $hata) {
        google_ads_hata_kaydi_yaz('GoogleAdsService::mutate kampanya-olustur', $hata);

        $birincil_mesaj = '';
        $failure = $hata->getGoogleAdsFailure();

        if ($failure !== null && count($failure->getErrors()) > 0) {
            $birincil_mesaj = (string) $failure->getErrors()[0]->getMessage();
        }

        $temiz_mesaj = trim(google_ads_hata_mesajini_sanitize_et($birincil_mesaj));

        throw new GoogleAdsKesifHatasi(
            $temiz_mesaj === ''
                ? 'Google Ads kampanya oluşturma çağrısı başarısız.'
                : $temiz_mesaj,
            'api',
            $hata
        );
    } catch (GoogleAdsKesifHatasi $hata) {
        throw $hata;
    } catch (ApiException $hata) {
        google_ads_hata_kaydi_yaz('GoogleAdsService::mutate kampanya-olustur', $hata);

        $temiz_mesaj = trim(google_ads_hata_mesajini_sanitize_et((string) $hata->getMessage()));

        throw new GoogleAdsKesifHatasi(
            $temiz_mesaj === ''
                ? 'Google Ads kampanya oluşturma çağrısı başarısız.'
                : $temiz_mesaj,
            'api',
            $hata
        );
    } catch (Throwable $hata) {
        google_ads_hata_kaydi_yaz('GoogleAdsService::mutate kampanya-olustur', $hata);

        throw new GoogleAdsKesifHatasi(
            'Google Ads kampanya oluşturma çağrısı başarısız.',
            'api',
            $hata
        );
    }
}
