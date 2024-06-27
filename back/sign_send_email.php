<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require('./../PHPMailer/src/PHPMailer.php');
require('./../PHPMailer/src/Exception.php');
require('./../PHPMailer/src/SMTP.php');

function send_email($subject,$content,$to){
  
  mb_language('uni');
  mb_internal_encoding('UTF-8');
  
  $mail = new PHPMailer(true);
  $mail->SMTPDebug = 3; 
  $mail->CharSet = 'utf-8';

  try {
  
    $mail->isSMTP();
    $mail->Host       = "sv8410.xserver.jp";
    $mail->SMTPAuth   = true;
    $mail->Username   = "customer@doctrack.jp";
    $mail->Password   = "tQ5MwjVK";
    $mail->SMTPSecure = 'tls';
    $mail->Port       = "";
  
    $mail->setFrom("customer@doctrack.jp","メールを送信"); 
    $mail->addAddress($to);
    
    $mail->isHTML(true);
    $mail->Subject = $subject; 
    $mail->Body    = $content;  
  
    $mail->send();
    //echo '配信に成功しました。'; 
    return(1);
  } catch (Exception $e) {
    // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    return(2);
  }
}
