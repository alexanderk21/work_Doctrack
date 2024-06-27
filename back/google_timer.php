<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Ramsey\Uuid\Guid\Guid;
ini_set('display_errors', 1);
error_reporting(E_ALL);

require ('/data/service/test.doctrack.jp/vendor/autoload.php');
require ('/data/service/test.doctrack.jp/PHPMailer/src/PHPMailer.php');
require ('/data/service/test.doctrack.jp/PHPMailer/src/Exception.php');
require ('/data/service/test.doctrack.jp/PHPMailer/src/SMTP.php');

function isValidEmail($email)
{
    // メールアドレスの形式をチェック
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // メールアドレスのドメイン部分を取得
    $domain = substr(strrchr($email, "@"), 1);

    // DNSでMXレコードが存在するかチェック
    if (!checkdnsrr($domain, "MX")) {
        return false;
    }

    return true;
}
function send_email($from, $from_name, $subject, $html_content, $text_content, $to, $smtp_host, $smtp_pw)
{
    // 受信者のメールアドレスが有効かチェック
    if (!isValidEmail($to)) {
        return 2; // 無効なメールアドレス
    }

    $ch = curl_init();

    $apiKey = "abccbd57256a40028ac7e3a8653b7930";
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://emailvalidation.abstractapi.com/v1/?api_key=' . $apiKey . '&email=' . $to);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $data = curl_exec($ch);

    curl_close($ch);
    $res = json_decode($data, true);
    if ($res['is_smtp_valid']['value']) {
        if ($smtp_host == "smtp.lolipop.jp") {
            $secure = "ssl";
            $smtp_port = 465;
        } else {
            $secure = "tls";
            $smtp_port = 587;
        }


        mb_language('uni');
        mb_internal_encoding('UTF-8');

        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 3;
        $mail->CharSet = 'utf-8';

        try {
            $mail->isSMTP();
            $mail->Host = $smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $from;
            $mail->Password = $smtp_pw;
            $mail->SMTPSecure = $secure;
            $mail->Port = $smtp_port;

            $mail->setFrom($from, $from_name);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html_content;
            $mail->AltBody = $text_content;

            if (!$mail->send()) {
                return (2);
            } else {
                return (1);
            }

        } catch (Exception $e) {
            if ($secure == "ssl") {
                $mail->SMTPSecure = "tls";
                $smtp_port = 587;
            } else {
                $mail->SMTPSecure = "ssl";
                $smtp_port = 465;
            }

            try {
                if ($mail->send()) {
                    return (1);
                } else {
                    return (2);
                }

            } catch (Exception $e) {
                return (2);
            }
        }
    } else {
        return (4);
    }
}

$driver = 'mysql';
$db = 'doctrack';
$host = '127.0.0.1';
$port = '3306';

$dsn = $driver . ':dbname=' . $db . ';host=' . $host . ";port=" . $port . ';charset=utf8;';
$user = 'php';
$pass = 'MyNew_Pass3!';

date_default_timezone_set('Asia/Tokyo');

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = "SELECT * FROM schedules";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $schedules_data = $stmt->fetchAll();

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
$s_num = 0;
$f_num = 0;
$d_num = 0;
$today = new DateTime(date("Y-m-d H:i:s"));

