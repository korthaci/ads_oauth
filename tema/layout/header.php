<?php
$sayfa_basligi = $sayfa_basligi ?? 'ads_oauth';
?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($sayfa_basligi, ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
<main>