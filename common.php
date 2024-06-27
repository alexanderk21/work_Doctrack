<?php
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 1200); // Set the timeout to 60 seconds
ini_set('upload_max_filesize', '20M');
ini_set('post_max_size', '25M');

include_once 'config/database.php';

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$directory = dirname($_SERVER['PHP_SELF']);
$current_directory_url = $protocol . "://" . $host . $directory;

$domain = explode('.', $host);
if (count($domain) == 3) {
    $subdomain = $domain[0];
}



if (isset($_SESSION['client_id'])) {
    if ($_SESSION['client_user'] == 'ohta') {
        $client_id = $subdomain;
        $client_user = $subdomain;
        $_SESSION['client_id'] = $subdomain;
    } elseif ($_SESSION['client_id'] == $subdomain) {
        $client_id = $_SESSION['client_id'];
        $client_user = $_SESSION['client_user'];
    } else {
        header('Location: https://in.doc1.jp/login.php');
        exit();
    }
} else {
    header('Location: https://in.doc1.jp/login.php');
    exit();
}

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare("SELECT * FROM clients WHERE id=?");
    $stmt->execute([$client_id]);
    $ClientData = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT * FROM clients WHERE id=?");
    $stmt->execute([$client_user]);
    $ClientUser = $stmt->fetch(PDO::FETCH_ASSOC);

    $func_limit = $ClientUser['func_limit'] ? explode(',', $ClientUser['func_limit']) : [];

    $stmt = $db->prepare("SELECT * FROM stages WHERE client_id=? ORDER BY stage_point");
    $stmt->execute([$client_id]);
    $stages_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($stages_data)) {
        $sql = "INSERT INTO stages (client_id,stage_name,stage_point,stage_memo) VALUES (:client_id,:stage_name,:stage_point,:stage_memo)";
        $stmt = $db->prepare($sql);
        $params = array(':client_id' => $client_id, ':stage_name' => '0ポイント', ':stage_point' => '0', ':stage_memo' => '');
        $stmt->execute($params);

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
function secondsToTime($seconds)
{
    $hours = floor($seconds / 3600);
    $minutes = floor(floor($seconds / 60) % 60);
    $seconds = $seconds % 60;

    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}