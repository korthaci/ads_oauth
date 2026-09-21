<?php

/**
 * Kampanya durumunu ENABLED, PAUSED veya REMOVED yapar.
 * Bu dosya yalnizca api/index.php tarafindan include edilir; is mantigi
 * php/servis/kampanya-servisi.php icindedir.
 */

if (!defined('ADS_OAUTH_API_INDEX')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../php/servis/kampanya-servisi.php';

/**
 * @return array<string, mixed>
 */
function api_kampanya_durdur(): array
{
    return kampanya_durumunu_degistir($_POST);
}