<?php
session_start();

include_once '../config/database.php';

$cid = $_POST['cid'];
$last_name = $_POST['last_name'] ?? " ";
$first_name = $_POST['first_name'] ?? " ";
$email = $_POST['email'] ?? " ";

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    //Check Email
    $sql = "SELECT * FROM clients WHERE email = :email";
    $stmt = $db->prepare($sql);
    $stmt->execute([':email' => $email]);
    $results = $stmt->fetchAll();
    if (count($results) > 0) {
        echo "<script>
                alert('既に登録のあるメールアドレスです。');
                location.href = './../users.php';
            </script>";
        exit();
    }
    $sql = "SELECT * FROM froms WHERE email = :email";
    $stmt = $db->prepare($sql);
    $stmt->execute([':email' => $email]);
    $results = $stmt->fetchAll();
    if (count($results) > 0) {
        echo "<script>
                alert('既に登録のあるメールアドレスです。');
                location.href = './../users.php';
            </script>";
        exit();
    }
    //Get ID
    $user_ex_stmt = $db->prepare("SELECT COUNT(*) FROM clients WHERE id=?");
    do {
        $id = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, 4);
        $user_ex_stmt->execute([$id]);
        $count = $user_ex_stmt->fetchColumn();
    } while ($count > 0);
    //Insert Client_User
    $sql = "INSERT INTO clients (cid, id, email, last_name, first_name, role, tags) 
    VALUES (:cid, :id, :email, :last_name, :first_name, :role, '未選択')";
    $stmt = $db->prepare($sql);
    $params = array(':cid' => $cid, ':id' => $id, ':email' => $email, ':last_name' => $last_name, ':first_name' => $first_name, ':role' => 0);
    $stmt->execute($params);

    $sql = "INSERT INTO froms (cid, cuser_id, email, from_name, signature, smtp_host, smtp_pw) 
    VALUES (:cid, :cuser_id, :email, '', '', '', '')";
    $stmt = $db->prepare($sql);
    $params = array(':cid' => $cid, ':cuser_id' => $id, ':email' => $email);
    $stmt->execute($params);
    $db = null;

    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}