<?php
include_once '../config/database.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
$id = $_GET['id'];

$db= new PDO($dsn,$user,$pass,[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$stmt = $db->prepare('DELETE FROM clients WHERE id = :id');
$stmt->execute(array(':id' => $id));

$stmt = $db->prepare('UPDATE froms SET cuser_id = ""  WHERE cuser_id = :id');
$stmt->execute(array(':id' => $id));

$db = null;

header('Location: '.$_SERVER['HTTP_REFERER']);
exit();