<?php
session_start();
include_once '../config/database.php';
include_once './f_send_email.php';

date_default_timezone_set('Asia/Tokyo');
try{

    $db= new PDO($dsn,$user,$pass,[
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    if(isset($_POST['large_cts'])){
        $sql = "SELECT * FROM large_categories WHERE id=:id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id'=>$_POST['large_cts']]);
        $result = $stmt->fetch();
        $large_ct = $result['title'];

        $sql = "SELECT * FROM list_store WHERE category=:small_cts AND regions=:regions";
        $stmt= $db->prepare($sql);
        $stmt->execute([':small_cts'=>trim($_POST['small_cts']), ':regions' => $_POST['d_regions']]);
        $searchData = $stmt->fetchAll();

        
        $_SESSION['searchData'] = $searchData;

        $totalNum = count($searchData);
        $acquisition_time = date('Y-m-d H:i:s');

        $sql = "INSERT INTO store_search (cid, large_category, small_category, region, num, acquisition_time) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt=$db->prepare($sql);
        $stmt->execute([$_POST['cid'], trim($large_ct), trim($_POST['small_cts']), trim($_POST['d_regions']), $totalNum, $acquisition_time]);
        $db = null;
        header('Location: '.$_SERVER['HTTP_REFERER']);
        if($totalNum==0){
            
            $from = "customer@doctrack.jp";
            $from_name = $_POST['cid'];
            $smtp_host = "sv8410.xserver.jp";
            $smtp_port = "0";
            $smtp_pw   = "tQ5MwjVK";

            $to = "customer@doctrack.jp";
            $subject = "検索通知";
            $content = "大カテゴリー：".$large_ct."、小カテゴリー：".$_POST['small_cts']."、地域：".$_POST['d_regions']."に該当する資料が存在しません。";
            
            if(send_email($from,$from_name,$subject,$content,$to,$smtp_host,$smtp_port,$smtp_pw)==1){
                echo "メールの送信に成功しました。";    
                header('Location: '.$_SERVER['HTTP_REFERER']);
              }else{
                echo "メール送信に失敗しました。";                
                header('Location: '.$_SERVER['HTTP_REFERER']);
              }

        }

        
    }
}catch(PDOException $e){
    echo '接続失敗' . $e->getMessage();
};
?>