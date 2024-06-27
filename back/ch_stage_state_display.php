<?php
    if(isset($_POST['state'])&&isset($_POST['id'])){
        include_once '../config/database.php';
    
        $id = $_POST['id'];
        $state = $_POST['state'];
        $switch = ($state == 0) ? 1 : 0;
        
        try{
            $db= new PDO($dsn,$user,$pass,[
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $stmt = $db->prepare('UPDATE stages SET stage_state=:stage_state WHERE id = :id');
            $stmt->execute(array(':id' => $id,':stage_state' => $switch));
        
            $db = null;
        }catch(PDOException $e){
            echo '接続失敗' . $e->getMessage();
            exit();
        };
        
        header('Location: '.$_SERVER['HTTP_REFERER']);
        exit();
    }
?>