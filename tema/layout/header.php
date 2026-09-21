<?php
$sayfa_basligi = $sayfa_basligi ?? 'ads_oauth';
?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($sayfa_basligi, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="tema/css/panel.css">
</head>
<body>
<?php if (function_exists('oturum_sahip_no') && oturum_sahip_no() !== null): ?>
<header class="site-header">
    <nav class="site-nav" aria-label="Ana menü">
        <a href="index.php">Ana Sayfa</a>
        <a href="index.php?islem=kampanyalarim">Kampanyalarım</a>
        <a href="index.php?islem=kampanya-sihirbazi">Kampanya Oluştur</a>
        <a href="index.php?islem=cikis">Çıkış Yap</a>
    </nav>
</header>
<?php endif; ?>
<main class="panel-main">