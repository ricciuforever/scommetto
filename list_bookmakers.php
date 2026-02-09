<?php
require 'bootstrap.php';
$apiKey = \App\Config\Config::get('FOOTBALL_API_KEY');
$url = 'https://v3.football.api-sports.io/bookmakers';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['x-apisports-key: ' . $apiKey]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
echo $res;
