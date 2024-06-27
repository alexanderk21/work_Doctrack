<?php
include_once '../config/database.php';

$id = $_POST['ch_id'];
$tag_name = $_POST['ch_tag_name'];
$memo = $_POST['ch_memo'];
date_default_timezone_set('Asia/Tokyo');

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare("SELECT * FROM tag_sample WHERE client_id=? AND tag_name=?");
    $stmt->execute([$client_id, $tag_name]);
    $tag_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty ($tag_data)) {
        $stmt = $db->prepare('UPDATE tag_sample SET tag_name=:tag_name, memo=:memo WHERE id = :id');
        $stmt->execute(array(':id' => $id, ':tag_name' => $tag_name, ':memo' => $memo));
    } else {
        echo "<script>
            alert('存在するタグ名です。');
        </script>";
    }
    
    $db = null;
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}

header('Location: ../settings.php?tab=tag');
exit();