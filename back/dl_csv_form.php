<?php
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    include_once '../config/database.php';

    try {
        $db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $sql = "SELECT f.user_id, u.url, f.company, f.lastname, f.firstname, f.subject, f.content, f.cid, f.template_id
        FROM form_logs f 
        JOIN users u ON f.user_id = u.user_id 
        WHERE f.token=?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$token]);
        $logs_data = $stmt->fetchAll();

        if (!empty($logs_data)) {
            // CSVファイルとして出力
            $subject = isset($logs_data[0]['subject']) ? $logs_data[0]['subject'] : "Unknown";
            $filename = mb_substr($subject, 0, 15, 'UTF-8') . "-送信履歴" . date('Y-m-d') . ".csv";
            header('Content-Type: text/csv; charset=Shift-JIS');
            header('Content-Disposition: attachment; filename=' . $filename);
            $stream = fopen('php://output', 'w');
            
            // ヘッダ行を出力
            $header = array('顧客ID', '企業URL', '企業名', '姓', '名', '本文');
            fputcsv($stream, array_map(function ($value) {
                return mb_convert_encoding($value, 'SJIS', 'UTF-8'); }, $header));
            // データ行を出力
            foreach ($logs_data as $row) {

                $content = $row['content'];
                $client_id = $row['cid'];
                $user_id = $row['user_id'];

                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                $directory = str_replace("\\", "/", dirname(__DIR__));
                $current_directory_url = $protocol . "://" . $host . str_replace($_SERVER['DOCUMENT_ROOT'], '', $directory);

                $placeholders = [
                    'company' => '',
                    'lastname' => '',
                    'firstname' => ''
                ];

                foreach ($placeholders as $key => $value) {
                    if (isset($row[$key])) {
                        $placeholders[$key] = $row[$key];
                        $content = str_replace('{' . $key . '}', $row[$key], $content);
                    }
                }

                //差し込みコードの変換
                //__pdf
                try {
                    // $stmt = $db->prepare("SELECT * FROM pdf_versions WHERE cid =?");
                    // $stmt->execute([$client_id]);
                    // $pdf_version_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    // $unique_pdf_version_ids = array_unique($pdf_version_ids);

                    // $pdf_placeholders = implode(',', array_fill(0, count($unique_pdf_version_ids), '?'));
                    // $pdf_sql = "SELECT DISTINCT pdf_id, title
                    //         FROM pdf_versions
                    //         WHERE pdf_version_id IN ($pdf_placeholders)";
                    // $pdf_stmt = $db->prepare($pdf_sql);
                    // $pdf_stmt->execute(array_values($unique_pdf_version_ids));
                    // $pdf_data = $pdf_stmt->fetchAll(PDO::FETCH_ASSOC);
                    $used_pdf_ids = array();

                    // foreach ($pdf_data as $i => $pdf_d) {

                    //     $url_ = $pdf_d['pdf_id'] . '/' . $user_id;
                    //     $t_url = $current_directory_url . '/s?t=' . $url_;
                    //     $search_str = '{pdf' . ($i + 1) . '}';
                    //     if (strpos($content, $search_str) !== false) {
                    //         $used_pdf_ids[] = $pdf_d['pdf_id'];
                    //         $content = str_replace($search_str, $t_url, $content);
                    //     }
                    // }
                    $pattern = '/{pdf-(.*?)}/';
                    preg_match_all($pattern, $content, $pdfs);
                    $pdfNumbers = $pdfs[1];
                    if (!is_null($pdfNumbers)) {
                        foreach ($pdfNumbers as $pn) {
                            $used_pdf_ids[] = $pn;
                            $url_ = $pn . '/' . $user_id;
                            $t_url = $current_directory_url . '/s?t=' . $url_;
                            $search_str = '{pdf-' . $pn . '}';
                            $content = str_replace($search_str, $t_url, $content);
                        }
                    }

                } catch (PDOException $e) {
                    echo 'PDF差し込みコードの置換に失敗しました。' . $e->getMessage();
                    exit();
                }
                ;
                //redl
                try {
                    // $stmt = $db->prepare("SELECT * FROM redirects WHERE cid=?");
                    // $stmt->execute([$client_id]);
                    // $redirects_data = $stmt->fetchAll();

                    $used_redirect_ids = array();
                    // foreach ($redirects_data as $i => $redirect) {
                    //     $url_ = $redirect['id'] . '/' . $user_id;
                    //     $t_url = $current_directory_url . '/r?t=' . $url_;
                    //     $search_str = '{redl' . ($i + 1) . '}';
                    //     if (strpos($content, $search_str) !== false) {
                    //         $used_redirect_ids[] = $redirect['id'];
                    //         $content = str_replace($search_str, $t_url, $content);
                    //     }
                    // }
                    $pattern = '/{redl-(.*?)}/';
                    preg_match_all($pattern, $content, $redls);
                    $redlNumbers = $redls[1];
                    if (!is_null($redlNumbers)) {
                        foreach ($redlNumbers as $rn) {
                            $url_ = $rn . '/' . $user_id;
                            $t_url = $current_directory_url . '/r?t=' . $url_;
                            $search_str = '{redl' . $rn . '}';
                            $used_redirect_ids[] = $rn;
                            $content = str_replace($search_str, $t_url, $content);
            
                        }
                    }
                } catch (PDOException $e) {
                    echo 'リダイレクト差し込みコードの置換に失敗しました。' . $e->getMessage();
                    exit();
                }
                ;

                $data = [
                    $row['user_id'],
                    $row['url'],
                    $placeholders['company'],
                    $placeholders['lastname'],
                    $placeholders['firstname'],
                    $content,
                ];

                // 行の各列をUTF-8からShift-JISに変換
                array_walk($data, function (&$value) {
                    $value = mb_convert_encoding($value, 'SJIS', 'UTF-8');
                });
                fputcsv($stream, $data);
            }
            fclose($stream);
            exit();
        } else {
            echo "<script>
                    alert('該当のデータが存在しません。');
                    location.href = './../send_list.php';
                </script>";
            exit();
        }

    } catch (PDOException $e) {
        echo '接続失敗' . $e->getMessage();
        exit();
    }

} else {
    echo "<script>
            alert('ファイルが存在しません。');
            location.href = './../send_list.php';
        </script>";
    exit();
    exit();
}

?>