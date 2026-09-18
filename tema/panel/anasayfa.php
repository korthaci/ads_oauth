<?php
$sayfa_basligi = 'Panel - ads oauth';
require __DIR__ . '/../layout/header.php';
?>
<h1>ads oauth | Hoş geldiniz</h1>
<?php if ($google_panel_baglanti_kontrol_hatasi): ?>
    <p role="alert">Google Ads bağlantı durumu kontrol edilemedi.</p>
<?php elseif (is_array($google_panel_baglantisi)): ?>
    <p>Google Ads hesabı bağlı.</p>

    <?php if (($google_panel_baglantisi['hesap_adi'] ?? null) !== null
        && $google_panel_baglantisi['hesap_adi'] !== ''): ?>
        <p>
            Hesap: <?= htmlspecialchars($google_panel_baglantisi['hesap_adi'], ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <?php if (($google_panel_baglantisi['harici_kimlik'] ?? null) !== null
        && $google_panel_baglantisi['harici_kimlik'] !== ''): ?>
        <p>
            Customer ID: <?= htmlspecialchars($google_panel_baglantisi['harici_kimlik'], ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php else: ?>
        <p>Customer bilgisi henüz keşfedilmedi.</p>
    <?php endif; ?>
<?php else: ?>
    <p>Oturum açıldı. Google Ads hesabınızı bağlayarak OAuth akışını başlatabilirsiniz.</p>

    <p>
        <button type="button" id="google-oauth-baslat">Google Ads hesabını bağla</button>
    </p>
    <p id="google-oauth-mesaj" role="status" aria-live="polite"></p>

    <script>
    document.getElementById('google-oauth-baslat').addEventListener('click', function () {
        var dugme = this;
        var mesaj = document.getElementById('google-oauth-mesaj');

        dugme.disabled = true;
        mesaj.textContent = 'Google OAuth hazırlanıyor...';

        fetch('api/index.php?islem=oauth-baslat', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (yanit) { return yanit.json(); })
            .then(function (cevap) {
                if (cevap.return === 1 && typeof cevap.url === 'string' && cevap.url !== '') {
                    window.location.href = cevap.url;
                    return;
                }

                dugme.disabled = false;
                mesaj.textContent = cevap.mesaj || 'OAuth başlatılamadı.';
            })
            .catch(function () {
                dugme.disabled = false;
                mesaj.textContent = 'OAuth başlatılamadı.';
            });
    });
    </script>
<?php endif; ?>

<p><a href="index.php?islem=cikis">Çıkış yap</a></p>
<?php require __DIR__ . '/../layout/footer.php'; ?>