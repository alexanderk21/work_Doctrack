<?php

$csv_data = json_decode($_POST['csv_data'], true);
foreach ($csv_data as &$data) {
    unset($data['pdf_version_id']);
    unset($data['pdf_id']);
    unset($data['title']);
}

date_default_timezone_set('Asia/Tokyo');
$filename = "アクセス解析".date('Y-m-d').".csv";

$header = array(
    '日時',
    '顧客ID',
    '会社名',
    '区分',
    'タイトル',
    '閲覧時間',
    'ページ移動数',
    'CTAクリック数',
);

// CSVファイルを書き込むためのストリームを開く
$stream = fopen('php://temp', 'w+b');

// ヘッダーを書き込む
fputcsv($stream, $header);

// データを書き込む
foreach ($csv_data as $data) {
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
