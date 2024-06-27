<?php

$csv_data = json_decode($_POST['csv_data'], true);

foreach ($csv_data as &$data) {
    unset($data['滞在秒']);
    unset($data['profile_image']);
    unset($data['route']);
    unset($data['form_result']);
    unset($data['client_user']);
}
date_default_timezone_set('Asia/Tokyo');
$filename = "顧客一覧".date('Y-m-d').".csv";

$header = array(
    '顧客ID',
    '企業名',
    '姓',
    '名',
    'メールアドレス',
    '企業URL',
    '電話番号',
    '部署名',
    '役職',
    '住所',
    '登録日時',
    'ポイント',
    'ステージ',
    'アクセス数',
    '閲覧時間',
    'ページ移動数',
    'CTAクリック数',
    '未アクセス期間',
    'サービス詳細',
    'マッチングしたい人',
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
