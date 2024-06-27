<?php

require 'vendor/autoload.php';
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Guid\Guid;

    
    session_start();
    if(session_status() === PHP_SESSION_ACTIVE){
        session_unset(); 
        session_destroy(); 
    }
    
    if(isset($_POST['email']) && isset($_POST['pass'])){
        $email = $_POST['email'];
        $pwd   = $_POST['pass'];
        $signup = true;
        $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $url_dir = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $url_dir = str_replace(basename($url_dir), '', $url_dir);
    }
    
    

    date_default_timezone_set('Asia/Tokyo');
    include_once '../config/database.php';
    require('./sign_send_email.php');
    try{
        $db= new PDO($dsn,$user,$pass,[
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $stmt = $db->prepare('SELECT * FROM clients WHERE email = :email');
       
        $stmt->execute(array(':email' => $email));
        $double = $stmt->fetch(PDO::FETCH_ASSOC);
        if($double) {
            header('Location: ../double_state.php');
            exit();
        } else {
            // $token = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, 16);
            $uuid = Guid::uuid4()->toString();
            $token = str_replace('-','',$uuid);
            $password = password_hash($pwd, PASSWORD_DEFAULT);
            $status = "未登録";
            $expiry   = 24*60*60;
            // $stmt = $db->prepare("SELECT * FROM clients WHERE status = '未登録' ORDER BY created_at ASC LIMIT 1");
            // $stmt->execute(array());
            // $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("INSERT INTO clients (email, status, password, token, expiry_time) VALUE (:email, :status, :password,:token, :expiry_time)");
            $params = array(':email'=>$email,':status'=>$status, ':password'=>$password, ':token'=>$token, 'expiry_time'=>$expiry);
            $stmt->execute($params);
            
            $token_link = $url_dir . "signup_confirm_email.php?token=$token";
            $subject = '【DocTrack】新規登録メール認証';
            $message = "下記のリンクをクリックするとメールアドレスの確認が完了します。<br>";
            $message .= $token_link."<br>このメールアドレスは送信専用ですので、受信することができません。";


            send_email($subject,$message,$email);
            $db = null;
            header('Location: ../status_view.php');
            
            exit();
            // if($result) {
            //     $status = "メール認証待ち";
            //     if(isset($_POST['ref'])){
            //         $ref   = $_POST['ref'];
                    
            //         $stmt = $db->prepare('UPDATE clients SET ref = :ref, email = :email, password = :password, status = :status, token = :token,  expiry_time = :expiry_time  WHERE id = :id');
            //         $stmt->bindValue(':ref', $ref);
            //     }
            //      else {
            //         $stmt = $db->prepare('UPDATE clients SET email = :email, password = :password, status = :status, token = :token,  expiry_time = :expiry_time  WHERE id = :id');
            //     }
            //     $stmt->bindValue(':id', $result['id']);
            //     $stmt->bindValue(':password', $password);
            //     $stmt->bindValue(':status', $status);
            //     $stmt->bindValue(':token', $token);
            //     $stmt->bindValue(':email', $email);
            //     $stmt->bindValue(':expiry_time', date('Y-m-d H:i:s',  time() + $expiry));
            //     $stmt->execute();
           
                // $token_link = $url_dir . "signup_confirm_email.php?token=$token";
                // $subject = '【DocTrack】新規登録メール認証';
                // $message = "下記のリンクをクリックするとメールアドレスの確認が完了します。<br>";
                // $message .= $token_link."<br>このメールアドレスは送信専用ですので、受信することができません。";


                // send_email($subject,$message,$email);
                // header('Location: ../status_view.php');
                // exit();
            // }else{
            //     echo "<script>
            //             alert('CALLTREEアカウントが準備中のため新規登録に失敗しました。');
            //             location.href = './../signup_1.php';
            //         </script>";
            // }
        }        
      
    }catch(PDOException $e){
        echo '接続失敗' . $e->getMessage();
        exit();
    };

?>
