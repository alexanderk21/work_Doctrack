<?php
include_once '../config/database.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$cid = $_POST['cid'];
$tracking_id = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, 5);
$title = $_POST['title'];
$url = $_POST['redirect_url'];
$memo = $_POST['memo'];

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = "INSERT INTO redirects (cid,id,title,url, memo) VALUES (:cid,:id,:title,:url, :memo)";
    $stmt = $db->prepare($sql);
    $params = array(':cid' => $cid, ':id' => $tracking_id, ':title' => $title, ':url' => $url, ':memo' => $memo);
    $stmt->execute($params);

    if (isset($_POST['select_tags'])) {
        $tags = implode(',', $_POST['select_tags']);
        $sql = "INSERT INTO tags (table_name,table_id,tags) VALUES (:table_name,:table_id,:tags)";
        $stmt = $db->prepare($sql);
        $params = array(':table_name' => 'redirects', ':table_id' => $tracking_id, ':tags' => $tags);
        $stmt->execute($params);
    }

    $db = null;
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}