<?php
include_once '../config/database.php';

$id = $_POST['id'];

$db= new PDO($dsn,$user,$pass,[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$stmt = $db->prepare('DELETE FROM schedules WHERE id = :id');
$stmt->execute(array(':id' => $id));

$db = null;

header('Location: '.$_SERVER['HTTP_REFERER']);
exit();
?> 