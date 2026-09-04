<?php

require_once __DIR__ . '/config.php';

/**
 * Tek PDO baglantisi olusturur ve sonraki cagrilarda ayni baglantiyi dondurur.
 */
function veritabani_baglan(): PDO
{
    static $baglanti = null;

    if ($baglanti instanceof PDO) {
        return $baglanti;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        config('DB_HOST'),
        config('DB_NAME')
    );

    $baglanti = new PDO(
        $dsn,
        config('DB_USER'),
        config('DB_PASS'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    return $baglanti;
}