<?php
$sayfa_basligi = 'Giriş - ads_oauth';
require __DIR__ . '/layout/header.php';
?>
<h1>ads_oauth</h1>
<p>Google Ads hesabınızı bağlamak için giriş yapın.</p>

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

<p>Kayıt davetle sınırlıdır; hesabınız için yöneticiyle iletişime geçin.</p>
<?php require __DIR__ . '/layout/footer.php'; ?>