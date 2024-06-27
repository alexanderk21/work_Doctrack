<?php
session_start();
date_default_timezone_set('Asia/Tokyo');
ini_set('display_errors', 1);
error_reporting(E_ALL);
include_once '../common.php';
require ('./f_send_email.php');

ob_start();
unset($_SESSION['read_contact']);

if (isset($_SESSION['csv_data'])) {
    $csv_data = serialize($_SESSION['csv_data']);
} elseif (isset($_SESSION['email_data'])) {
    $csv_data = serialize($_SESSION['email_data']);
}

function br2nl($string)
{
    preg_replace('/<br[[:space:]]*\ ?[[:space:]]*="">/i', "\n", $string);
}


try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $stmt = $db->prepare("SELECT * FROM froms WHERE id=?");
    $stmt->execute([$_POST['from_id']]);
    $from_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT * FROM templates WHERE id=?");
    $stmt->execute([$_POST['template_id']]);
    $template_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $cid=$_POST['cid'];
    $from_id=$_POST['from_id'];
    $template_id=$_POST['template_id'];
    $content = str_replace(array("<br />", "<br>"), "", $_POST['content']);
    $subject=$template_data['subject'];
    $from_email=$from_data['email'];
    $from_name=$from_data['from_name'];
    $scheduled_datetime = date('Y-m-d H:i');

    $sql = "INSERT INTO schedules (cid,process,template_id,subject,content,from_email,from_name,scheduled_datetime,to_data) VALUES (:cid,:process,:template_id,:subject,:content,:from_email,:from_name,:scheduled_datetime,:to_data)"; 
    $stmt = $db->prepare($sql); 
    $params = array(':cid' => $cid,':process' => 1,':template_id' =>$template_id,':subject' => $subject,':content' => $content,':from_email' => $from_email,':from_name' => $from_name,':scheduled_datetime' => $scheduled_datetime,':to_data' => $csv_data); 
    $stmt->execute($params);

    $schedule_id = $db->lastInsertId();

    if(isset($_SESSION['file_name'])){
        $stmt = $db->prepare("INSERT INTO csv_history (cid,file_name,token, subject) VALUES (?,?,?,?)");
        $stmt->execute([$cid, $_SESSION['file_name'], $schedule_id, $subject]);
        unset($_SESSION['file_name']);
    }

    $db = null;
    
    echo "<script>
            location.href = './../distribution_list.php';
        </script>";
    exit;
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}