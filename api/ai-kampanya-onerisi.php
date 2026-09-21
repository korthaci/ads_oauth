<?php

/**
 * AI kampanya önerisi HTTP köprüsü.
 * Yalnızca api/index.php tarafından include edilir; Ads AI mantığı ai/ altında tutulur.
 */

if (!defined('ADS_OAUTH_API_INDEX')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../ai/ads-yardimci.php';

/**
 * @return array<string, mixed>
 */
function api_ai_kampanya_onerisi(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return [
            'return' => 0,
            'mesaj' => 'AI önerisi için POST isteği gerekir.',
        ];
    }

    return ads_yardimci_onerisi($_POST);
}