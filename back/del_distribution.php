<?php
include_once '../config/database.php';

$token = $_POST['token'];

$db= new PDO($dsn,$user,$pass,[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$stmt = $db->prepare('DELETE FROM logs WHERE token = :token');
$stmt->execute(array(':token' => $token));

$db = null;

header('Location: '.$_SERVER['HTTP_REFERER']);
exit();