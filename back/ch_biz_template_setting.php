<?php
if (!empty($_POST['id'])) {
    include_once '../config/database.php';

    $cid = $_POST['cid'];
    $template_id = $_POST['id'];
    $hour = $_POST['hour'];

    try {
        $db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        // 既存のレコードを検索
        $stmt = $db->prepare('SELECT * FROM biz_template_setting WHERE cid = :cid');
        $stmt->execute(array(':cid' => $cid));
        $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingRecord) {
            // 既存のレコードが存在する場合はUPDATE
            $stmt = $db->prepare('UPDATE biz_template_setting SET template_id=:template_id, sending_time=:sending_time WHERE cid = :cid');
            $stmt->execute(array(':cid' => $cid, ':template_id' => $template_id, ':sending_time' => $hour));
        } else {
            // 既存のレコードが存在しない場合はINSERT
            $stmt = $db->prepare('INSERT INTO biz_template_setting (cid, template_id, sending_time) VALUES (:cid, :template_id, :sending_time)');
            $stmt->execute(array(':cid' => $cid, ':template_id' => $template_id, ':sending_time' => $hour));
        }

        $db = null;
    } catch (PDOException $e) {
        echo '接続失敗' . $e->getMessage();
        exit();
    }

    header('Location: ./../settings.php?tab=link');
    exit();
}else{
    echo "<script>
            alert('テンプレートが選択されていません。');
            location.href = './../settings.php?tab=link';
        </script>";
}