<?php
include_once '../config/database.php';

$id = $_POST['id'];

$db= new PDO($dsn,$user,$pass,[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$stmt = $db->prepare('DELETE FROM templates WHERE id = :id');
$stmt->execute(array(':id' => $id));

$stmt = $db->prepare('DELETE FROM tags WHERE table_id = :table_id AND table_name = :table_name');
$stmt->execute(array(':table_id' => $id, ':table_name' => 'templates'));

$db = null;

header('Location: '.$_SERVER['HTTP_REFERER']);
exit();