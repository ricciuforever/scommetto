<?php
$dbFile = 'c:\Users\ricci\OneDrive\Desktop\SVILUPPO\scommetto\data\gianik.sqlite';
try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $res = $pdo->query('SELECT sport, count(*) as cnt FROM bets GROUP BY sport')->fetchAll(PDO::FETCH_ASSOC);
    print_r($res);
} catch (Exception $e) {
    echo $e->getMessage();
}
