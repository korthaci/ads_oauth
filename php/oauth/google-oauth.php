<?php

/**
 * Google OAuth teknik akisini yurutur.
 *
 * Bu dosya api/index.php tarafindan yuklenir; HTTP giris noktasi degildir.
 * Google Auth ve Google Ads PHP client paketleri mevcut Composer autoload'u
 * uzerinden kullanilir.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/oturum.php';
require_once dirname(__DIR__) . '/sifreleme.php';
require_once dirname(__DIR__) . '/veritabani.php';
require_once dirname(__DIR__) . '/servis/hesap-servisi.php';

use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Auth\OAuth2;

const GOOGLE_OAUTH_SCOPE = 'https://www.googleapis.com/auth/adwords';
const GOOGLE_OAUTH_AUTHORIZATION_URI = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_OAUTH_TOKEN_URI = 'https://oauth2.googleapis.com/token';
const GOOGLE_OAUTH_STATE_SESSION_KEY = 'google_oauth_state';

/**
 * Google OAuth callback URI'sini config'ten alir ve guvenli bir callback
 * endpoint'i oldugunu kontrol eder.
 */
function google_oauth_redirect_uri_al(): string
{
    $uri = trim(config('GOOGLE_OAUTH_REDIRECT_URI'));
    $parcalar = parse_url($uri);

    if (!is_array($parcalar)) {
        throw new RuntimeException('Google OAuth redirect URI geçersiz.');
    }

    $scheme = strtolower((string) ($parcalar['scheme'] ?? ''));
    $host = strtolower((string) ($parcalar['host'] ?? ''));
    $query = (string) ($parcalar['query'] ?? '');

    $yerel_http = $scheme === 'http'
        && in_array($host, ['localhost', '127.0.0.1', '::1'], true);

    if (($scheme !== 'https' && !$yerel_http) || $host === '') {
        throw new RuntimeException('Google OAuth redirect URI HTTPS olmalıdır.');
    }

    if (
        array_key_exists('user', $parcalar)
        || array_key_exists('pass', $parcalar)
        || array_key_exists('fragment', $parcalar)
    ) {
        throw new RuntimeException('Google OAuth redirect URI geçersiz.');
    }

    parse_str($query, $query_parametreleri);

    if (($query_parametreleri['islem'] ?? null) !== 'oauth-donus') {
        throw new RuntimeException('Google OAuth redirect URI callback işlemini belirtmelidir.');
    }

    return $uri;
}

/**
 * Google OAuth ayarlarini zorunlu alanlari aciga cikarmadan dogrular.
 *
 * @return array{client_id: string, client_secret: string}
 */
function google_oauth_ayarlarini_al(): array
{
    $client_id = config('GOOGLE_CLIENT_ID');
    $client_secret = config('GOOGLE_CLIENT_SECRET');

    if ($client_id === '' || $client_secret === '') {
        throw new RuntimeException('Google OAuth client ayarlari eksik.');
    }

    return [
        'client_id' => $client_id,
        'client_secret' => $client_secret,
    ];
}

/**
 * Authorization URL'sini Google Auth OAuth2 sinifi ile uretir.
 */
function google_oauth_yetkilendirme_urlu_uret(string $state): string
{
    $ayarlar = google_oauth_ayarlarini_al();
    $oauth = new OAuth2([
        'authorizationUri' => GOOGLE_OAUTH_AUTHORIZATION_URI,
        'redirectUri' => google_oauth_redirect_uri_al(),
        'clientId' => $ayarlar['client_id'],
        'clientSecret' => $ayarlar['client_secret'],
        'scope' => GOOGLE_OAUTH_SCOPE,
        'state' => $state,
    ]);

    $url = $oauth->buildFullAuthorizationUri([
        'access_type' => 'offline',
        'prompt' => 'consent',
    ]);

    return (string) $url;
}

/**
 * Oturumdaki kullanici icin Google OAuth akisini baslatir.
 *
 * @return array{return: int, mesaj: string, url?: string}
 */