if (count($schedules_data) != 0) {
    foreach ($schedules_data as $d) {
        $d_time = new DateTime($d['scheduled_datetime']);
        if ($d_time < $today) {
            $csv_data = unserialize($d['to_data']);
            $uuid = Guid::uuid4()->toString();
            $token = str_replace('-', '', $uuid);
            $subject = $d['subject'];
            $from_email = $d['from_email'];
            $from_name = $d['from_name'];
            $client_id = $d['cid'];
            $template_id = $d['template_id'];
            $current_directory_url = "https://" . $client_id . ".doc1.jp";
            $stmt = $db->prepare('DELETE FROM schedules WHERE id = :id');
            $stmt->execute(array(':id' => $d['id']));

            if (empty($from_email)) {
                continue;
            }

            try {
                $db = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                
                $stmt = $db->prepare("UPDATE csv_history SET token=? WHERE token=?");
                $stmt->execute([$token, $d['id']]);
                
                $stmt = $db->prepare("SELECT * FROM froms WHERE email=?");
                $stmt->execute([$from_email]);
                $from_data = $stmt->fetch(PDO::FETCH_ASSOC);

                $from = $from_data['email'];
                $from_name = $from_data['from_name'];
                $cuser_id = $from_data['cuser_id'];

                $smtp_host = $from_data['smtp_host'];
                $smtp_pw = $from_data['smtp_pw'];

                $stmt = $db->prepare("SELECT * FROM clients WHERE id=?");
                $stmt->execute([$client_id]);
                $ClientData = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (PDOException $e) {
                echo '接続失敗' . $e->getMessage();
                exit();
            }
            
            
            foreach ($csv_data as $key1 => $csv) {

                $user_id = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, 4);
                $to = $csv[0];

                $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND cid = ?");
                $stmt->execute([$to, $client_id]);
                $existing_data = $stmt->fetch();
                $user_id = empty($existing_data) ? $user_id : $existing_data['user_id'];

                $company_url = $csv[1] ? $csv[1] : "";
                $surname = $csv[2] ? $csv[2] : "";
                $firstname = $csv[3] ? $csv[3] : "";
                $company = $csv[4] ? $csv[4] : "";
                $content_ = $d['content'];

                $parsed_url = $company_url ? parse_url($company_url) : [];

                if(!empty($parsed_url) && isset($parsed_url['scheme']) && isset($parsed_url['host'])){
                    $company_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
                }else{
                    $company_url = '';
                }

                //差し込みコードの変換
                $content_ = str_replace('{company}', $company_url, $content_);
                $content_ = str_replace('{lastname}', $surname, $content_);
                $content_ = str_replace('{firstname}', $firstname, $content_);
                $content_ = str_replace('{fromname}', $from_name, $content_);
                
                $subject = str_replace('{company}', $company_url, $subject);
                $subject = str_replace('{lastname}', $surname, $subject);
                $subject = str_replace('{firstname}', $firstname, $subject);
                $subject = str_replace('{fromname}', $from_name, $subject);
                //__pdf
                $pattern = '/{pdf-(.*?)}/';
                preg_match_all($pattern, $content_, $pdfs);
                $pdfNumbers = $pdfs[1];
                if (!is_null($pdfNumbers)) {
                    foreach ($pdfNumbers as $pn) {
                        $used_pdf_ids[] = $pn;
                        $search_str = '{pdf-' . $pn . '}';
                        $url_ = $pn . '/' . $user_id . '/' . $token;
                        $t_url = $current_directory_url . '/s?t=' . $url_;
                        $out_url = "<a href ='" . $t_url . "'>" . $t_url . "</a>";
                        $content_ = str_replace($search_str, $t_url, $content_);
                    }
                }

                //redl
                $pattern = '/{redl-(.*?)}/';
                preg_match_all($pattern, $content_, $redls);
                $redlNumbers = $redls[1];
                if (!is_null($redlNumbers)) {
                    foreach ($redlNumbers as $rn) {
                        $url_ = $rn . '/' . $user_id . '/' . $token;
                        $t_url = $current_directory_url . '/r?t=' . $url_;
                        $out_url = "<a href ='" . $t_url . "'>" . $t_url . "</a>";
                        $search_str = '{redl-' . $rn . '}';
                        $used_redirect_ids[] = $rn;
                        $content_ = str_replace($search_str, $t_url, $content_);

                    }
                }
                
                //cms
                $pattern = '/{cms-(.*?)}/';
                preg_match_all($pattern, $content_, $cmss);
                $cmsNumbers = $cmss[1];
                if (!is_null($cmsNumbers)) {
                    foreach ($cmsNumbers as $rn) {
                        $url_ = $rn . '/' . $user_id . '/' . $token;
                        $t_url = $current_directory_url . '/c?t=' . $url_;
                        $out_url = "<a href ='" . $t_url . "'>" . $t_url . "</a>";
                        $search_str = '{cms-' . $rn . '}';
                        $used_redirect_ids[] = $rn;
                        $content_ = str_replace($search_str, $t_url, $content_);

                    }
                }
                $content_ = makeClickableLinks($content_);

                $signature = $from_data['signature'];
                $signature = makeClickableLinks($signature);

                $html_content = nl2br($content_) . "<br><br>" . nl2br($signature);
                $text_content = strip_tags(str_replace("<br>", "\n", $content_)) . "\n\n" . strip_tags($signature);

                $stmt = $db->prepare("SELECT * FROM stops WHERE mail=:mail AND cid=:cid");
                $params = array(':mail' => $to, ':cid' => $client_id);
                $stmt->execute($params);
                $stop_data = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($stop_data && $stop_data['division'] != '停止解除') {
                    $status = '配信停止';
                    $d_num++;
                } else {
                    if (send_email($from, $from_name, $subject, $html_content, $text_content, $to, $smtp_host, $smtp_pw) == 1) {
                        $status = '配信成功';
                        $s_num++;
                    } else {
                        $status = '配信失敗';
                        $f_num++;
                        if (!$stop_data) {
                            $sql = "INSERT INTO stops (cid,mail,division) VALUES (:cid,:mail,:division)";
                            $stmt = $db->prepare($sql);
                            $params = array(':cid' => $client_id, ':mail' => $to, ':division' => '配信失敗');
                            $stmt->execute($params);
                        }
                    }
                }

                try {
                    $db = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    ]);
                    
                    $sql = "INSERT INTO logs (cid,to_email,template_id,user_id,subject,content,from_email,token,status,created_at) VALUES (:cid,:to_email,:template_id,:user_id,:subject,:content,:from_email,:token,:status,:created_at)";
                    $stmt = $db->prepare($sql);
                    $params = array(':cid' => $client_id, ':to_email' => $to, ':template_id' => $template_id, ':user_id' => $user_id, ':subject' => $subject, ':content' => $content_, ':from_email' => $from, ':token' => $token, ':status' => $status, ':created_at' => $d['scheduled_datetime']);
                    $stmt->execute($params);

                    // 既存のデータを取得
                    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ? AND cid = ?");
                    $stmt->execute([$user_id, $client_id]);
                    $existing_data = $stmt->fetch();

                    if ($existing_data) {
                        // UPDATE文を実行
                        $stmt = $db->prepare("UPDATE users SET email = ?, url = ?, company = ?, surename = ?, name = ? WHERE user_id = ? AND cid = ?");
                        $stmt->execute([$to, $company_url, $company, $surname, $firstname, $user_id, $client_id]);
                    } else {
                        // 新しいデータを挿入
                        $stmt = $db->prepare("INSERT INTO users (user_id, cid, cuser_id, email, url, company, surename, name, route) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$user_id, $client_id, $cuser_id, $to, $company_url, $company, $surname, $firstname, 'メール配信']);
                    }

                    // $csv_data から CSV ファイルを生成する
                    
                } catch (PDOException $e) {
                    echo '接続失敗_' . $e->getMessage();
                    exit();
                }
            }
            $csv = '"顧客ID","宛先アドレス","差し込みコード１","差し込みコード２","差し込みコード３","差し込みコード４","差し込みコード５","差し込みコード６","差し込みコード７","差し込みコード８","差し込みコード９","差し込みコード１０"' . "\n";
            foreach ($csv_data as $row) {
                $csv .= implode(',', $row) . "\n";
            }
            $filename = $token . '.csv';
            $file_path = '/data/service/test.doctrack.jp/upload_csv/' . $filename;
            if (file_put_contents($file_path, $csv) !== false) {
                echo 'ファイルの保存に成功しました。';
            } else {
                echo 'ファイルの保存に失敗しました。';
            }
        }
    }
    $db = null;
    
    //log
    $res_file = fopen('timer_log.txt', 'a+');
    fwrite($res_file, date("Y-m-d H:i:s") . '成功: ' . $s_num . ', ' . '失敗: ' . $f_num . ', ' . '停止: ' . $d_num . "\n");
    fclose($res_file);

} else {
    echo "登録されたスケジュールはありません！";
}

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = "SELECT * FROM business_schedules";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $schedules_data = $stmt->fetchAll();

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}

