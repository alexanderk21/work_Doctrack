<?php
include_once '../config/database.php';

$id = $_POST['del_tag_id'];

$db= new PDO($dsn,$user,$pass,[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$stmt = $db->prepare('DELETE FROM tag_sample WHERE id = :id');
$stmt->execute(array(':id' => $id));

$db = null;

header('Location: ../settings.php?tab=tag');
exit();