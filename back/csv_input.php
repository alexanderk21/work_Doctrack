<?php
    include_once '../config/database.php';

    if (isset($_FILES['select'])){
        $csv = file($_FILES['select']["tmp_name"], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $csv_n=[];
        foreach ($csv as $key => $value) {
            $csv_n[$key] = mb_convert_encoding($csv[$key],"utf-8","sjis");
        }

        $stash = "";

        foreach ($csv_n as $key => $value) {
            $stash = $value;

            if ($key !== 0) {
                $stash = explode(",", $stash);                
                $cid   = $_POST['cid'];
                $tracking_id = $stash[0];
                $title = $stash[1];
                $url   = $stash[2];
                $memo  = $stash[3];
                
                try{
                    $db= new PDO($dsn,$user,$pass,[
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    ]);
                    $sql = "INSERT INTO redirects (cid,id,title,url,memo) VALUES (:cid,:id,:title,:url,:memo)"; 
                    $stmt = $db->prepare($sql); 
                    $params = array(':cid'=> $cid,':id'=> $tracking_id,':title'=> $title,':url' => $url,':memo' => $memo); 
                    $stmt->execute($params);
                    $db = null;
                    
                }catch(PDOException $e){
                    
                    if (strstr( $e->getMessage(), 'Duplicate' )){
                        echo "<script>
                                alert('すでに登録されています。');
                                location.href = './../redirect.php';
                            </script>";
                    }else{
                        echo "<script>
                                alert('登録でエラーが発生しました。');
                                location.href = './../redirect.php';
                            </script>";
                    }
                    exit();
                };
            }
        }
        header('Location: '.$_SERVER['HTTP_REFERER']);
        exit();
    }
?>