<?php

$send_list_data = json_decode($_POST['send_list_data'], true);

date_default_timezone_set('Asia/Tokyo');
$filename = "送信一覧".date('Y-m-d').".csv";
// header("Content-Type: text/csv");
// header('Content-Disposition: attachment; filename=' . $filename);
// header("Content-Transfer-Encoding: binary");

$header = array(
    '作成日時',
    'テンプレート名',
    '顧客数',
    'アクセス数'
);

// CSVファイルを書き込むためのストリームを開く
$stream = fopen('php://output', 'w+b');

// BOMを書き込む
// fputs($stream, "\xEF\xBB\xBF");
fputcsv($stream, $header);

// ヘッダーを書き込む
// fputcsv($stream, array_map(function($value) { return mb_convert_encoding($value, 'SJIS', 'UTF-8'); }, $header));

// データを書き込む
foreach ($send_list_data as $data) {
    fputcsv($stream, $data);
}

// ストリームの位置を先頭に戻す
rewind($stream);

// ストリームからデータを取得する
$data = stream_get_contents($stream);

// ストリームを閉じる
fclose($stream);

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Transfer-Encoding: binary");

// ファイル名をエンコードする
$encoded_filename = rawurlencode($filename);

// Content-Dispositionを設定する
header("Content-Disposition: attachment; filename*=UTF-8''{$encoded_filename}");

// データを出力する
echo mb_convert_encoding($data, "SJIS", "UTF-8");
