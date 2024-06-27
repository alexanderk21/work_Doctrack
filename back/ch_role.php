<?php
date_default_timezone_set('Asia/Tokyo');
include_once '../config/database.php';

$add_froms = $_POST['add_froms'];
$select_tag = $_POST['select_tag'];
$id = $_POST['id'];

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare('UPDATE clients SET from_emails = :from_emails, tags = :tags WHERE id = :id');
    $stmt->bindValue(':id', $id);
    $stmt->bindValue(':from_emails', implode(',', $add_froms));
    $stmt->bindValue(':tags', implode(',', $select_tag));
    $stmt->execute();

    echo '<script>
            location.href = "../users.php";
        </script>';
    $db = null;
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}