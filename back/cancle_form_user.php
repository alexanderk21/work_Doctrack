<?php
session_start();
include_once '../config/database.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
$client_id = $_SESSION['client_id'];

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $stmt = $db->prepare("SELECT * FROM form_users WHERE client_id=?");
    $stmt->execute([$client_id]);
    $form_users = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($form_users)) {
        $sql = "UPDATE form_users SET token=:token, status = 0 WHERE client_id=:client_id";
        $stmt = $db->prepare($sql);
        $params = array(':client_id' => $client_id, ':token' => '');
        $stmt->execute($params);
    } else {
        echo "<script>
                alert('登録された連携アドレスはありません。');
                location.href = './../settings.php?tab=form';
            </script>";
        exit();
    }

    $db = null;
    header('Location: ../settings.php?tab=form');
    exit();
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}