$today = new DateTime(date("Y-m-d H:i:s"));

if (count($schedules_data) != 0) {
    foreach ($schedules_data as $d) {
        $d_time = new DateTime($d['scheduled_datetime']);

        if ($d_time < $today) {
            $to_data = json_decode($d['to_data'], true);
            $subject = $d['subject'];
            $from_email = $d['from_email'];
            $from_name = $d['from_name'];
            $client_id = $d['cid'];
            $template_id = $d['template_id'];
            $smtp_host = $d['smtp_host'];
            $smtp_pw = $d['smtp_pw'];

            if (empty($from_email)) {
                continue;
            }
            $stmt = $db->prepare('DELETE FROM business_schedules WHERE id = :id');
            $stmt->execute(array(':id' => $d['id']));

            foreach ($to_data as $key1 => $data) {
                $cemail = $data['cemail'];
                $html_content = $data['html_content'];
                $text_content = $data['text_content'];
                $log_content = $data['log_content'];
                $user_id = $data['user_id'];
                $token = $data['token'];

                if (send_email($from_email, $from_name, $subject, $html_content, $text_content, $cemail, $smtp_host, $smtp_pw) == 1) {
                    $status = '配信成功';
                } else {
                    $status = '配信失敗';
                }

                try {
                    $db = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    ]);
                    $sql = "INSERT INTO logs (cid,to_email,template_id,user_id,subject,content,from_email,token,status) VALUES (:cid,:to_email,:template_id,:user_id,:subject,:content,:from_email,:token,:status)";
                    $stmt = $db->prepare($sql);
                    $params = array(':cid' => $client_id, ':to_email' => $cemail, ':template_id' => $template_id, ':user_id' => $user_id, ':subject' => $subject, ':content' => $log_content, ':from_email' => $from_email, ':token' => $token, ':status' => $status);
                    $stmt->execute($params);

                } catch (PDOException $e) {
                    echo '接続失敗_' . $e->getMessage();
                    exit();
                }
            }
        }
    }
    $db = null;
    //log
    $res_file = fopen('timer_log.txt', 'a+');
    fwrite($res_file, date("Y-m-d H:i:s") . ' ' . "\n");

    fclose($res_file);

} else {
    echo "登録されたスケジュールはありません！";
}

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $document_url = 'https://testtest.doc1.jp';
    $local_path = '/data/service/test-2.doc1.jp/pdf_img'; // Local path to save images
    $sql = "SELECT * FROM pdf_versions WHERE page_cnt is NULL";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $new_pdf_data = $stmt->fetch();

    if (!empty($new_pdf_data)) {
        $pdfPath = $document_url . '/pdf/' . $new_pdf_data['pdf_id'] . '_' . $new_pdf_data['pdf_version_id'] . '.pdf';
        $local_file_path = $local_path . '/' . $new_pdf_data['pdf_id'] . '_' . $new_pdf_data['pdf_version_id'];
        $directory = "./../pdf_img/";
        $images = glob($directory . "/" . $new_pdf_data['pdf_id'] . "_" . $new_pdf_data['pdf_version_id'] . "-*.jpg");
        if (empty($images)) {
            $imagick = new Imagick();
            $imagick->setResolution(300, 300);
            $imagick->readImage($pdfPath);
            $imagick->setImageCompressionQuality(100);
            $numPages = $imagick->getNumberImages();
    
            if ($numPages == 1) {
                $imagick->writeImages($local_file_path . '-0.jpg', false);
            } else {
                $imagick->writeImages($local_file_path . '-%d.jpg', false);
            }
    
            $stmt = $db->prepare('UPDATE pdf_versions SET page_cnt=:page_cnt WHERE pdf_version_id = :pdf_version_id');
            $stmt->execute(array(':pdf_version_id' => $new_pdf_data['pdf_version_id'], ':page_cnt' => $numPages));
        }
    }

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
function makeClickableLinks($text)
{
    // URL pattern matching
    $pattern = '/(https?:\/\/[^\s]+)/';
    // Replace URLs with clickable links
    $text = preg_replace($pattern, '<a href="$1" target="_blank">$1</a>', $text);
    return $text;
}
define('API_URL', 'https://api.chatwork.com/v2');

