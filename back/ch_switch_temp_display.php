<?php
    if(isset($_POST['switch'])&&isset($_POST['id'])){
        include_once '../config/database.php';
    
        $id = $_POST['id'];
        $switch = $_POST['switch'];
    
        
        try{
            $db= new PDO($dsn,$user,$pass,[
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $stmt = $db->prepare('UPDATE clients SET temp_display=:temp_display WHERE id = :id');
            $stmt->execute(array(':id' => $id,':temp_display' => $switch));
        
            $db = null;
        }catch(PDOException $e){
            echo '接続失敗' . $e->getMessage();
            exit();
        };
        
        header('Location: '.$_SERVER['HTTP_REFERER']);
        exit();
    }
    
    
?>
