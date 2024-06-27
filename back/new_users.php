<?php
session_start();

include_once '../config/database.php';
$cid = $_POST['cid'];
$cuser_id = $_POST['cuser_id'];
$user_id = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, 4);
$company = $_POST['company'] ?? " ";
$surname = $_POST['surname'] ?? " ";
$firstname = $_POST['firstname'] ?? " ";
$email = $_POST['email'] ?? " ";
$url = $_POST['company_url'] ?? " ";
$tel = $_POST['new_tel'] ?? " ";
$depart_name = $_POST['new_depart_name'] ?? " ";
$director = $_POST['new_director'] ?? " ";
$address = $_POST['new_address'] ?? " ";
$free_memo = $_POST['new_free_memo'] ?? " ";

$parsed_url = $url ? parse_url($url) : [];

if (!empty($parsed_url) && isset($parsed_url['scheme']) && isset($parsed_url['host'])) {
    $url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
} else {
    $url = '';
}

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = "SELECT * FROM users WHERE email = :email AND cid=:cid";
    $stmt = $db->prepare($sql);
    $stmt->execute([':email' => $email, ':cid' => $cid]);
    $results = $stmt->fetchAll();
    $email_confirm = 0;
    $url_confirm = 0;
    $info_confirm = 0;
    foreach ($results as $result) {
        if ($result['email'] == $email && !empty($email)) {
            $email_confirm++;
        }
        if ($result['url'] == $url && !empty($url)) {
            $url_confirm++;
        }
    }
    if ($email_confirm > 0) {
        echo "<script>
                alert('既に登録のあるメールアドレスです。');
                location.href = './../clients_list.php';
            </script>";
        exit();
    }
    if ($url_confirm > 0) {
        echo "<script>
                alert('既に登録のあるURLです。');
                location.href = './../clients_list.php';
            </script>";
        exit();
    }
    if ($email_confirm == 0 && $url_confirm == 0) {
        $sql = "INSERT INTO users (cid, user_id, cuser_id, email, url, company, surename, name, tel, depart_name, director, address, free_memo, route) 
        VALUES (:cid, :user_id, :cuser_id, :email, :url, :company, :surname, :firstname, :tel, :depart_name, :director, :address, :free_memo, :route)";
        $stmt = $db->prepare($sql);
        $params = array(
            ':cid' => $cid,
            ':user_id' => $user_id,
            ':cuser_id' => $cuser_id,
            ':email' => $email,
            ':url' => $url,
            ':company' => $company,
            ':surname' => $surname,
            ':firstname' => $firstname,
            ':tel' => $tel,
            ':depart_name' => $depart_name,
            ':director' => $director,
            ':address' => $address,
            ':free_memo' => $free_memo,
            ':route' => '手動',
        );
        $stmt->execute($params);
        $db = null;
    }



    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}