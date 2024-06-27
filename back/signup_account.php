<?php
    session_start();

    include_once '../config/database.php';
    require('./sign_send_email.php');
    
    if(!isset($_POST['email']) && !isset($_POST['password'])){
        header('Location: ../signup_1.php');
        exit();
    }else{
        $id = $_POST['subdomain'];
        $name = $_POST['name'];
        $manager_name = $_POST['manager_name'];
        $tel = $_POST['tel'];
        $ref = $_POST['ref'];
        $email = $_POST['email'];
        $password = password_hash($id, PASSWORD_DEFAULT);
        // $password = $_POST['password'];
        // $id = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'),0,6);
        $status = "Free";
    }
    date_default_timezone_set('Asia/Tokyo');

    try{

        // ここにドメインを生成するコードを挿入します。

        $db= new PDO($dsn,$user,$pass,[
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $stmt = $db->prepare('SELECT * FROM basicsettings');
        $stmt->execute();
        $basics = $stmt->fetch(PDO::FETCH_ASSOC);

        
        
        $stmt = $db->prepare('UPDATE clients SET 
                                    id = :id, 
                                    name = :name, 
                                    tel = :tel, 
                                    manager = :manager,
                                    subdomain = :subdomain, 
                                    password = :password, 
                                    status = :status, 
                                    send_limit = :send_limit, 
                                    send_limit_type = :send_limit_type, 
                                    max_froms = :max_froms, 
                                    max_froms_type = :max_froms_type, 
                                    max_temp = :max_temp, 
                                    max_temp_type = :max_temp_type, 
                                    max_pdfs = :max_pdfs, 
                                    max_pdfs_type = :max_pdfs_type, 
                                    max_redirects = :max_redirects, 
                                    max_redirects_type = :max_redirects_type, 
                                    max_pops = :max_pops, 
                                    max_pops_type = :max_pops_type, 
                                    ref = :ref  
                                    WHERE email = :email');
        $stmt->bindValue(':email', $email);        
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':tel', $tel);
        $stmt->bindValue(':manager', $manager_name);
        $stmt->bindValue(':subdomain', $id);
        $stmt->bindValue(':password', $password);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':send_limit', $basics['send_limit'] , PDO::PARAM_INT);
        $stmt->bindValue(':send_limit_type', $basics['send_limit_type'] , PDO::PARAM_INT);
        $stmt->bindValue(':max_froms', $basics['max_froms'], PDO::PARAM_INT);
        $stmt->bindValue(':max_froms_type', $basics['max_froms_type'], PDO::PARAM_INT);
        $stmt->bindValue(':max_temp', $basics['max_temp'], PDO::PARAM_INT);
        $stmt->bindValue(':max_temp_type', $basics['max_temp_type'], PDO::PARAM_INT);
        $stmt->bindValue(':max_pdfs', $basics['max_pdfs'], PDO::PARAM_INT);        
        $stmt->bindValue(':max_pdfs_type', $basics['max_pdfs_type'], PDO::PARAM_INT);        
        $stmt->bindValue(':max_redirects', $basics['max_redirects'], PDO::PARAM_INT);
        $stmt->bindValue(':max_redirects_type', $basics['max_redirects_type'], PDO::PARAM_INT); 
        $stmt->bindValue(':max_pops', $basics['max_pops'], PDO::PARAM_INT);
        $stmt->bindValue(':max_pops_type', $basics['max_pops_type'], PDO::PARAM_INT);
        $stmt->bindValue(':ref', $ref);        
        $stmt->execute();    


        $stmt = $db->prepare("INSERT INTO basic_info (cid) VALUES (?)");
        $stmt->execute([$id]);
        
        $_SESSION['client_id'] = $id;
        $db = null;

        $login_link ="http://".$id."calltree-system.com";
        $subject = 'DocTrackご利用開始のご案内';
        $message = "DocTrackのご利用開始ありがとうございます。<br>
                    顧客管理システム「CALLTREE」と連携させてご利用できます。<br>
                    ログインURL<br>";
        $message .= $login_link;
        $message .= "<br>ログインID<br>
                    DocTrackの契約情報でご確認ください。<br>
                    ログインpassword<br>".$id;
        $message .= "<br>※CALLTREEにログイン後、セキュリティを考慮してパスワード変更をお願いします。<br>
                    ▼CALLTREEとは？<br>
                    https://calltree.jp/lp/";


        send_email($subject,$message,$email);
        header('Location: https://../access_analysis.php');
        exit();            
        
    }catch(PDOException $e){
        echo '接続失敗' . $e->getMessage();
        exit();
    };

?>