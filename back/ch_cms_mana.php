<?php
include_once '../config/database.php';
date_default_timezone_set('Asia/Tokyo');
$id = $_POST['id'];
$row = $_POST['row'];
asort($row);

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare("SELECT * FROM cmss WHERE id=?");
    $stmt->execute([$id]);
    $cms_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $content = is_null($cms_data['content']) ? [] : json_decode($cms_data['content'], true);
    $n_content = [];
    foreach ($row as $k => $r) {
        $n_content[] = $content[$k - 1];
    }

    $stmt = $db->prepare('UPDATE cmss SET content=:content WHERE id = :id');
    $stmt->execute(array(':id' => $id, ':content' => json_encode($n_content)));

    $db = null;
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
header('Location: ../cms_mana.php?id=' . $id);
exit();