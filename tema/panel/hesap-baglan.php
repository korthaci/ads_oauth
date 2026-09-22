<?php
$sayfa_basligi = 'Google Ads hesabı seç - ads oauth';
$google_oauth_bekleyen_hesaplar = google_oauth_bekleyen_hesaplari_al();
require __DIR__ . '/../layout/header.php';
?>
<h1>Google Ads hesabı seç</h1>

<?php if ($google_oauth_bekleyen_hesaplar === []): ?>
    <p role="alert">Seçilebilecek Google Ads hesabı bulunamadı veya seçim oturumu sona erdi.</p>
    <p><a href="index.php">Ana sayfaya dön</a></p>
<?php else: ?>
    <p>Bağlamak istediğiniz Google Ads hesabını seçin.</p>

    <form id="google-ads-hesap-secim-formu">
        <fieldset>
            <legend>Google Ads hesapları</legend>
            <?php foreach ($google_oauth_bekleyen_hesaplar as $hesap): ?>
                <?php
                $harici_kimlik = (string) $hesap['harici_kimlik'];
                $hesap_adi = trim((string) ($hesap['hesap_adi'] ?? ''));
                ?>
                <label>
                    <input
                        type="radio"
                        name="harici_kimlik"
                        value="<?= htmlspecialchars($harici_kimlik, ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                    <?= htmlspecialchars($hesap_adi !== '' ? $hesap_adi : 'Ads hesabı', ENT_QUOTES, 'UTF-8') ?>
                    (Customer ID: <?= htmlspecialchars($harici_kimlik, ENT_QUOTES, 'UTF-8') ?>)
                </label><br>
            <?php endforeach; ?>
        </fieldset>

        <p>
            <button type="submit">Seçilen hesabı bağla</button>
        </p>
        <p id="google-hesap-secim-mesaj" role="status" aria-live="polite"></p>
    </form>

    <script src="/ads-oauth/assets/js/hesap-baglan.js" defer></script>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
