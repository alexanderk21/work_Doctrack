<?php
require '../vendor/autoload.php';
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Guid\Guid;

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (isset($_SESSION['csv_data_form'])) {

    include_once '../common.php';

    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    // セッションからログデータを取得
    $csv_data_form = $_SESSION['csv_data_form'];
    // セッションからログデータを削除
    unset($_SESSION["csv_data_form"]);

    // $token = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'),0,16);
    $uuid = Guid::uuid4()->toString();
    $token = str_replace('-', '', $uuid);

    // データを反転してCSV形式の文字列に変換
    foreach ($csv_data_form as $key1 => $value) {
        $user_id = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, 4);
        $existingUser = $db->query("SELECT * FROM users WHERE user_id = '$user_id'")->fetch();

        while ($existingUser) {
            $user_id = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, 4);
            $existingUser = $db->query("SELECT * FROM users WHERE user_id = '$user_id'")->fetch();
        }
        $email = $value[0] ?? '';
        $url = $value[1] ?? '';
        $company = $value[2] ?? '';
        $surname = $value[3] ?? '';
        $name = $value[4] ?? '';
        $tel = $value[5] ?? '';
        $depart = $value[6] ?? '';
        $role = $value[7] ?? '';
        $address = $value[8] ?? '';

        $parsed_url = $url ? parse_url($url) : [];

        if(!empty($parsed_url) && isset($parsed_url['scheme']) && isset($parsed_url['host'])){
            $url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
        }else{
            $url = '';
        }

        $stmt = $db->prepare("SELECT * FROM users WHERE url = ? AND cid = ?");
        $stmt->execute([$url, $client_id]);
        $user_result = $stmt->fetchAll();
        $count = count($user_result);
        if ($count != 0) {
            $user_id = $user_result[0]['user_id'];
        }


        $stmt = $db->prepare("SELECT * FROM templates WHERE id=?");
        $stmt->execute([$_POST['template_id']]);
        $template_data = $stmt->fetch(PDO::FETCH_ASSOC);

        $title = $template_data['subject'];
        $content_ = $template_data['content'];
        if (isset($value[2])) {
            $content_ = str_replace('{company}', $value[2], $content_);
        }
        if (isset($value[3])) {
            $content_ = str_replace('{lastname}', $value[3], $content_);
        }
        if (isset($value[4])) {
            $content_ = str_replace('{firstname}', $value[4], $content_);
        }
        $content_ = str_replace('{fromname}', '', $content_);
        // 一つ上の階層を取得
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $directory = dirname(dirname($_SERVER['PHP_SELF']));
        $current_directory_url = $protocol . "://" . $host . $directory;
        
        $pattern = '/{pdf-(.*?)}/';
        preg_match_all($pattern, $content_, $pdfs);
        $pdfNumbers = $pdfs[1];
        if (!is_null($pdfNumbers)) {
            foreach ($pdfNumbers as $pn) {
                $url_ = $pn . '/' . $user_id;
                $t_url = $current_directory_url . 's?t=' . $url_;
                $search_str = '{pdf-' . $pn . '}';
                $content_ = str_replace($search_str, $t_url, $content_);
            }
        }

        $pattern = '/{redl-(.*?)}/';
        preg_match_all($pattern, $content_, $redls);
        $redlNumbers = $redls[1];
        if (!is_null($redlNumbers)) {
            foreach ($redlNumbers as $rn) {
                $url_ = $rn . '/' . $user_id;
                $t_url = $current_directory_url . 'r?t=' . $url_;
                $search_str = '{redl-' . $rn . '}';
                $content_ = str_replace($search_str, $t_url, $content_);

            }
        }
        $pattern = '/{cms-(.*?)}/';
        preg_match_all($pattern, $content_, $cmss);
        $cmsNumbers = $cmss[1];
        if (!is_null($cmsNumbers)) {
            foreach ($cmsNumbers as $rn) {
                $url_ = $rn . '/' . $user_id;
                $t_url = $current_directory_url . 'c?t=' . $url_;
                $search_str = '{cms-' . $rn . '}';
                $content_ = str_replace($search_str, $t_url, $content_);

            }
        }

        $content = $content_;

        // 顧客情報の登録
        if (!empty($email)) {
            $setClauses[] = 'email = :email';
            $params[':email'] = $email;
        }
        if (!empty($url)) {
            $setClauses[] = 'url = :url';
            $params[':url'] = $url;
        }
        if (!empty($company)) {
            $setClauses[] = 'company = :company';
            $params[':company'] = $company;
        }
        if (!empty($surname)) {
            $setClauses[] = 'surename = :surename';
            $params[':surename'] = $surname;
        }
        if (!empty($firstname)) {
            $setClauses[] = 'name = :name';
            $params[':name'] = $firstname;
        }

        if (!empty($tel)) {
            $setClauses[] = 'tel = :tel';
            $tel = str_replace("-", "", $tel);
            if (substr($tel, 0, 1) != "0") {
                $tel = "0" . $tel;
            }
            $params[':tel'] = $tel;
        }
        if (!empty($depart)) {
            $setClauses[] = 'depart_name = :depart_name';
            $params[':depart_name'] = $depart;
        }
        if (!empty($role)) {
            $setClauses[] = 'director = :director';
            $params[':director'] = $role;
        }
        if (!empty($address)) {
            $setClauses[] = 'address = :address';
            $params[':address'] = $address;
        }
        if ($count == 0) {
            $stmt = $db->prepare("INSERT INTO users (user_id, cid, cuser_id, email, url, company, surename, name, tel, depart_name, director, address, route) VALUES (?, ?,?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $client_id, $ClientUser['id'], $email, $url, $company, $surname, $name, $tel, $depart, $role, $address, 'フォーム営業']);
        } else {
            $stmt = $db->prepare('UPDATE users SET ' . implode(", ", $setClauses) . ' WHERE user_id = :user_id AND cid = :cid');
            $params[':user_id'] = $user_id;
            $params[':cid'] = $client_id;
            $stmt->execute($params);
        }

        //logs
        try {
            $stmt = $db->prepare("INSERT INTO form_logs (user_id,cid,template_id,subject,content,token,company,lastname,firstname) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $client_id, $template_data['id'], $title, $content, $token, $company, $surname, $name]);
        } catch (PDOException $e) {
            echo 'ログの書き込みに失敗しました。' . $e->getMessage();
            exit();
        }
    }
    $csv = '"企業URL","企業名","姓","名"' . "\n";
    foreach ($csv_data_form as $row) {
        $csv .= implode(',', $row) . "\n";
    }
    $filename = $token . '.csv';
    $file_path = './../upload_csv/' . $filename;
    if (file_put_contents($file_path, $csv) !== false) {
        // echo 'ファイルの保存に成功しました。';
    } else {
        // echo 'ファイルの保存に失敗しました。';
    }

    echo "<script>
            location.href = '../send_list.php';
        </script>";
    exit();

} else {
    // セッションが存在しない場合
    echo "<script>
            alert('登録に失敗しました。');
            location.href = './../form_submission.php';
        </script>";
    exit();
}

