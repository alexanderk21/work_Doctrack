<?php
session_start();
include_once '../config/database.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$title = $_POST['title'];
$client_id = $_POST['cid'];
$tags = $_POST['select_tags'] ?? [];
$tracking_id = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, 5);

$tag = json_encode($tags);
try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = "INSERT INTO cmss (client_id,cms_id,title,tag) VALUES (:client_id,:cms_id,:title,:tag)";
    $stmt = $db->prepare($sql);
    $params = array(':client_id' => $client_id, ':cms_id' => $tracking_id, ':title' => $title, ':tag' => $tag);
    $stmt->execute($params);

    $db = null;
    header('Location: ../cms.php');
    exit();
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}