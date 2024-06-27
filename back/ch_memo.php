<?php
session_start();
include_once '../config/database.php';

$user_id = $_POST['user_id'];
$service_detail = $_POST['service_detail'];
$hope_matching = $_POST['hope_matching'];

try{
    $db= new PDO($dsn,$user,$pass,[
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare('UPDATE users SET service_detail=:service_detail,hope_matching=:hope_matching  WHERE user_id = :user_id');
    $stmt->execute(array(':service_detail' => $service_detail,':hope_matching' => $hope_matching,':user_id' => $user_id));

    $db = null;
}catch(PDOException $e){
    echo '接続失敗' . $e->getMessage();
    exit();
};

header('Location: '.$_SERVER['HTTP_REFERER']);
exit();
?>