<?php
session_start();

if (isset($_SESSION['csv_data'])) {
    unset($_SESSION["csv_data"]);
}

require ('common.php');

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$directory = dirname($_SERVER['PHP_SELF']);
$current_directory_url = $protocol . "://" . $host . $directory;

// $from = $_POST['from'];

if (isset($_SESSION['csv_data_form'])) {
    $csv_data = $_SESSION['csv_data_form'];
} else {
    echo "<script>alert('送信先をアップロードしてください。');location.href = './form_submission.php';</script>";
}

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $stmt = $db->prepare("SELECT * FROM templates WHERE id=?");
    $stmt->execute([$_POST['id']]);
    $template_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // $stmt = $db->prepare("SELECT from_name FROM froms WHERE id=?");
    // $stmt->execute([$from]);
    // $from_name = $stmt->fetchColumn();

    // サンプルで1行目だけ差し込みコードの置換
    $user_id = substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, 4);
    //__code
    $content_ = $template_data['content'];
    if (isset($csv_data[0][2])) {
        $content_ = str_replace('{company}', $csv_data[0][2], $content_);
    }
    if (isset($csv_data[0][3])) {
        $content_ = str_replace('{lastname}', $csv_data[0][3], $content_);
    }
    if (isset($csv_data[0][4])) {
        $content_ = str_replace('{firstname}', $csv_data[0][4], $content_);
    }
    $content_ = str_replace('{fromname}', '', $content_);
   
    //__pdf
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

    $sample_content = nl2br($content_);

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
            顧客ID数 <?= count($csv_data) ?>件
            <br>
            <br>
            <h5>タイトル</h5>
            <?= $template_data['subject'] ?>
            <br><br>
            <h5>本文</h5>
            <?= $sample_content ?>
            <br><br>
            <br>
            <table>
                <td>
                    <form action="back/csv_form_submission.php" method="post">
                        <input type="hidden" name="template_id" value="<?= $template_data['id'] ?>">
                        <!-- <input type="hidden" name="from" value="<?= $from ?>"> -->
                <td><button class="btn btn-secondary">登録</button></td>
                </form>
                </td>
                <td><button class="btn btn-secondary" onclick="goBack(<?= $_POST['id'] ?>)">戻る</button></td>
            </table>
        </main>
    </div>


    <script>
        function goBack(template) {
            location.href = "./form_submission.php?template=" + template;
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