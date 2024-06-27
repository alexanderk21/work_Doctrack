<?php
include_once '../config/database.php';

$id = $_POST['id'];
$title = $_POST['title'];
$memo = $_POST['memo'];
$tag = json_encode($_POST['detail_tags']);
date_default_timezone_set('Asia/Tokyo');

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    if ($_FILES['logo']['size'] > 0) {

        // ファイルがアップロードされた場合、画像を保存する
        $file = $_FILES['logo'];
        $unique_file = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, 10);

        $new_file_name = "./../favi_logos/" . $unique_file . ".png";

        // ファイルタイプとサイズのチェック
        $allowed_types = ['image/png', 'image/jpeg', 'image/gif'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowed_types)) {
            echo "<script>alert('無効なファイルタイプです。'); window.location.href='./../cms.php';</script>";
            exit();
        }

        if ($file['size'] > $max_file_size) {
            echo "<script>alert('ファイルサイズが大きすぎます。'); window.location.href='./../cms.php';</script>";
            exit();
        }

        if (file_exists($new_file_name)) {
            unlink($new_file_name);
        }


        if (move_uploaded_file($file['tmp_name'], $new_file_name)) {
            $command = "convert " . escapeshellarg($new_file_name) . " png:" . escapeshellarg($new_file_name);
            exec($command);

            // img_nameカラムを更新する
            $stmt = $db->prepare("UPDATE cmss SET logo_name=?, logo_img=? WHERE id=?");
            $stmt->execute([$file['name'], $unique_file, $id]);

        } else {
            echo "<script>alert('画像のアップロードに失敗しました。'); window.location.href='./../popup.php';</script>";
            exit();
        }
    }

    $stmt = $db->prepare('UPDATE cmss SET title=:title, memo=:memo, tag=:tag WHERE id = :id');
    $stmt->execute(array(':id' => $id, ':title' => $title, ':memo' => $memo, ':tag' => $tag));

    $db = null;
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}

header('Location: ../cms.php');
exit();