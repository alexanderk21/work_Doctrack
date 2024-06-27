<?php
if (isset($_POST['escapedData'])) {
    $escapedData = $_POST['escapedData'];

    include_once '../config/database.php';

    $data = json_decode($escapedData, true);
    // 不要なID列を削除
    foreach ($data as &$row) {
        array_shift($row);
    }
    unset($row);

        // CSVファイルとして出力
        $filename = "アップロード履歴" . date('Y-m-d') . ".csv";
        header('Content-Type: text/csv; charset=Shift-JIS');
        header('Content-Disposition: attachment; filename=' . $filename);
        $stream = fopen('php://output', 'w');
        // ヘッダ行を出力
        $header = array('ページ数', '閲覧数', '閲覧時間');
        fputcsv($stream, array_map(function($value) { return mb_convert_encoding($value, 'SJIS', 'UTF-8'); }, $header));
        // データ行を出力
        foreach ($data as $row) {
            // 行の各列をUTF-8からShift-JISに変換
            array_walk($row, function(&$value){
                $value = mb_convert_encoding($value, 'SJIS', 'UTF-8');
            });
            fputcsv($stream, $row);
        }
        fclose($stream);
        exit();

} else {
    echo 'ファイルが存在しません。';
    exit();
}
