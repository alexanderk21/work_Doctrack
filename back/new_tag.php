<?php
session_start();
include_once '../config/database.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
$client_id = $_SESSION['client_id'];
$tag = $_POST['new_tag'];
$memo = $_POST['new_memo'];

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $stmt = $db->prepare("SELECT * FROM tag_sample WHERE client_id=? AND tag_name=?");
    $stmt->execute([$client_id, $tag]);
    $tag_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($tag_data)) {
        $sql = "INSERT INTO tag_sample (client_id,tag_name,memo) VALUES (:client_id,:tag_name,:memo)";
        $stmt = $db->prepare($sql);
        $params = array(':client_id' => $client_id, ':tag_name' => $tag, ':memo' => $memo);
        $stmt->execute($params);
    } else {
        echo "<script>
            alert('存在するタグ名です。');
        </script>";
    }

    $db = null;
    header('Location: ../settings.php?tab=tag');
    exit();
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}