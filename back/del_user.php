<?php
include_once '../config/database.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
$id = $_GET['id'];
$cid = $_GET['cid'];

$db= new PDO($dsn,$user,$pass,[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$stmt = $db->prepare('DELETE FROM users WHERE user_id = :id AND cid = :cid');
$stmt->execute(array(':id' => $id, ':cid' => $cid));

$stmt = $db->prepare('DELETE FROM user_pdf_access WHERE user_id = :id AND cid = :cid');
$stmt->execute(array(':id' => $id, ':cid' => $cid));

$stmt = $db->prepare('DELETE FROM user_redirects_access WHERE user_id = :id AND cid = :cid');
$stmt->execute(array(':id' => $id, ':cid' => $cid));

$stmt = $db->prepare('DELETE FROM popup_access WHERE user_id = :id AND cid = :cid');
$stmt->execute(array(':id' => $id, ':cid' => $cid));

$db = null;

header('Location: '.$_SERVER['HTTP_REFERER']);
exit();