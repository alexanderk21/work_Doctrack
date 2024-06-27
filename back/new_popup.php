<?php

if(isset($_FILES['popup_img']))
{
    include_once '../config/database.php';

    $cid = $_POST['cid'];
    $pdf_id = $_POST['pdf_id'];
    $title = $_POST['title'];
    $url = $_POST['url'];
    $trigger = $_POST['trigger'];
    $trigger_parameter = (int)$_POST['trigger_parameter'];
    if(isset($_POST['trigger_parameter2'])){
        $trigger_parameter2 = (int)$_POST['trigger_parameter2'];
    }else{
        $trigger_parameter2 = null;
    }
    
    $memo = $_POST['memo'];
    
    try{
        $db= new PDO($dsn,$user,$pass,[
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $file_name = $_FILES['popup_img']['name'];

        $stmt = $db->prepare("INSERT INTO popups (cid, pdf_id, title, file_name, popup_trigger, url, trigger_parameter, trigger_parameter2, memo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$cid, $pdf_id, $title, $file_name, $trigger, $url, $trigger_parameter, $trigger_parameter2, $memo]);
        
        $popup_id = $db->lastInsertId();
        

        $file = $_FILES['popup_img'];
        $new_file_name = "./../popup_img/" . $popup_id . ".png";
        
        
        if (move_uploaded_file($file['tmp_name'], $new_file_name)) {
            $command = "convert ".escapeshellarg($new_file_name)." png:".escapeshellarg($new_file_name);
            exec($command);
            
            // 元々のファイル名を更新する
            $stmt = $db->prepare("UPDATE popups SET img_name=? WHERE id=?");
            $stmt->execute([$file_name, $popup_id]);
            
            if(isset($_POST['select_tags'])){
                $tags = implode(',', $_POST['select_tags']);
                $sql = "INSERT INTO tags (table_name,table_id,tags) VALUES (:table_name,:table_id,:tags)";
                $stmt = $db->prepare($sql);
                $params = array(':table_name' => 'popups',':table_id' => $popup_id,':tags' => $tags);
                $stmt->execute($params);
            }

            $db = null;
            header('Location: ./../popup.php');
            exit();

        } else {
            $stmt = $db->prepare('DELETE FROM popups WHERE id = :id');
            $stmt->execute(array(':id' => $popup_id));

            $db = null;
            echo "<script>alert('画像のアップロードに失敗しました。'); window.location.href='./../popup.php';</script>";
            exit();
        }
    
        
    }catch(PDOException $e){
        echo '接続失敗' . $e->getMessage();
        exit();
    };
}else{
    echo "<script>alert('画像がアップロードされていません。'); window.location.href='./../popup.php';</script>";
    exit();
}