<?php
if (!isset($_POST['new_pwd']) && !isset($_POST['userid'])) {
    header('Location: ../');
    exit();
} else {

    $pwd = $_POST['new_pwd'];
    $userid = $_POST['userid'];

}

date_default_timezone_set('Asia/Tokyo');
include_once '../config/database.php';
try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $password = password_hash($pwd, PASSWORD_DEFAULT);
    $stmt = $db->prepare('UPDATE clients SET password = :password WHERE id = :userid');
    $stmt->bindValue(':userid', $userid);
    $stmt->bindValue(':password', $password);
    $stmt->execute();
    echo '
            <script>
                alert("パスワードが変更されました。");
                location.href = "../account_info.php";
            </script>
        ';
    $db = null;
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}