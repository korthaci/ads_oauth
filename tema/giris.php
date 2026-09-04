<?php
$sayfa_basligi = 'Giriş / Kayıt - ads_oauth';
require __DIR__ . '/layout/header.php';
?>
<h1>ads_oauth</h1>
<p>Google Ads hesabınızı bağlamak için giriş yapın veya yeni hesap oluşturun.</p>

<?php if ($mesaj !== ''): ?>
    <p role="alert"><?= htmlspecialchars($mesaj, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<section aria-labelledby="giris-baslik">
    <h2 id="giris-baslik">Giriş</h2>
    <form method="post" action="index.php">
        <input type="hidden" name="form_islem" value="giris">
        <p>
            <label for="giris-eposta">E-posta</label><br>
            <input id="giris-eposta" name="eposta" type="email" autocomplete="email" required>
        </p>
        <p>
            <label for="giris-sifre">Şifre</label><br>
            <input id="giris-sifre" name="sifre" type="password" autocomplete="current-password" required>
        </p>
        <button type="submit">Giriş</button>
    </form>
</section>

<section aria-labelledby="kayit-baslik">
    <h2 id="kayit-baslik">Kayıt</h2>
    <form method="post" action="index.php">
        <input type="hidden" name="form_islem" value="kayit">
        <p>
            <label for="kayit-ad-soyad">Ad Soyad</label><br>
            <input id="kayit-ad-soyad" name="ad_soyad" type="text" autocomplete="name" required>
        </p>
        <p>
            <label for="kayit-eposta">E-posta</label><br>
            <input id="kayit-eposta" name="eposta" type="email" autocomplete="email" required>
        </p>
        <p>
            <label for="kayit-sifre">Şifre</label><br>
            <input id="kayit-sifre" name="sifre" type="password" autocomplete="new-password" required>
        </p>
        <button type="submit">Kayıt</button>
    </form>
</section>
<?php require __DIR__ . '/layout/footer.php'; ?>