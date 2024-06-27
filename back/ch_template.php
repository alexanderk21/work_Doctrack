<?php
include_once '../config/database.php';

$template_id = $_POST['template_id'];
// $from_id = $_POST['email'];
$subject = $_POST['subject'];
$content = $_POST['content'];
date_default_timezone_set('Asia/Tokyo');
$updated_at = date('Y-m-d H:i:s');

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare('UPDATE templates SET subject=:subject, content=:content, updated_at=:updated_at WHERE id = :id');
    $stmt->execute(array(':id' => $template_id, ':subject' => $subject, ':content' => $content, ':updated_at' => $updated_at));
    
    $stmt = $db->prepare("SELECT * FROM tags WHERE table_name=? AND table_id=?");
    $stmt->execute(['templates', $template_id]);
    $tag_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!empty($tag_data)) {
        $tag_id = $tag_data['id'];
        if (isset($_POST['detail_tags'])) {
            $tags = implode(',', $_POST['detail_tags']);
            $stmt = $db->prepare('UPDATE tags SET tags=:tags WHERE id =:id');
            $stmt->execute(array(':tags' => $tags, ':id'=>$tag_id));
        } else {
            $stmt = $db->prepare('DELETE FROM tags WHERE id = :id');
            $stmt->execute(array(':id' => $tag_id));
        }
    } else {
        if (isset($_POST['detail_tags'])) {
            $tags = implode(',', $_POST['detail_tags']);
            $sql = "INSERT INTO tags (table_name,table_id,tags) VALUES (:table_name,:table_id,:tags)";
            $stmt = $db->prepare($sql);
            $params = array(':table_name' => 'templates', ':table_id' => $template_id, ':tags' => $tags);
            $stmt->execute($params);
        }
    }

    $db = null;
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}

header('Location: ../template_setting.php');
exit();