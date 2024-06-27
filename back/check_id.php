<?php
include_once './../config/database.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (isset($_POST['id'])) {
    $cid = $_POST['id'];

    try {
        $db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $sql = "SELECT * FROM clients WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$cid]);
        $result = $stmt->fetchAll();

        if (count($result) == 0) {
            echo 'yes';
        } else {
            echo 'no';
        }

    } catch (PDOException $e) {
        echo '接続失敗' . $e->getMessage();
        exit();
    }
}