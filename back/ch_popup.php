<?php
if (isset($_POST['id'])) {
    session_start();
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    include_once '../config/database.php';

    $id = $_POST['id'];
    // $title = $_POST['detail_title'];
    $url = $_POST['url'];
    $popup_trigger = $_POST['popup_trigger'];
    $trigger_parameter = $_POST['trigger_parameter'];
    if (isset($_POST['trigger_parameter2'])) {
        $trigger_parameter2 = $_POST['trigger_parameter2'];
    } else {
        $trigger_parameter2 = 0;
    }
    $memo = $_POST['memo'];

    try {
        $db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        if ($_FILES['popup_img']['size'] > 0) {

            // ファイルがアップロードされた場合、画像を保存する
            $file = $_FILES['popup_img'];
            $new_file_name = "./../popup_img/" . $id . ".png";

            // ファイルタイプとサイズのチェック
            $allowed_types = ['image/png', 'image/jpeg', 'image/gif'];
            $max_file_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($file['type'], $allowed_types)) {
                echo "<script>alert('無効なファイルタイプです。'); window.location.href='./../popup.php';</script>";
                exit();
            }

            if ($file['size'] > $max_file_size) {
                echo "<script>alert('ファイルサイズが大きすぎます。'); window.location.href='./../popup.php';</script>";
                exit();
            }

            if (file_exists($new_file_name)) {
                unlink($new_file_name);
            }


            if (move_uploaded_file($file['tmp_name'], $new_file_name)) {
                $command = "convert " . escapeshellarg($new_file_name) . " png:" . escapeshellarg($new_file_name);
                exec($command);

                // img_nameカラムを更新する
                $stmt = $db->prepare("UPDATE popups SET img_name=? WHERE id=?");
                $stmt->execute([$file['name'], $id]);


            } else {
                echo "<script>alert('画像のアップロードに失敗しました。'); window.location.href='./../popup.php';</script>";
                exit();
            }
        }

        $stmt = $db->prepare('UPDATE popups SET url=?, popup_trigger=?,trigger_parameter=?,trigger_parameter2=?,memo=? WHERE id = ?');
        $stmt->execute([$url, $popup_trigger, $trigger_parameter, $trigger_parameter2, $memo, $id]);


        $stmt = $db->prepare("SELECT * FROM tags WHERE table_name=? AND table_id=?");
        $stmt->execute(['popups', $id]);
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
            $tags = implode(',', ($_POST['detail_tags'] ?? []));
            if (!empty($tags)) {
                $sql = "INSERT INTO tags (table_name,table_id,tags) VALUES (:table_name,:table_id,:tags)";
                $stmt = $db->prepare($sql);
                $params = array(':table_name' => 'popups', ':table_id' => $id, ':tags' => $tags);
                $stmt->execute($params);
            }
        }

        $db = null;
    } catch (PDOException $e) {
        echo '接続失敗' . $e->getMessage();
        exit();
    }
    ;

    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
} else {
    exit();
}
