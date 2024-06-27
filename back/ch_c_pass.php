<?php
if (!isset($_POST['password']) && !isset($_POST['cid'])) {
    header('Location: ../');
    exit();
} else {
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $id = $_POST['id'];
    $cid = $_POST['cid'];
}

date_default_timezone_set('Asia/Tokyo');
include_once '../config/database.php';
try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare('UPDATE clients SET password = :password WHERE id = :id');
    $stmt->bindValue(':id', $id);
    $stmt->bindValue(':password', $password);
    $stmt->execute();
    echo '
            <script>
                alert("パスワードが変更されました。");
                location.href = "' . $_SERVER['HTTP_REFERER'] . '";
            </script>
        ';

    $db = null;
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}