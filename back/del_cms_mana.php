<?php
include_once '../config/database.php';
date_default_timezone_set('Asia/Tokyo');
$id = $_POST['id'];
$row_no = $_POST['row_no'];

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare("SELECT * FROM cmss WHERE id=?");
    $stmt->execute([$id]);
    $cms_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $content = is_null($cms_data['content']) ? [] : json_decode($cms_data['content'], true);

    print_r($row_no);
    unset($content[$row_no]);
    $n_content = array_values($content);

    $stmt = $db->prepare('UPDATE cmss SET content=:content WHERE id = :id');
    $stmt->execute(array(':id' => $id, ':content' => json_encode($n_content)));

    $db = null;
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}

header('Location: '.$_SERVER['HTTP_REFERER'] . '&status=edit');
exit();