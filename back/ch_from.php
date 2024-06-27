<?php
session_start();
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

$from_id = $_POST['from_id'];
$from_name = $_POST['from_name'];
$email = $_POST['email'];
$signature = $_POST['signature'];
$smtp_host = $_POST['smtp_host'];
$smtp_pw = $_POST['smtp_pw'];

$subject = "送信テスト";
$content = "TEST";
$to = "send-test@doctrack.jp";
$html_content = $content;

try {
    if (send_email($email, $from_name, $subject, $html_content, $content, $to, $smtp_host, $smtp_pw) !== 1) {
        throw new Exception('SMTP設定が正しくありません。');
    } else {
        try {
            $db = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $stmt = $db->prepare('UPDATE froms SET email=:email,from_name=:from_name,signature=:signature,smtp_host=:smtp_host,smtp_pw=:smtp_pw WHERE id = :id');
            $stmt->execute(array(':id' => $from_id, ':email' => $email, ':from_name' => $from_name, ':signature' => $signature, ':smtp_host' => $smtp_host, ':smtp_pw' => $smtp_pw));

            $db = null;
        } catch (PDOException $e) {
            echo '接続失敗' . $e->getMessage();
            exit();
        }
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
} catch (Exception $e) {
    echo "<script>
            alert('設定情報が間違っています。メールサーバー情報を再度、ご確認ください。');
            location.href = './../address_setting.php';
        </script>";
    exit;
}