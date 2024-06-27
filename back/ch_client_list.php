<?php
include_once '../config/database.php';

$client_id = $_POST['client_id'];
$user_id = $_POST['userid'];

$company = !empty($_POST['link_company']) ? $_POST['link_company'] : null;
$surename = !empty($_POST['link_surename']) ? $_POST['link_surename'] : null;
$firstname = !empty($_POST['link_name']) ? $_POST['link_name'] : null;
$email = !empty($_POST['link_email']) ? $_POST['link_email'] : null;
$url = !empty($_POST['url']) ? $_POST['url'] : null;
$tel = !empty($_POST['tel']) ? $_POST['tel'] : null;
$depart_name = !empty($_POST['depart_name']) ? $_POST['depart_name'] : null;
$director = !empty($_POST['director']) ? $_POST['director'] : null;
$address = !empty($_POST['address']) ? $_POST['address'] : null;
$free_memo = !empty($_POST['free_memo']) ? $_POST['free_memo'] : null;

date_default_timezone_set('Asia/Tokyo');

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare('UPDATE `users` SET `email`=:email, `url`=:url,`company`=:company,`surename`=:surename,`name`=:firstname, `tel`=:tel, `depart_name`=:depart_name, `director`=:director, `address`=:address, `free_memo`=:free_memo WHERE `cid` = :client_id AND `user_id` = :user_id');
    $stmt->bindValue(':client_id', $client_id, PDO::PARAM_STR);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':url', $url, PDO::PARAM_STR);
    $stmt->bindValue(':company', $company, PDO::PARAM_STR);
    $stmt->bindValue(':surename', $surename, PDO::PARAM_STR);
    $stmt->bindValue(':firstname', $firstname, PDO::PARAM_STR);
    $stmt->bindValue(':tel', $tel, PDO::PARAM_STR);
    $stmt->bindValue(':depart_name', $depart_name, PDO::PARAM_STR);
    $stmt->bindValue(':director', $director, PDO::PARAM_STR);
    $stmt->bindValue(':address', $address, PDO::PARAM_STR);
    $stmt->bindValue(':free_memo', $free_memo, PDO::PARAM_STR);
    $test = $stmt->execute();

    header('Location: ' . $_SERVER['HTTP_REFERER']);

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}

