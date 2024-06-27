<?php
include_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset ($_FILES['csvFile']) && is_uploaded_file($_FILES['csvFile']['tmp_name'])) {
        $csv_data = [];
        if (($handle = fopen($_FILES['csvFile']['tmp_name'], 'r')) !== false) {

            $header = fgetcsv($handle, 0, ',', '"', '"');
            if (empty ($header)) {
                echo '<script type="text/javascript">
                alert("CSVに内容がありません。");';
                header('Location: ./../stop.php');
                exit;
            }

            $header = array_map('mb_convert_encoding', $header, array_fill(0, count($header), 'UTF-8'), array_fill(0, count($header), 'Shift-JIS'));

            while (($row = fgetcsv($handle, 0, ',', '"', '"')) !== false) {
                $row = array_map('mb_convert_encoding', $row, array_fill(0, count($row), 'UTF-8'), array_fill(0, count($row), 'Shift-JIS'));
                if (strpos($row[0], '@') === false && strpos($row[0], '＠') === false) {
                    $_SESSION['csv_data'] = null;
                    echo '<script type="text/javascript">
                    alert("CSVファイルの内容をご確認ください。");
                    location.href = "./../stop.php";
                    </script>';
                    exit;
                }
                if(strpos($row[0], '@') === false){
                    str_replace('＠', '@', $row[0]);
                }
                $csv_data[] = $row[0];
            }
            fclose($handle);

            try {
                $db = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $cid = $_POST['cid'];
                foreach($csv_data as $r){
                    $stmt = $db->prepare("SELECT * FROM stops WHERE cid=? AND mail=?");
                    $stmt->execute([$cid, $r]);
                    $stop_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                    if (empty ($stop_data)) {
                        $sql = "INSERT INTO stops (cid,mail,division) VALUES (:cid,:mail,:division)";
                        $stmt = $db->prepare($sql);
                        $params = array(':cid' => $cid, ':mail' => $r, ':division' => 'CSV登録');
                        $stmt->execute($params);
                    } 
                }
            
                $db = null;
                header('Location: ' . $_SERVER['HTTP_REFERER']);
                exit();
            } catch (PDOException $e) {
                echo '接続失敗' . $e->getMessage();
                exit();
            }
        }
    }
} else {
    echo '不正なアクセスです。';
    exit;
}
echo '<script type="text/javascript">
      alert("CSVファイルのアップロードに失敗しました。");
      location.href = "./../stop.php";
      </script>';
exit;