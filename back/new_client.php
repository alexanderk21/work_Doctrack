<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require '../vendor/autoload.php';

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Guid\Guid;

include_once './../config/database.php';
include '../../test.doctrack.jp/back/f_send_email.php';
date_default_timezone_set('Asia/Tokyo');
function generatePass($length = 8)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $Pass = '';
    for ($i = 0; $i < $length; $i++) {
        $Pass .= $characters[rand(0, $charactersLength - 1)];
    }
    return $Pass;
}

if (isset($_POST['email'])) {
    $email = $_POST['email'];
    $company = $_POST['company'];
    $last_name = $_POST['last_name'];
    $first_name = $_POST['first_name'];
    $ref = $_POST['ref'];

    try {
        $db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $sql = "SELECT * FROM froms WHERE email = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$email]);
        $emails = $stmt->fetchAll();

        if (count($emails) == 0) {
            $uuid = Guid::uuid4()->toString();
            $token = str_replace('-', '', $uuid);

            $pwd = generatePass();
            $password = password_hash($pwd, PASSWORD_DEFAULT);
            $status = "メール認証待ち";
            $expiry = 24 * 60 * 60;
            $expiry_time = date('Y-m-d H:i:s', time() + $expiry);
            $func_limit = '削除,変更,CSV出力,ユーザー管理,各種設定,アフィリエイト,契約情報';

            $stmt = $db->prepare("INSERT INTO clients (email, status, password,name,last_name,first_name, ref, token, expiry_time) VALUE (:email, :status, :password,:name,:last_name,:first_name, :ref,:token, :expiry_time, func_limit)");
            $params = array(':email' => $email, ':status' => $status, ':password' => $password, ':name' => $company, ':last_name' => $last_name, ':first_name' => $first_name, ':ref' => $ref, ':token' => $token, ':expiry_time' => $expiry_time, ':func_limit' => $func_limit);

            $stmt->execute($params);

            $token_link = "https://in.doc1.jp/back/signup_confirm_email.php?token=" . $token;
            $subject = '【DocTrack】新規登録メール認証';
            $message = "<p>下記のリンクをクリックするとメールアドレスの確認が完了します。</p>";
            $message .= "<a href='" . $token_link . "'>" . $token_link . "</a>";
            $message .= "<p>ログインアドレス：" . $email . "</p>";
            $message .= "<p>ログインパスワード：" . $pwd . "</p>";
            $message .= "<p>初期パスワードはランダム生成されてます。<br>ログイン後、ユーザー管理にて変更下さい。</p>";
            $message .= "<p>このメールアドレスは送信専用ですので、受信することができません。</p>";

            $smtp_host = "sv8410.xserver.jp";
            $from = "customer@doctrack.jp";
            $smtp_pw = "tQ5MwjVK";
            $from_name = "【DocTrack】新規登録メール認証";

            send_email($from, $from_name, $subject, $message, $message, $email, $smtp_host, $smtp_pw);
        } else {
            echo "<script>
                    alert('現在のメールアドレスが存在します。');
                    location.href = './../affilate.php';
                </script>";
            exit();
        }
    } catch (PDOException $e) {
        echo '接続失敗' . $e->getMessage();
        exit();
    }
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit();