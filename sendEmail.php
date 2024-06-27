<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
require ('common.php');
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$directory = dirname($_SERVER['PHP_SELF']);
$current_directory_url = $protocol . "://" . $host . $directory;

$from = $_POST['from'];

if (isset($_SESSION['csv_data'])) {
    $csv_data = $_SESSION['csv_data'];
} elseif (isset($_SESSION['email_data'])) {
    $email_data = $_SESSION['email_data'];
} else {
    echo "<script>alert('送信先をアップロードしてください。');
    location.href = './template.php';</script>";
}
if (isset($_SESSION['csv_data_form']))
unset($_SESSION["csv_data_form"]);

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    $stmt = $db->prepare("SELECT * FROM templates WHERE id=?");
    $stmt->execute([$_POST['id']]);
    $template_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT * FROM froms WHERE id=?");
    $stmt->execute([$from]);
    $from_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $fromname = $from_data['from_name'] ?? '';
    
    if($from_data['smtp_host'] == ''){
        echo "<script>alert('送信先をアップロードしてください。');
        location.href = './email_distribution.php';</script>";
        exit;
    }

    // サンプルで1行目だけ差し込みコードの置換
    if (isset($_SESSION['csv_data'])) {
        $company = $csv_data[0][4] ?? '';
        $surname = $csv_data[0][2] ?? '';
        $firstname = $csv_data[0][3] ?? '';

        $subject_ = $template_data['subject'];
        $subject_ = str_replace('{company}', $company, $subject_);
        $subject_ = str_replace('{lastname}', $surname, $subject_);
        $subject_ = str_replace('{firstname}', $firstname, $subject_);
        $subject_ = str_replace('{fromname}', $fromname, $subject_);
        
        $content_ = $template_data['content'];
        $content_ = str_replace('{company}', $company, $content_);
        $content_ = str_replace('{lastname}', $surname, $content_);
        $content_ = str_replace('{firstname}', $firstname, $content_);
        $content_ = str_replace('{fromname}', $fromname, $content_);

        foreach ($csv_data as $each) {
            $email = $each[0] ?? '';
            $company_url = $each[1] ?? '';

            $parsed_url = $company_url ? parse_url($company_url) : [];

            if(!empty($parsed_url) && isset($parsed_url['scheme']) && isset($parsed_url['host'])){
                $company_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
            }else{
                $company_url = '';
            }

            $company = $each[4] ?? '';
            $surname = $each[2] ?? '';
            $firstname = $each[3] ?? '';
            $tel = $each[5] ?? '';
            $depart = $each[6] ?? '';
            $director = $each[7] ?? '';
            $address = $each[8] ?? '';

            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND cid = ?");
            $stmt->execute([$email, $client_id]);
            $existing_data = $stmt->fetch();

            if (empty($existing_data)) {
                do {
                    $user_id = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, 4);
                    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $existing_user = $stmt->fetch();
                } while ($existing_user);

                $stmt = $db->prepare("INSERT INTO users (user_id, cid, cuser_id, email, url, company, surename, name, tel, depart_name, director, address, route) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $client_id, $ClientUser['id'], $email, $company_url, $company, $surname, $firstname, $tel, $depart, $director, $address, 'メール配信']);
            }else{
                $existing_data['user_id'];
            }
        }
    } elseif (isset($_SESSION['email_data'])) {
        $user_id = $email_data[0][5];

        $company = $email_data[0][4] ?? '';
        $surname = $email_data[0][2] ?? '';
        $firstname = $email_data[0][3] ?? '';
        
        $subject_ = $template_data['subject'];
        $subject_ = str_replace('{company}', $company, $subject_);
        $subject_ = str_replace('{lastname}', $surname, $subject_);
        $subject_ = str_replace('{firstname}', $firstname, $subject_);
        $subject_ = str_replace('{fromname}', $fromname, $subject_);

        $content_ = $template_data['content'];
        $content_ = str_replace('{company}', $company, $content_);
        $content_ = str_replace('{lastname}', $surname, $content_);
        $content_ = str_replace('{firstname}', $firstname, $content_);
        $content_ = str_replace('{fromname}', $fromname, $content_);
    }

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
    
    $sample_content = nl2br($content_) . "<br><br>" . nl2br($from_data['signature']);

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}

?>
<?php require ('header.php'); ?>

<body>

    <div class="wrapper">
        <?php require ('menu.php'); ?>
        <main>
            宛先アドレス数
            <?php isset($csv_data) ? print_r(count($csv_data)) : print_r(count($email_data)) ?>件
            <br>
            <br>
            <h5>差出アドレス</h5>
            <?= $from_data['email'] ?>
            <br><br>
            <h5>メール件名</h5>
            <?= $subject_ ?>
            <br><br>
            <h5>メール本文</h5>
            <?= $sample_content ?>
            <br><br>
            <br>
            <table>
                <form action="back/send_email.php" method="post">
                    <input type="hidden" name="cid" value="<?= $client_id ?>">
                    <input type="hidden" name="from_id" value="<?= $from ?>">
                    <input type="hidden" name="template_id" value="<?= $_POST['id'] ?>">
                    <input type="hidden" name="content" value="<?= $template_data['content'] ?>">
                    <td><button class="btn btn-primary">今すぐ配信</button></td>
                </form>
                <td>
                    <div class="modal fade" id="detail" role="dialog" data-bs-backdrop="static" data-bs-keyboard="false"
                        tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">予約日時</h5>
                                    <button type="button" id="close" class="btn-close" data-bs-dismiss="modal"
                                        aria-bs-label="Close" onclick="inition()">

                                    </button>
                                </div>
                                <!-- modal-body -->
                                <div class="modal-body" id="modal_req">
                                    <form action="back/set_schedule.php" method="post">
                                        <table>
                                            <tr>
                                                <td><input type="date" name="date" id="date"></td>
                                                <td>
                                                    <select name="hour" id="hour">

                                                    </select>
                                                    <select name="minute" id="minute">

                                                    </select>

                                                </td>
                                            </tr>
                                        </table>
                                        <br>
                                        <br>
                                </div>
                                <div class="modal-footer">
                                    <input type="hidden" name="cid" value="<?= $client_id ?>">
                                    <input type="hidden" name="from_id" value="<?= $template_data['from_id'] ?>">
                                    <input type="hidden" name="template_id" value="<?= $_POST['id'] ?>">
                                    <input type="hidden" name="subject" value="<?= $template_data['subject'] ?>">
                                    <input type="hidden" name="email" value="<?= $from_data['email'] ?>">
                                    <input type="hidden" name="from_name" value="<?= $from_data['from_name'] ?>">
                                    <input type="hidden" name="content" value="<?= $template_data['content'] ?>">
                                    <button type="submit" class="btn btn-primary" disabled>予約する</button>
                                    </form>
                                    <button type="button" class="btn btn-secondary" onclick="inition()"
                                        data-bs-dismiss="modal">閉じる</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="timepicker()" class="btn btn-primary mx-2" data-bs-toggle="modal"
                        data-bs-target="#detail">
                        予約する</button>
                </td>
                <td><button class="btn btn-secondary" onclick="goBack('<?= $_POST['id'] ?>')">戻る</button></td>
            </table>
        </main>
    </div>
    <script src="./assets/js/client/timepicker.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"
        integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous">
        </script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous">
        </script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous">
        </script>

    <script type="text/javascript">
        function goBack(template) {
            location.href = "./email_distribution.php?template=" + template;
        }
    </script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
        crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI"
        crossorigin="anonymous"></script>
</body>

</html>