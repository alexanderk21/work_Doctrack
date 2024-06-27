<?php

if ($_POST['division'] === "メール送信") {
    echo "<script>
            alert('差出アドレスを選択してください。');
            location.href = './../template_setting.php';
        </script>";
    exit();
}

include_once '../config/database.php';

$cid = $_POST['cid'];
// $email = $_POST['email'];
$subject = $_POST['subject'];
$content = $_POST['content'];
$division = $_POST['division'];

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    // $stmt = $db->prepare("SELECT * FROM froms WHERE email=? AND cid=?");
    // $stmt->execute([$email, $cid]);
    // $data = $stmt->fetch(PDO::FETCH_ASSOC);

    $sql = "INSERT INTO templates (cid,subject,content,division) VALUES (:cid,:subject,:content,:division)";
    $stmt = $db->prepare($sql);
    $params = array(':cid' => $cid, ':subject' => $subject, ':content' => $content, ':division' => $division);
    $stmt->execute($params);

    $table_id = $db->lastInsertId();
    if (isset($_POST['select_tags'])) {
        $tags = implode(',', $_POST['select_tags']);
        $sql = "INSERT INTO tags (table_name,table_id,tags) VALUES (:table_name,:table_id,:tags)";
        $stmt = $db->prepare($sql);
        $params = array(':table_name' => 'templates', ':table_id' => $table_id, ':tags' => $tags);
        $stmt->execute($params);
    }

    $db = null;
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}