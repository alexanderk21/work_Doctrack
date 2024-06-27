<?php
if(isset($_GET['ref']) && isset($_GET['cid']) && isset($_GET['url'])){
    
    include_once '../config/database.php';

    try{
        $db= new PDO($dsn,$user,$pass,[
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $stmt = $db->prepare("INSERT INTO ad_click_cnt (cid, clicked_cid) VALUES (?, ?)");
        $stmt->execute([$_GET['ref'] , $_GET['cid']]);

        $db = null;
    }catch(PDOException $e){
        echo '接続失敗' . $e->getMessage();
        exit();
    };

    header('Location: '.$_GET['url']);
    exit();
}