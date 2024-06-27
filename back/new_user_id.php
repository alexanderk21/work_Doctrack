<?php
session_start();
if(isset($_POST['user_id']))
{
    include_once '../config/database.php';
    
    $cid = $_POST['cid'];
    $user_id = $_POST['user_id'];
    
    try{
        $db= new PDO($dsn,$user,$pass,[
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    
        $sql = "INSERT INTO users (cid,user_id) VALUES (:cid,:user_id)"; 
        $stmt = $db->prepare($sql); 
        $params = array(':cid' => $cid,':user_id' => $user_id); 
        $stmt->execute($params);
    
        $db = null;
        header('Location: '.$_SERVER['HTTP_REFERER']);
        exit();
    }catch(PDOException $e){
        echo '接続失敗' . $e->getMessage();
        exit();
    };
}


?>
