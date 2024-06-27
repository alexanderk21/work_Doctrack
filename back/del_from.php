<?php
include_once '../config/database.php';

$id = $_POST['id'];
$cid = $_POST['cid'];

$db= new PDO($dsn,$user,$pass,[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$stmt = $db->prepare('DELETE FROM froms WHERE id = :id');
$stmt->execute(array(':id' => $id));
$stmt = $db->prepare('DELETE FROM templates WHERE from_id = :id AND cid=:cid');
$stmt->execute(array(':id' => $id, ':cid' => $cid));

$db = null;

header('Location: '.$_SERVER['HTTP_REFERER']);
exit();