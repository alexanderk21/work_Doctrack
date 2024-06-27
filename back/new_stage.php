<?php
include_once '../config/database.php';

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = "INSERT INTO stages (client_id,stage_name,stage_point,stage_memo) VALUES (:client_id,:stage_name,:stage_point,:stage_memo)";
    $stmt = $db->prepare($sql);
    $params = array(':client_id' => $_POST['client_id'],':stage_name' => $_POST['stage_name'],':stage_point' => $_POST['stage_point'],':stage_memo' => $_POST['stage_memo']);
    $stmt->execute($params);

    $db = null;
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
?>