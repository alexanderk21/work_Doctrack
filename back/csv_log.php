<?php
if(isset($_POST['send_list_data']) && $_POST['send_list_data'] != '[]'){
    $send_list_data = json_decode($_POST['send_list_data'], true);

    $filename='配信ログ.csv';
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=".$filename);
    header("Content-Transfer-Encoding: binary");

    $header = ["No","配信日時","差出アドレス","テンプレート名","宛先数","アクセス数"];

    $stream = fopen('php://output', 'w');
    
    // BOMを書き込む
    // fputs($stream, "\xEF\xBB\xBF");
    // ヘッダーを書き込む
    fputcsv($stream, array_map(function($value) { return mb_convert_encoding($value, 'SJIS', 'UTF-8'); }, $header));
    
    foreach($send_list_data as $data) {
        array_walk($data, function(&$value){
            $value = mb_convert_encoding($value, 'SJIS', 'UTF-8');
        });
        fputcsv($stream, $data);
    }

    // ストリームからデータを取得する
    $data = stream_get_contents($stream);

    
    // ストリームを閉じる
    fclose($stream);
    // ファイル名をエンコードする
    $encoded_filename = rawurlencode($filename);
    
    // Content-Dispositionを設定する
    header("Content-Disposition: attachment; filename*=UTF-8''{$encoded_filename}");

    // データを出力する
    echo mb_convert_encoding($data, "SJIS", "UTF-8");
}else if(isset($_POST['individual_logs']) && $_POST['individual_logs'] != '[]'){

    $logs_data = json_decode($_POST['individual_logs'], true);
   
    date_default_timezone_set('Asia/Tokyo');
    $filename="配信ログ".date('Y-m-d H-i-s').".csv";
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=".$filename);
    header("Content-Transfer-Encoding: binary");

    $header = ["No","配信日時","差出アドレス", "宛先アドレス", "レスポンス内容"];

    $stream = fopen('php://output', 'w');
    
    // BOMを書き込む
    // fputs($stream, "\xEF\xBB\xBF");
    // ヘッダーを書き込む
    fputcsv($stream, array_map(function($value) { return mb_convert_encoding($value, 'SJIS', 'UTF-8'); }, $header));
    
    foreach(array_reverse($logs_data) as $data) {
        array_walk($data, function(&$value){
            $value = mb_convert_encoding($value, 'SJIS', 'UTF-8');
        });
        fputcsv($stream, $data);
    }

    // ストリームからデータを取得する
    $data = stream_get_contents($stream);

    
    // ストリームを閉じる
    fclose($stream);
    // ファイル名をエンコードする
    $encoded_filename = rawurlencode($filename);
    
    // Content-Dispositionを設定する
    header("Content-Disposition: attachment; filename*=UTF-8''{$encoded_filename}");

    // データを出力する
    echo mb_convert_encoding($data, "SJIS", "UTF-8");
}else{
    echo "<script>
              alert('登録情報はありません。');
              location.href = './../distribution_list.php';
          </script>";
}


