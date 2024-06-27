<?php
session_start();
include_once '../config/database.php';
if(isset($_POST['small_category']) && isset($_POST['region']))
{   
    try{
        $db= new PDO($dsn,$user,$pass,[
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $sql = "SELECT * FROM list_store WHERE category=:category AND regions=:regions";
        $stmt = $db->prepare($sql);
        $stmt->execute([':category'=>$_POST['small_category'], ':regions'=>$_POST['region']]);
        $csv_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT lc.title FROM small_categories sc JOIN large_categories lc ON sc.l_id = lc.id WHERE sc.title = :title";
        $stmt = $db->prepare($sql);
        $stmt->execute([':title'=>$_POST['small_category']]);
        $large_category = $stmt->fetch();
        
    }catch(PDOException $e){
        echo '接続失敗' . $e->getMessage();
    }
    
    date_default_timezone_set('Asia/Tokyo');
    $token = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'),0,5);
    $filename = trim($large_category['title']) . "_" . trim($_POST['small_category']) . "_" . trim($_POST['region']) . "_" . date('Y-m-d');
    

    
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=".$filename.".csv");
    header("Content-Transfer-Encoding: binary");

    $csv = null;
    $csv = '"No","会社名","郵便番号","都道府県","市区町村","住所","電話番号","FAX番号","企業URL","カテゴリ"' . "\n";
    $cnt = 0;
    foreach( $csv_data as $key=>$value) {        
        $csv .= '"' . $key+1 . '","' . $value['company'] . '","' . $value['post_code'] . '","' . $value['regions'] . '","' . $value['municipalities']. '","' . $value['address'] . '","' . $value['tel'] . '","' . $value['fax'] . '","' . $value['url'] . '","' . $value['category'] . '"'. "\n";    
    }
    echo mb_convert_encoding($csv,"SJIS", "UTF-8");
    
    return;
}   
header('Location: '.$_SERVER['HTTP_REFERER']);

?> 
