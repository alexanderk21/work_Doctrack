<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once ('/data/service/test-2.doc1.jp/PHPMailer/src/PHPMailer.php');
require_once ('/data/service/test-2.doc1.jp/PHPMailer/src/Exception.php');
require_once ('/data/service/test-2.doc1.jp/PHPMailer/src/SMTP.php');

// Check if the function exists before declaring it
if (!function_exists('isValidEmail')) {
    function isValidEmail($email)
    {
        // Basic email format validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            print_r('filter');
            return false;
        }

        list($localPart, $domain) = explode('@', $email);

        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            print_r('domain');
            return false;
        }

        return true;
    }

}

function send_email($from, $from_name, $subject, $html_content, $text_content, $to, $smtp_host, $smtp_pw)
{
    // Check if the recipient email is valid
    if (!isValidEmail($to)) {
        return 2; // Invalid email address
    }

    $apiKey = "abccbd57256a40028ac7e3a8653b7930";
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://emailvalidation.abstractapi.com/v1/?api_key=' . $apiKey . '&email=' . $to);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $data = curl_exec($ch);

    curl_close($ch);
    $res = json_decode($data, true);

    if (($res['autocorrect'] == '') && $res['is_smtp_valid']['value']) {
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
        // $mail->SMTPDebug = 3;
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
                return (2);
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
                    return (2);
                }
            } catch (Exception $e) {
                return (3);
            }
        }
    } else {
        return (0);
    }
}
