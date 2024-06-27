<?php   
    session_start();
    unset($_SESSION['email']);
    unset($_SESSION['password']); 
    unset($_SESSION['ref']); 
    unset($_SESSION['client_id']); 

    $token = $_GET['token'];
    date_default_timezone_set('Asia/Tokyo');
    include_once '../config/database.php';

    try{
        $db= new PDO($dsn,$user,$pass,[
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $stmt = $db->prepare("SELECT * FROM clients WHERE token=?");
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($result) {            
            $expiry_time = $result['expiry_time'];
            $userid      = $result['id'];
            if($expiry_time < date('Y-m-d H:i:s')){
                $stmt = $db->prepare('DELETE FROM clients WHERE token = :token AND status = "未登録"');
                $stmt->bindValue(':token', $token);
                $stmt->execute();
                header('Location: ../expiry_over.php');
                exit();
            }else{
                $stmt = $db->prepare('UPDATE clients SET updated_at = CURRENT_TIMESTAMP  WHERE token = :token');
                $stmt->bindValue(':token', $token);
                $stmt->execute();

                $_SESSION['email']    = $result['email'];
                $_SESSION['password'] = $result['password'];
                $_SESSION['ref'] = $result['ref'];

                header('Location: ../signup_2.php');
                exit();
            }
        }        
        $db = null;
    }catch(PDOException $e){
        echo '接続失敗' . $e->getMessage();
        exit();
    };

?>
