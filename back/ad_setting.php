<?php
include_once '../config/database.php';

$url = $_POST['url'];
$cid = $_POST['cid'];
date_default_timezone_set('Asia/Tokyo');
$updated_at = date('Y-m-d H:i:s');

try{
    $db= new PDO($dsn,$user,$pass,[
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    if(isset($_FILES['file']) && $_FILES['file']['name'] != "") {
        $target_dir = "./../img_ad_setting/";
        $target_file = $target_dir . $cid .".png";
        $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
        $allowed_types = array("jpg", "jpeg", "png", "PNG", "gif"); // 許可する拡張子を指定する
        
        if(in_array($imageFileType, $allowed_types)) {
            
            if(file_exists($target_file)){
                unlink($target_file);
            }
            if(!move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
                throw new Exception("ファイルのアップロード中にエラーが発生しました。");
            }

            $stmt = $db->prepare('SELECT * FROM ad_setting WHERE cid = :cid');
            $stmt->execute(array(':cid' => $cid));
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if($result) {
                $stmt = $db->prepare('UPDATE ad_setting SET file_name=:file_name,url=:url,updated_at=:updated_at WHERE cid = :cid');
            } else {
                $stmt = $db->prepare('INSERT INTO ad_setting (cid, file_name, url, updated_at) VALUES (:cid, :file_name, :url, :updated_at)');
            }
            $stmt->execute(array(':cid' => $cid,':file_name' => basename($_FILES["file"]["name"]),':url' => $url,':updated_at' => $updated_at));
        } else {
            throw new Exception("画像ファイル以外はアップロードできません。");
        }
    } else {
        $stmt = $db->prepare('UPDATE ad_setting SET url=:url,updated_at=:updated_at WHERE cid = :cid');
        $stmt->execute(array(':cid' => $cid, ':url' => $url, ':updated_at' => $updated_at));
    }

    $db = null;
} catch(PDOException $e){
    echo 'Database connection failed: ' . $e->getMessage();
    exit();
} catch(Exception $e){
    echo 'Error: ' . $e->getMessage();
    exit();
}

header('Location: '.$_SERVER['HTTP_REFERER']);
exit();
?>
