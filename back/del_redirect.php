<?php
include_once '../config/database.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
$id = $_GET['id'];

$db= new PDO($dsn,$user,$pass,[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$stmt = $db->prepare('UPDATE redirects SET deleted = 1 WHERE id = :id');
$stmt->execute(array(':id' => $id));

$db = null;

header('Location: '.$_SERVER['HTTP_REFERER']);
exit();
?> 