function run()
{
    $contacts = getContacts();
    $res_file = fopen('timer_log.txt', 'a+');
    if (!$contacts) {
        fwrite($res_file, '承認待ちユーザーがいません。' . "\n");
        fclose($res_file);
        return;
    }
    $req_room = '';
    foreach ($contacts as $contact) {
        $user_info = approveContactById($contact['request_id']);
        $req_room .= $user_info['room_id'] . ', ';
    }
    fwrite($res_file, date("Y-m-d H:i:s") . '承認された部屋: ' . $req_room . "\n");
    fclose($res_file);
}
function getContacts()
{
    $end_point = "/incoming_requests";
    $response = chatworkRequest('GET', $end_point);
    return json_decode($response, true);
}
function approveContactById($request_id)
{
    $end_point = "/incoming_requests/" . $request_id;
    $response = chatworkRequest('PUT', $end_point);
    return json_decode($response, true);
}
function chatworkRequest($method, $end_point, $data = [])
{
    $url = API_URL . $end_point;
    $headers = [
        'X-ChatWorkToken: ' . '29a5c3f4be07e1f71981f4afd1c9925c',
    ];

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ];

    if ($method === 'POST' || $method === 'PUT') {
        $options[CURLOPT_POSTFIELDS] = http_build_query($data);
    }

    $curl = curl_init();
    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    curl_close($curl);

    return $response;
}

run();