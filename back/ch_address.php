<?php
include_once '../config/database.php';

$id = $_POST['id'];
$first_name = $_POST['first_name'];
$last_name = $_POST['last_name'];
$email = $_POST['email'];
date_default_timezone_set('Asia/Tokyo');

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($client['email'] == $email) {
        $stmt = $db->prepare('UPDATE clients SET last_name=:last_name, first_name=:first_name WHERE id = :id');
        $stmt->execute(array(':id' => $id, ':last_name' => $last_name, ':first_name' => $first_name));
    } else {
        $stmt = $db->prepare('UPDATE clients SET last_name=:last_name, first_name=:first_name, email=:email WHERE id = :id');
        $stmt->execute(array(':id' => $id, ':last_name' => $last_name, ':first_name' => $first_name, ':email' => $email));
        
        $stmt = $db->prepare('UPDATE froms SET smtp_host=:smtp_host, smtp_pw=:smtp_pw WHERE email = :email');
        $stmt->execute(array(':email' => $email, ':smtp_host' => '', ':smtp_pw' => ''));
    }
    
    $db = null;
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}

header('Location: ../users.php');
exit();