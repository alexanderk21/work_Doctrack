<?php
session_start();
include_once '../config/database.php';

$tracking_id = $_POST['tracking_id'];
$title = $_POST['detail_title'];
$url = $_POST['redirect_url'];
date_default_timezone_set('Asia/Tokyo');
$updated_at = date('Y-m-d H:i:s');
$memo = $_POST['memo'];

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    if ($_FILES['redl_img']['size'] > 0) {
        $unique_file = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, 10);
        $document_url = $_SERVER['DOCUMENT_ROOT'];
        $file = $_FILES['redl_img'];
        $redl_name = $file['name'];
        $tempFilePath = $file['tmp_name'];
    
        $imageType = exif_imagetype($tempFilePath);
        $fileExtension = image_type_to_extension($imageType, false);
        $redl_img = $unique_file . '.' . $fileExtension;
        move_uploaded_file($tempFilePath, $document_url . '/redl_img/' . $redl_img);
        
        $stmt = $db->prepare('UPDATE redirects SET redl_name=:redl_name,redl_img=:redl_img WHERE id = :id');
        $stmt->execute(array(':id' => $tracking_id, ':redl_name' => $redl_name, ':redl_img' => $redl_img));

    }
    $stmt = $db->prepare('UPDATE redirects SET title=:title,url=:url,updated_at=:updated_at,memo=:memo WHERE id = :id');
    $stmt->execute(array(':id' => $tracking_id, ':title' => $title, ':url' => $url, ':updated_at' => $updated_at, ':memo' => $memo));

    $stmt = $db->prepare("SELECT * FROM tags WHERE table_name=? AND table_id=?");
    $stmt->execute(['redirects', $tracking_id]);
    $tag_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($tag_data)) {
        $tag_id = $tag_data['id'];
        if (isset($_POST['detail_tags'])) {
            $tags = implode(',', $_POST['detail_tags']);
            $stmt = $db->prepare('UPDATE tags SET tags=:tags WHERE id =:id');
            $stmt->execute(array(':tags' => $tags, ':id' => $tag_id));
        } else {
            $stmt = $db->prepare('DELETE FROM tags WHERE id = :id');
            $stmt->execute(array(':id' => $tag_id));
        }
    } else {
        if (isset($_POST['detail_tags'])) {
            $tags = implode(',', $_POST['detail_tags']);
            $sql = "INSERT INTO tags (table_name,table_id,tags) VALUES (:table_name,:table_id,:tags)";
            $stmt = $db->prepare($sql);
            $params = array(':table_name' => 'redirects', ':table_id' => $tracking_id, ':tags' => $tags);
            $stmt->execute($params);
        }
    }
    $db = null;
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit();