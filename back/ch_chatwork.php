<?php
session_start();
include_once '../config/database.php';

$client_id = $_POST['client_id'];
preg_match('/\d+/', $_POST['chatwork_id'], $matches); // Match one or more digits
$chatwork_id = $matches[0]; 

$stage = $_POST['stage'];
if (empty($stage)) {
    echo "<script>alert('ステージを選択してください。'); 
            window.location.href='./../settings.php';
        </script>";
    exit;
}
$reach_stage = implode(',', array_keys($stage));
try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare('UPDATE clients SET chatwork_id=:chatwork_id,reach_stage=:reach_stage WHERE id = :id');
    $stmt->execute(array(':id' => $client_id, ':chatwork_id' => $chatwork_id, ':reach_stage' => $reach_stage));

    $db = null;
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
header('Location: ../settings.php?tab=notice');
exit();