<?php
session_start();
include_once '../config/database.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);


$client_id = $_POST['client_id'];
$subject = $_POST['subject'];
$item = $_POST['item'];
$fromId = $_POST['fromId'];
$template = $_POST['template'];

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $stmt = $db->prepare("SELECT * FROM form_users WHERE client_id=?");
    $stmt->execute([$client_id]);
    $form_users = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($form_users)) {
        $sql = "UPDATE form_users SET subject=:subject, items=:item, fromId=:fromId, template=:template WHERE client_id=:client_id";
        $stmt = $db->prepare($sql);
        $params = array(':client_id' => $client_id, ':subject' => $subject, ':item' => json_encode($item), ':fromId' => $fromId, ':template' => $template);
        $stmt->execute($params);
    } else {
        echo "<script>
                alert('連携メールアドレスを登録してください。');
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