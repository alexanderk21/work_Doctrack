<?php
include_once '../config/database.php';
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

$client_id = $_POST['client_id'];
$unique_file = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, 10);
$document_url = $_SERVER['DOCUMENT_ROOT'];
if (isset($_FILES['ch_favi'])) {
    $file = $_FILES['ch_favi'];
    $favi_name = $file['name'];
    $tempFilePath = $file['tmp_name'];

    $imageType = exif_imagetype($tempFilePath);
    $fileExtension = image_type_to_extension($imageType, false);
    $favi_img = $unique_file . '.' . $fileExtension;
    move_uploaded_file($tempFilePath, $document_url . '/favi_logos/' . $favi_img);
    try {
        $db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $stmt = $db->prepare("SELECT * FROM favi_logos WHERE client_id = ?");
        $stmt->execute([$client_id]);
        $favi_logos = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!isset($favi_logos['client_id'])) {
            $sql = "INSERT INTO favi_logos (client_id,favi_name,favi_img) VALUES (:client_id,:favi_name,:favi_img)";
            $stmt = $db->prepare($sql);
            $params = array(':client_id' => $client_id, ':favi_name' => $favi_name, ':favi_img' => $favi_img);
            $stmt->execute($params);
        } else {
            $stmt = $db->prepare('UPDATE favi_logos SET favi_name=:favi_name,favi_img=:favi_img WHERE client_id = :client_id');
            $stmt->execute(array(':client_id' => $client_id, ':favi_name' => $favi_name, ':favi_img' => $favi_img));
        }
    } catch (PDOException $e) {
        echo '接続失敗' . $e->getMessage();
        exit();
    }
}
if (isset($_FILES['ch_logo'])) {
    $file = $_FILES['ch_logo'];
    $logo_name = $file['name'];
    $tempFilePath = $file['tmp_name'];

    $imageType = exif_imagetype($tempFilePath);
    $fileExtension = image_type_to_extension($imageType, false);
    $logo_img = $unique_file . '.' . $fileExtension;
    move_uploaded_file($tempFilePath, $document_url . '/favi_logos/' . $logo_img);
    try {
        $db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $stmt = $db->prepare("SELECT * FROM favi_logos WHERE client_id = ?");
        $stmt->execute([$client_id]);
        $favi_logos = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!isset($favi_logos['client_id'])) {
            $sql = "INSERT INTO favi_logos (client_id,logo_name,logo_img) VALUES (:client_id,:logo_name,:logo_img)";
            $stmt = $db->prepare($sql);
            $params = array(':client_id' => $client_id, ':logo_name' => $logo_name, ':logo_img' => $logo_img);
            $stmt->execute($params);
        } else {
            $stmt = $db->prepare('UPDATE favi_logos SET logo_name=:logo_name,logo_img=:logo_img WHERE client_id = :client_id');
            $stmt->execute(array(':client_id' => $client_id, ':logo_name' => $logo_name, ':logo_img' => $logo_img));
        }
    } catch (PDOException $e) {
        echo '接続失敗' . $e->getMessage();
        exit();
    }
}
$db = null;
header('Location: ../settings.php?tab=other');
exit();
