<?php
include_once './../config/database.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    //Change Id of clients table.
    $stmt = $db->prepare('SELECT * FROM users');
    $stmt->execute();
    $users = $stmt->fetchAll();

    foreach($users as $user){

        $url = $user['url'];

        $parsed_url = $url ? parse_url($url) : [];

        if(!empty($parsed_url) && isset($parsed_url['scheme']) && isset($parsed_url['host'])){
            $url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
            $stmt = $db->prepare('UPDATE users SET url=:url WHERE user_id = :user_id');
            $stmt->execute(array(':user_id' => $user['user_id'], ':url' => $url));
        }

    }

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}