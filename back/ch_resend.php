<?php
session_start();
include_once '../config/database.php';

$client_id = $_POST['client_id'];
$resend_temp = $_POST['resend_temp'];
$resend_date = $_POST['resend_date'];
$resend_time = $_POST['resend_time'];
$resend_timing = $resend_date . ',' . $resend_time;

if ($resend_temp == '') {
    echo "<script>alert('テンプレートを選択してください。'); 
    window.location.href='./../settings.php?tab=resend';
    </script>";
    exit;
}
try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare('UPDATE clients SET resend_temp=:resend_temp,resend_timing=:resend_timing WHERE id = :id');
    $stmt->execute(array(':id' => $client_id, ':resend_temp' => $resend_temp, ':resend_timing' => $resend_timing));

    $db = null;
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
header('Location: ../settings.php?tab=resend');
exit();