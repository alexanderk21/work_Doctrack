<?php

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $subject = $_GET['subject'];
    $filename = $token . '.csv';
    $file_path = './../upload_csv/' . $token . '.csv';
    if (file_exists($file_path)) {
        // 出力バッファリングを有効にする
        ob_start();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $subject . '.csv"');

        // ファイルの読み込み
        readfile($file_path);

        // 出力バッファをフラッシュする
        ob_end_flush();
        exit();
    } else {
        $csv = "申し訳ありませんが、ファイルが保存されませんでした。\n";
        $file_path = './../upload_csv/' . $filename;
        if (file_put_contents($file_path, $csv) !== false) {
            ob_start();
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $subject . '.csv"');

            // ファイルの読み込み
            readfile($file_path);

            // 出力バッファをフラッシュする
            ob_end_flush();
            exit();
        } else {
            echo 'ファイルが存在しません。';
        }
        exit();
    }
}