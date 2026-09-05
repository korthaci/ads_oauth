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
use Google\Ads\GoogleAds\V25\Enums\CustomerStatusEnum\CustomerStatus;
use Google\Ads\GoogleAds\V25\Services\ListAccessibleCustomersRequest;
use Google\Ads\GoogleAds\V25\Services\SearchGoogleAdsRequest;
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