function google_oauth_baslat(): array
{
    $sahip_no = oturum_sahip_no();

    if ($sahip_no === null || $sahip_no < 1) {
        return [
            'return' => 0,
            'mesaj' => 'OAuth başlatmak için giriş yapmalısınız.',
        ];
    }

    $state = bin2hex(random_bytes(32));
    $url = google_oauth_yetkilendirme_urlu_uret($state);

    oturum_baslat();
    $_SESSION[GOOGLE_OAUTH_STATE_SESSION_KEY] = $state;

    return [
        'return' => 1,
        'mesaj' => 'Google OAuth URL hazır.',
        'url' => $url,
    ];
}

/**
 * Callback parametrelerinin tekil, bos olmayan string oldugunu kontrol eder.
 */
function google_oauth_tekil_parametre(array $parametreler, string $anahtar): ?string
{
    if (!array_key_exists($anahtar, $parametreler) || !is_string($parametreler[$anahtar])) {
        return null;
    }

    $deger = trim($parametreler[$anahtar]);

    return $deger === '' ? null : $deger;
}

/**
 * Authorization code'u Google token endpoint'inde refresh token'a cevirir.
 * Access token bu akista kullanilmaz ve saklanmaz.
 */
function google_oauth_refresh_token_al(string $code): string
{
    $ayarlar = google_oauth_ayarlarini_al();
    $oauth = new OAuth2([
        'tokenCredentialUri' => GOOGLE_OAUTH_TOKEN_URI,
        'redirectUri' => google_oauth_redirect_uri_al(),
        'clientId' => $ayarlar['client_id'],
        'clientSecret' => $ayarlar['client_secret'],
    ]);
    $oauth->setCode($code);
    $oauth->setGrantType('authorization_code');

    $token_response = $oauth->fetchAuthToken();
    $refresh_token = $token_response['refresh_token'] ?? null;
    unset($token_response);

    if (!is_string($refresh_token) || trim($refresh_token) === '') {
        throw new RuntimeException('Google refresh token alınamadı.');
    }

    return trim($refresh_token);
}

/**
 * Refresh token'in Google Ads PHP client tarafinda kullanilabilir credential
 * olarak kurulabildigini kontrol eder. Google Ads API istegi gondermez.
 */
function google_oauth_credential_dogrula(string $refresh_token): void
{
    $credential = (new OAuth2TokenBuilder())
        ->withClientId(config('GOOGLE_CLIENT_ID'))
        ->withClientSecret(config('GOOGLE_CLIENT_SECRET'))
        ->withRefreshToken($refresh_token)
        ->build();

    unset($credential);
}

/**
 * Secilen Google hesabini ve refresh token'i mevcut bagli hesap kaydina ekler.
 * Mevcut aktif kayit guncellenir; diger Google kayitlari pasiflenir.
 *
 * @param array{harici_kimlik: string, hesap_adi: ?string, yonetici: bool} $secili_hesap
 */
