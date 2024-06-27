<?php
session_start();
include_once '../common.php';

$tracking_id = $_POST['id'];
$pdf_version_id = $_POST['pdf_version_id'];
$title = $_POST['detail_title'];
$memo = $_POST['memo'];

date_default_timezone_set('Asia/Tokyo');
$updated_at = date('Y-m-d H:i:s');

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$directory = str_replace("\\", "/", dirname(__DIR__));
$current_directory_url = $protocol . "://" . $host . str_replace($_SERVER['DOCUMENT_ROOT'], '', $directory);

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // タイトルとメモを更新する
    $sql = "UPDATE pdf_versions SET title=:title, memo=:memo, updated_at=:updated_at WHERE pdf_id=:pdf_id AND cid=:cid";
    $stmt = $db->prepare($sql);
    $params = array(':pdf_id' => $tracking_id, ':cid' => $client_id, ':title' => $title, ':memo' => $memo, ':updated_at' => $updated_at);
    $stmt->execute($params);

    $stmt = $db->prepare("SELECT * FROM tags WHERE table_name=? AND table_id=?");
    $stmt->execute(['pdf_versions', $pdf_version_id]);
    $tag_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($tag_data)) {
        $tag_id = $tag_data['id'];
        if (isset($_POST['detail_tags'])) {
            $tags = implode(',', $_POST['detail_tags']);
            $stmt = $db->prepare('UPDATE tags SET tags=:tags WHERE id =:id');
            $stmt->execute(array(':tags' => $tags, ':id' => $tag_id));
        } else {
            $stmt = $db->prepare('DELETE FROM tags WHERE id = :id');
            $stmt->execute(array(':id' => $tag_id));
        }
    } else {
        if (isset($_POST['detail_tags'])) {
            $tags = implode(',', $_POST['detail_tags']);
            $sql = "INSERT INTO tags (table_name,table_id,tags) VALUES (:table_name,:table_id,:tags)";
            $stmt = $db->prepare($sql);
            $params = array(':table_name' => 'pdf_versions', ':table_id' => $pdf_version_id, ':tags' => $tags);
            $stmt->execute($params);
        }
    }

    if (is_uploaded_file($_FILES["pdf_file"]["tmp_name"])) {
        // 既存のファイル処理コード

        $sql = "INSERT INTO pdf_versions (pdf_id,cid,title,memo) VALUES (:pdf_id,:cid,:title,:memo)";
        $stmt = $db->prepare($sql);
        $params = array(':pdf_id' => $tracking_id, ':cid' => $client_id, ':title' => $title, ':memo' => $memo);
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
            if (move_uploaded_file($_FILES["pdf_file"]["tmp_name"], $file_name)) {
                // PDFを画像ファイルに変換
                // $pdfPath = $_SERVER['DOCUMENT_ROOT'] . '/pdf/' . $tracking_id . '_' . $pdf_version_id . '.pdf';
                // $imgInfo = new finfo(FILEINFO_MIME_TYPE);
                // $mimeType = $imgInfo->file($pdfPath);
                // $imagick = new Imagick();
                // $imagick->readImage($pdfPath);
                // $numPages = $imagick->getNumberImages();

                // if ($numPages == 1) {
                //     $imagick->writeImages($_SERVER['DOCUMENT_ROOT'] . '/pdf_img/' . $tracking_id . '_' . $pdf_version_id . '-0.jpg', false);
                // } else {
                //     $imagick->writeImages($_SERVER['DOCUMENT_ROOT'] . '/pdf_img/' . $tracking_id . '_' . $pdf_version_id . '-%d.jpg', false);
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

        // $directory = "./../pdf_img/";
        // $images = glob($directory . "/" . $tracking_id . "_" . $pdf_version_id . "-*.jpg");
        // if (empty($images)) {
        //     echo 'PDFが存在しません。';
        //     exit();
        // }

        $stmt = $db->prepare('UPDATE pdf_versions SET page_cnt=:page_cnt,file_name=:file_name WHERE pdf_version_id = :pdf_version_id');
        $stmt->execute(array(':pdf_version_id' => $pdf_version_id, ':page_cnt' => NULL, ':file_name' => $old_file_name));

        if (!empty($_POST['detail_tags'])) {
            $sql = "INSERT INTO tags (table_name,table_id,tags) VALUES (:table_name,:table_id,:tags)";
            $stmt = $db->prepare($sql);
            $params = array(':table_name' => 'pdf_versions', ':table_id' => $pdf_version_id, ':tags' => $tags);
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