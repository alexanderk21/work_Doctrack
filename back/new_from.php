<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

ini_set('display_errors', 1);
error_reporting(E_ALL);
require ('./../PHPMailer/src/PHPMailer.php');
require ('./../PHPMailer/src/Exception.php');
require ('./../PHPMailer/src/SMTP.php');

include_once '../config/database.php';

function send_email($from, $from_name, $subject, $html_content, $text_content, $to, $smtp_host, $smtp_pw)
{
    if ($smtp_host == "smtp.lolipop.jp") {
        $secure = "ssl";
        $smtp_port = 465;
    } else {
        $secure = "tls";
        $smtp_port = 587;
    }

    mb_language('uni');
    mb_internal_encoding('UTF-8');

    $mail = new PHPMailer(true);
    $mail->SMTPDebug = 3;
    $mail->CharSet = 'utf-8';

    try {
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $from;
        $mail->Password = $smtp_pw;
        $mail->SMTPSecure = $secure;
        $mail->Port = $smtp_port;

        $mail->setFrom($from, $from_name);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_content;
        $mail->AltBody = $text_content;

        if (!$mail->send()) {
            return (3);
        } else {
            return (1);
        }

    } catch (Exception $e) {
        if ($secure == "ssl") {
            $mail->SMTPSecure = "tls";
            $smtp_port = 587;
        } else {
            $mail->SMTPSecure = "ssl";
            $smtp_port = 465;
        }

        try {
            if ($mail->send()) {
                return (1);
            } else {
                return (4);
            }

        } catch (Exception $e) {
            return (5);
        }
    }
}


$cid = $_POST['cid'];
$email = $_POST['email'];
$from_name = $_POST['from_name'];
$signature = $_POST['signature'];
$smtp_host = $_POST['smtp_host'];
$smtp_pw = $_POST['smtp_pw'];
$subject = "送信テスト";
$content = "TEST";
$to = "send-test@doctrack.jp";
$html_content = $content;

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare("SELECT * FROM froms WHERE email=?");
    $stmt->execute([$email]);
    $data = $stmt->fetchAll();
    if (count($data) > 0) {
        echo "<script>
                alert('現在のメールが存在します。');
                location.href = './../address_setting.php';
            </script>";
        exit();
    }
    if (send_email($email, $from_name, $subject, $html_content, $content, $to, $smtp_host, $smtp_pw) !== 1) {
        throw new Exception('SMTP設定が正しくありません。');
    } else {
        try {
            $db = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $stmt = $db->prepare("SELECT * FROM froms WHERE cid=?");
            $stmt->execute([$cid]);
            $data = $stmt->fetchAll();

            $flag = 0;
            foreach ($data as $da) {
                if ($da['email'] == $email) {
                    $flag++;
                }
            }
            if ($flag == 0) {
                $sql = "INSERT INTO froms (cid,email,from_name,signature,smtp_host,smtp_pw) VALUES (:cid,:email,:from_name,:signature,:smtp_host,:smtp_pw)";
                $stmt = $db->prepare($sql);
                $params = array(':cid' => $cid, ':email' => $email, ':from_name' => $from_name, ':signature' => $signature, ':smtp_host' => $smtp_host, ':smtp_pw' => $smtp_pw);
                $stmt->execute($params);
            } else {
                echo "<script>
            alert('現在のメールが存在します。');
            location.href = './../address_setting.php';
            </script>";
                exit();
            }

            $db = null;
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit();
        } catch (PDOException $e) {
            echo '接続失敗' . $e->getMessage();
            exit();
        }
    }
} catch (Exception $e) {
    echo "<script>
            alert('設定情報が間違っています。メールサーバー情報を再度、ご確認ください。');
            location.href = './../address_setting.php';
        </script>";
}