function google_oauth_refresh_token_kaydet(
    int $sahip_no,
    string $refresh_token,
    array $secili_hesap
): void
{
    $sifreli_refresh_token = sifrele($refresh_token);
    $baglanti = veritabani_baglan();

    $baglanti->beginTransaction();

    try {
        $sec = $baglanti->prepare(
            'SELECT `no` FROM `baglanmis_hesaplar` '
            . 'WHERE `sahip_no` = :sahip_no AND `platform` = :platform '
            . 'ORDER BY `aktif` DESC, `no` DESC LIMIT 1 FOR UPDATE'
        );
        $sec->execute([
            'sahip_no' => $sahip_no,
            'platform' => 'google',
        ]);
        $hesap = $sec->fetch();

        if (is_array($hesap) && isset($hesap['no'])) {
            $pasiflestir = $baglanti->prepare(
                'UPDATE `baglanmis_hesaplar` SET `aktif` = 0 '
                . 'WHERE `sahip_no` = :sahip_no AND `platform` = :platform '
                . 'AND `no` <> :no'
            );
            $pasiflestir->execute([
                'sahip_no' => $sahip_no,
                'platform' => 'google',
                'no' => (int) $hesap['no'],
            ]);

            $guncelle = $baglanti->prepare(
                'UPDATE `baglanmis_hesaplar` SET '
                . '`harici_kimlik` = :harici_kimlik, `hesap_adi` = :hesap_adi, '
                . '`refresh_token_sifreli` = :refresh_token_sifreli, '
                . '`erisim_token_sifreli` = NULL, `token_bitis` = NULL, `aktif` = 1 '
                . 'WHERE `no` = :no AND `sahip_no` = :sahip_no AND `platform` = :platform'
            );
            $guncelle->execute([
                'harici_kimlik' => $secili_hesap['harici_kimlik'],
                'hesap_adi' => $secili_hesap['hesap_adi'],
                'refresh_token_sifreli' => $sifreli_refresh_token,
                'no' => (int) $hesap['no'],
                'sahip_no' => $sahip_no,
                'platform' => 'google',
            ]);
        } else {
            $ekle = $baglanti->prepare(
                'INSERT INTO `baglanmis_hesaplar` '
                . '(`sahip_no`, `platform`, `harici_kimlik`, `hesap_adi`, `refresh_token_sifreli`, '
                . '`erisim_token_sifreli`, `token_bitis`, `aktif`) '
                . 'VALUES (:sahip_no, :platform, :harici_kimlik, :hesap_adi, '
                . ':refresh_token_sifreli, NULL, NULL, 1)'
            );
            $ekle->execute([
                'sahip_no' => $sahip_no,
                'platform' => 'google',
                'harici_kimlik' => $secili_hesap['harici_kimlik'],
                'hesap_adi' => $secili_hesap['hesap_adi'],
                'refresh_token_sifreli' => $sifreli_refresh_token,
            ]);
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
 * Google OAuth callback'ini dogrular, token'i alir ve sifreli olarak kaydeder.
 *
 * @param array<string, mixed>|null $parametreler Test edilebilirlik icin
 * callback parametreleri; NULL verilirse $_GET kullanilir.
 * @return array{return: int, mesaj: string}
 */
function google_oauth_donus(?array $parametreler = null): array
{
    $sahip_no = oturum_sahip_no();

    if ($sahip_no === null || $sahip_no < 1) {
        return [
            'return' => 0,
            'mesaj' => 'OAuth callback için giriş yapmalısınız.',
        ];
    }

    $parametreler = $parametreler ?? $_GET;
    $gelen_state = google_oauth_tekil_parametre($parametreler, 'state');
    oturum_baslat();
    $oturum_state = $_SESSION[GOOGLE_OAUTH_STATE_SESSION_KEY] ?? null;

    if (!is_string($oturum_state) || $gelen_state === null || !hash_equals($oturum_state, $gelen_state)) {
        return [
            'return' => 0,
            'mesaj' => 'OAuth state doğrulaması başarısız.',
        ];
    }

    unset($_SESSION[GOOGLE_OAUTH_STATE_SESSION_KEY]);

    if (google_oauth_tekil_parametre($parametreler, 'error') !== null) {
        return [
            'return' => 0,
            'mesaj' => 'Google OAuth işlemi kullanıcı tarafından reddedildi.',
        ];
    }

    $code = google_oauth_tekil_parametre($parametreler, 'code');

    if ($code === null) {
        return [
            'return' => 0,
            'mesaj' => 'OAuth authorization code alınamadı.',
        ];
    }

    $refresh_token = google_oauth_refresh_token_al($code);
    google_oauth_credential_dogrula($refresh_token);

    try {
        $hesaplar = google_ads_hesaplarini_kesfet($refresh_token);
        $secili_hesap = google_baglanti_hesabini_sec($hesaplar);
    } catch (Throwable $hata) {
        unset($refresh_token);
        throw $hata;
    }

    if ($secili_hesap === null) {
        unset($refresh_token);

        return [
            'return' => 0,
            'mesaj' => 'Bağlanabilecek Google Ads hesabı bulunamadı.',
        ];
    }

    google_oauth_refresh_token_kaydet($sahip_no, $refresh_token, $secili_hesap);

    unset($refresh_token);

    return [
        'return' => 1,
        'mesaj' => 'Google Ads hesabı başarıyla bağlandı.',
    ];
}