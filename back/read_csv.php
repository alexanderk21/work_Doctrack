<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['csvFile']) && is_uploaded_file($_FILES['csvFile']['tmp_name'])) {
        $file_name = $_FILES['csvFile']['name'];
        $csv_data = array();
        if (($handle = fopen($_FILES['csvFile']['tmp_name'], 'r')) !== false) {
            $header = fgetcsv($handle, 0, ',', '"', '"');
            if (empty($header)) {
                header('Location: ./../email_distribution.php?err=empty_file');
                exit;
            }

            $company_name_categories = array('企業名', '会社名');
            $url_categories = array('企業URL', 'サイトurl', 'URL', '出典URL', 'HP');
            $optional_columns = array('メールアドレス', '企業URL', '姓', '名', '企業名', '電話番号', '部署名', '役職', '住所');
            $all_columns = $optional_columns;
            $column_indexes = array();
            $company_name_index = -1;
            $url_index = -1;
            $last_name_index = -1;

            $req2mb = 0;

            foreach ($header as $key => $column) {
                if (strpos($column, '企業名') !== false || strpos($column, '会社名') !== false) {
                    $req2mb = 1;
                    if ($company_name_index != -1) {
                        echo '<script type="text/javascript">
                        alert("同じカテゴリーの項目があります。CSVの中身をご確認ください。");
                            location.href = "./../email_distribution.php";
                        </script>';
                        exit;
                    } else {
                        $company_name_index = $key;
                    }
                } elseif (strpos($column, '性') !== false || strpos($column, '姓') !== false) {
                    $req2mb = 1;
                    if ($last_name_index != -1) {
                        echo '<script type="text/javascript">
                        alert("同じカテゴリーの項目があります。CSVの中身をご確認ください。");
                        location.href = "./../email_distribution.php";
                        </script>';
                        exit;
                    } else {
                        $last_name_index = $key;
                    }
                }
            }
            if ($req2mb == 0) {
                $header = array_map('mb_convert_encoding', $header, array_fill(0, count($header), 'UTF-8'), array_fill(0, count($header), 'Shift-JIS'));
                foreach ($header as $key => $column) {
                    if (in_array($column, $company_name_categories) && $company_name_index == -1) {
                        $company_name_index = array_search($column, $header);
                    } else if (in_array($column, $company_name_categories)) {
                        echo '<script type="text/javascript">
                        alert("同じカテゴリーの項目があります。CSVの中身をご確認ください。");
                            location.href = "./../email_distribution.php";
                        </script>';
                        exit;
                    }

                    if (in_array($column, $url_categories) && $url_index == -1) {
                        $url_index = array_search($column, $header);
                    } elseif (strpos($column, '企業URL') !== false || strpos($column, 'サイトurl') !== false || strpos($column, 'URL') !== false || strpos($column, '出典URL') !== false || strpos($column, 'HP') !== false) {
                        if ($url_index != -1) {
                            echo '<script type="text/javascript">
                            alert("同じカテゴリーの項目があります。CSVの中身をご確認ください。");
                            location.href = "./../email_distribution.php";
                            </script>';
                            exit;
                        } else {
                            $url_index = $key;
                        }
                    }
                }
            }

            if ($company_name_index != -1) {
                $header[$company_name_index] = '企業名';
            }

            if ($url_index != -1) {
                $header[$url_index] = '企業URL';
            }

            if ($last_name_index != -1) {
                $header[$last_name_index] = '姓';
            }

            foreach ($optional_columns as $column) {
                $index = array_search($column, $header);
                if ($index !== false) {
                    $column_indexes[$column] = $index;
                }
            }

            while (($row = fgetcsv($handle, 0, ',', '"', '"')) !== false) {
                if ($req2mb == 0) {
                    $row = array_map('mb_convert_encoding', $row, array_fill(0, count($row), 'UTF-8'), array_fill(0, count($row), 'Shift-JIS'));
                }
                // $row = array_map('mb_convert_encoding', $row, array_fill(0, count($row), 'UTF-8'), array_fill(0, count($row), 'Shift-JIS'));
                if (strpos($row[$column_indexes['メールアドレス']], '@') === false && strpos($row[$column_indexes['メールアドレス']], '＠') === false) {
                    $_SESSION['csv_data'] = null;
                    echo '<script type="text/javascript">
                        alert("メールアドレスが正しくありません。CSVファイルの内容をご確認ください。");
                        location.href = "./../email_distribution.php";
                        </script>';
                    exit;
                }
                if (strpos($row[$column_indexes['メールアドレス']], '@') === false) {
                    str_replace('＠', '@', $row[$column_indexes['メールアドレス']]);
                }

                $new_row = array();
                foreach ($all_columns as $column) {
                    if ($column == '企業URL' && strlen($row[$column_indexes[$column]]) > 200) {
                        echo '<script type="text/javascript">
                                alert("URLが200文字を超えています。 確認してください。");
                                location.href = "./../form_submission.php";
                            </script>';
                        exit;
                    }
                    if (isset($column_indexes[$column])) {
                        $new_row[] = $row[$column_indexes[$column]];
                    } else {
                        $new_row[] = ""; // Fill with empty if the optional column does not exist
                    }
                }
                $csv_data[] = $new_row;
            }
            fclose($handle);

            $csv_count = count($csv_data);
            if ( $csv_count > 200) {
                header('Location: ./../email_distribution.php?err=more_file');
                exit;
            }elseif($_POST['send_limit_type'] == 1 && $csv_count > $_POST['send_limit']){
                header('Location: ./../email_distribution.php?err=more_file');
                exit;
            }


            $emails = [];
            $confirm_csv_data = [];
            for ($i = 0; $i < count($csv_data); $i++) {
                array_push($emails, $csv_data[$i][0]);
            }
            // 各要素の出現数をカウントします
            $counts = array_count_values($emails);

            // counts 配列をループして、繰り返される要素を見つけます
            foreach ($counts as $number => $count) {
                if ($count > 1) {
                    $indices = array_keys($emails, $number);
                    $index = $indices[count($indices) - 1];
                    array_push($confirm_csv_data, $csv_data[$index]);
                } else if ($count == 1) {
                    $indices = array_keys($emails, $number);
                    $index = $indices[0];
                    array_push($confirm_csv_data, $csv_data[$index]);
                }
            }

            // 合計を確認する
            $csv_cnt = count($confirm_csv_data);
            $total = (int) $_POST['used_value'] + $csv_cnt;
            $_SESSION['csv_data'] = $confirm_csv_data;
            $_SESSION['file_name'] = $file_name;
            header('Location: ./../email_distribution.php');
            exit;
        }
    }
} else {
    echo '不正なアクセスです。';
    exit;
}
echo '<script type="text/javascript">
      alert("CSVファイルのアップロードに失敗しました。");
      location.href = "./../email_distribution.php";
      </script>';
exit;