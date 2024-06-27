<?php
include_once '../config/database.php';
$cid = $_POST['cid'] ?? '';
$gender = $_POST['gender'] ?? '';
$ageGroup = $_POST['age_group'] ?? '';
$region = $_POST['regions'] ?? '';
$staffNum = $_POST['staff_num'] ?? 0;
$industry = $_POST['industry'] ?? '';
$departName = $_POST['depart_name'] ?? '';
$role = $_POST['role'] ?? '';
$problems = $_POST['problem'] ?? '';
if ($problems != '') {
    $problem = implode(' ', $problems);
}

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare('UPDATE clients SET gender=:gender, age_group=:ageGroup, staff_num=:staffNum, region=:region, industry=:industry, depart_name=:departName, director=:role, problem=:problem WHERE id = :cid');
    $stmt->execute(
        array(
            ':gender' => $gender,
            ':ageGroup' => $ageGroup,
            ':staffNum' => $staffNum,
            ':region' => $region,
            ':industry' => $industry,
            ':departName' => $departName,
            ':role' => $role,
            ':problem' => $problem,
            ':cid' => $cid
        )
    );

    $db = null;
    header('Location: ' . $_SERVER['HTTP_REFERER']);

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}