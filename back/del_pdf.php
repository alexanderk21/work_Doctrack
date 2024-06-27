<?php
include_once '../config/database.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
$pdf_id = $_GET['pdf_id'];

$db= new PDO($dsn,$user,$pass,[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$stmt = $db->prepare('UPDATE pdf_versions SET deleted = 1 WHERE pdf_id = :pdf_id');
$stmt->execute(array(':pdf_id' => $pdf_id));

$db = null;

header('Location: '.$_SERVER['HTTP_REFERER']);
exit();
?> 