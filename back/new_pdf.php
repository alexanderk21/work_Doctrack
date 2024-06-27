<?php
include_once '../config/database.php';
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
$cid = $_POST['cid'];
$tracking_id = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, 5);
$title = $_POST['title'];
date_default_timezone_set('Asia/Tokyo');
$updated_at = date('Y-m-d H:i:s');
$memo = $_POST['memo'];

$document_url = $_SERVER['DOCUMENT_ROOT'];

if ($_FILES['pdf_file']['size'] > 20000000) {
    echo "<script>
            alert('20,000KB/1ファイルが上限となります。');
            location.href = './../pdf.php';
        </script>";
    exit();
}

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = "INSERT INTO pdf_versions (pdf_id,cid,title) VALUES (:pdf_id,:cid,:title)";
    $stmt = $db->prepare($sql);
    $params = array(':pdf_id' => $tracking_id, ':cid' => $cid, ':title' => $title);
    $stmt->execute($params);
    $pdf_version_id = $db->lastInsertId();

    // 画像の登録
    $fileinfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileinfo, $_FILES["pdf_file"]["tmp_name"]);
    finfo_close($fileinfo);
    if ($mimeType != "application/pdf") {
        echo "ファイルのMINEタイプが不正です。\nアップロード可能なファイルはPDFのみです。\n";
        exit;
    } else if (is_uploaded_file($_FILES["pdf_file"]["tmp_name"])) {

        $file_name = './../pdf/' . $tracking_id . '_' . $pdf_version_id . '.pdf';
        $i = 0;
        if (move_uploaded_file($_FILES["pdf_file"]["tmp_name"], $file_name)) {
            // PDFを画像ファイルに変換
            // $pdfPath = $document_url . '/pdf/' . $tracking_id . '_' . $pdf_version_id . '.pdf';
            // $imgInfo = new finfo(FILEINFO_MIME_TYPE);
            // $mimeType = $imgInfo->file($pdfPath);

            // $imagick = new Imagick();
            // $imagick->setResolution(300, 300);
            // $imagick->readImage($pdfPath);
            // $imagick->setImageCompressionQuality(100);
            // $numPages = $imagick->getNumberImages();
            // if ($numPages == 1) {
            //     $imagick->writeImages($document_url . '/pdf_img/' . $tracking_id . '_' . $pdf_version_id . '-0.jpg', false);
            // } else {
            //     $imagick->writeImages($document_url . '/pdf_img/' . $tracking_id . '_' . $pdf_version_id . '-%d.jpg', false);
            // }
            $old_file_name = $_FILES["pdf_file"]["name"];
        } else {
            echo "<script>
            alert('アップロードに失敗しました。');
            location.href = './../pdf.php';
            </script>";
            exit();
        }

    }
    // exit;
    // $directory = "./../pdf_img/";
    // $images = glob($directory . "/" . $tracking_id . "_" . $pdf_version_id . "-*.jpg");
    // if (empty($images)) {
    //     echo 'PDFが存在しません。';
    //     exit();
    // }

    $stmt = $db->prepare('UPDATE pdf_versions SET file_name=:file_name,memo=:memo WHERE pdf_version_id = :pdf_version_id');
    $stmt->execute(array(':pdf_version_id' => $pdf_version_id, ':file_name' => $old_file_name, ':memo' => $memo));


    if (isset($_POST['select_tags'])) {
        $tags = implode(',', $_POST['select_tags']);
        $sql = "INSERT INTO tags (table_name,table_id,tags) VALUES (:table_name,:table_id,:tags)";
        $stmt = $db->prepare($sql);
        $params = array(':table_name' => 'pdf_versions', ':table_id' => $pdf_version_id, ':tags' => $tags);
        $stmt->execute($params);
    }


    $db = null;
    echo "<script>
            alert('PDFデータ登録しました。');
            location.href = './../pdf.php';
        </script>";
    exit();

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}