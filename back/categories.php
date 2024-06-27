<?php
session_start();
$large_category=$_POST['large_category'];

require('../common.php');
header('Content-Type: application/json');
// echo json_encode($response);
try{
    $db= new PDO($dsn,$user,$pass,[
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);        
    
    $sql = "SELECT * FROM small_categories WHERE l_id=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$large_category]);
    $small_categories = $stmt->fetchAll();
    
    $categories = json_encode($small_categories);
    echo $categories;

}catch(PDOException $e){
    $response = [
        'status' => 'エラー',
        'message' => 'データベースエラー: ' . $e->getMessage(),
    ];
    echo json_encode($response);
    exit();
};
?>