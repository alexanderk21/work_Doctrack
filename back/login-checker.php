<?php   
    session_start();
    $email = $_POST['email'];
    $password = $_POST['pass'];
    $signup = true;
    date_default_timezone_set('Asia/Tokyo');
    include_once '../config/database.php';
    try{
        $db= new PDO($dsn,$user,$pass,[
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);        
        $stmt = $db->prepare('SELECT * FROM clients WHERE email = :email');
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if($result) {            
            $status = $result['status'];
            if($status == "未登録"){
                echo '<script type="text/javascript">
                            alert("アカウントが未登録です。");
                            location.href = "../signup_2.php";
                            </script>';
            }else if($status == "メール認証待ち"){
                    echo '<script type="text/javascript">
                            alert("メール認証待ちのアカウントです。\nメールを送信しておりますのでご確認ください。");
                            location.href = "../signup_2.php";
                            </script>';           
            }else{
                if(password_verify($password, $result['password'])){
                    $_SESSION['client_id'] = $result['id'];
                    header('Location: ../clients_list.php');
                    exit();
                }else{
                    header('Location: ../login.php?error=pwderror');
                    exit();
                }
            }            
        }else{
            header('Location: ../login.php?error=emailerror');
            exit();
        }   
        $db = null;
    }catch(PDOException $e){
        echo '接続失敗' . $e->getMessage();
        exit();
    };

?>
