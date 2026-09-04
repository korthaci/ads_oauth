<?php

/**
 * PHP oturumunu, daha once baslatilmadiysa baslatir.
 */
function oturum_baslat(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * Giris yapan site sahibinin numarasini oturuma yazar.
 */
function oturum_sahip_no_yaz(int $sahip_no): void
{
    oturum_baslat();
    $_SESSION['sahip_no'] = $sahip_no;
}

/**
 * Aktif oturumdaki site sahibi numarasini dondurur.
 */
function oturum_sahip_no(): ?int
{
    oturum_baslat();

    if (!isset($_SESSION['sahip_no'])) {
        return null;
    }

    return (int) $_SESSION['sahip_no'];
}

/**
 * Aktif oturumu temizler ve sonlandirir.
 */
function oturum_sonlandir(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        oturum_baslat();
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cerez_parametreleri = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $cerez_parametreleri['path'],
            $cerez_parametreleri['domain'],
            (bool) $cerez_parametreleri['secure'],
            (bool) $cerez_parametreleri['httponly']
        );
    }

    session_destroy();
}