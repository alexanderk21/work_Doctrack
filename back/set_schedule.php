<?php
session_start();
include_once '../config/database.php';

if(isset($_SESSION['csv_data'])){
    $csv_data = serialize($_SESSION['csv_data']);
    unset($_SESSION["csv_data"]);
}elseif(isset($_SESSION['email_data'])){
    $csv_data = serialize($_SESSION['email_data']);
    unset($_SESSION["email_data"]);
}
unset($_SESSION['read_contact']);
function br2nl($string)
{
    preg_replace('/<br[[:space:]]*\ ?[[:space:]]*="">/i', "\n", $string);
}

$cid=$_POST['cid'];
$from_id=$_POST['from_id'];
$template_id=$_POST['template_id'];
$content = str_replace(array("<br />", "<br>"), "", $_POST['content']);
$subject=$_POST['subject'];
$from_email=$_POST['email'];
$from_name=$_POST['from_name'];
$hour = $_POST['hour'];
$minute = $_POST['minute'];
$time = $hour.':'.$minute;
$scheduled_datetime = $_POST['date'].' '.$time;

try{
    $db= new PDO($dsn,$user,$pass,[
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $sql = "INSERT INTO schedules (cid,template_id,subject,content,from_email,from_name,scheduled_datetime,to_data) VALUES (:cid,:template_id,:subject,:content,:from_email,:from_name,:scheduled_datetime,:to_data)"; 
    $stmt = $db->prepare($sql); 
    $params = array(':cid' => $cid,':template_id' =>$_POST['template_id'],':subject' => $subject,':content' => $content,':from_email' => $from_email,':from_name' => $from_name,':scheduled_datetime' => $scheduled_datetime,':to_data' => $csv_data); 
    $stmt->execute($params);

    $schedule_id = $db->lastInsertId();

    if(isset($_SESSION['file_name'])){
        $stmt = $db->prepare("INSERT INTO csv_history (cid,file_name,token, subject) VALUES (?,?,?,?)");
        $stmt->execute([$cid, $_SESSION['file_name'], $schedule_id, $subject]);
        unset($_SESSION['file_name']);
    }

    $db = null;

    echo "<script>
        alert('予約を受け付けしました。');
        location.href = './../schedules.php';
    </script>";
    
  }catch(PDOException $e){
      echo '接続失敗_' . $e->getMessage();
  };

exit();
