<?php
session_start();

if (isset($_GET["user_id"])) {
    $user_id = $_GET["user_id"];
    $company = $_GET['company'];
    $name = $_GET['name'];

    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $url_dir = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $url_dir = str_replace(basename($url_dir), '', $url_dir);

    require ('common.php');

    try {
        $db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        if($ClientUser['role'] == 1){
            $tag_array = [];
            $stmt = $db->prepare("SELECT * FROM tag_sample WHERE client_id=?");
            $stmt->execute([$client_id]);
            $tag_data = $stmt->fetchAll();
            foreach ($tag_data as $t) {
                $tag_array[] = $t['tag_name'];
            }
            $tag_array = explode(',', $ClientUser['tags']);
        }else{
            $tag_array = explode(',', $ClientUser['tags']);
        }

        if (isset($_GET['tag']) && $_GET['tag'] != '') {
            $tag = $_GET['tag'];
            $tag_ids = [];
            $sql = "SELECT * FROM tags WHERE table_name = 'templates' AND tags LIKE '%$tag%'";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll();
            if (!empty($result)) {
                foreach ($result as $row) {
                    $tag_ids[] = $row['table_id'];
                }
            }
        }

        $sql = "SELECT * FROM templates WHERE cid = ?";
        if (!empty($tag_ids)) {
            $placeholders = implode(',', array_fill(0, count($tag_ids), '?'));
            $sql .= " AND id IN ($placeholders)";
        }

        if (isset($_GET['segment']) && $_GET['segment'] != '') {
            $segment = $_GET['segment'];
            $sql .= " AND division = '$segment'";
        }
        $stmt = $db->prepare($sql);

        $params = [$client_id];
        if (!empty($tag_ids)) {
            $params = array_merge($params, $tag_ids);
        }
        $stmt->execute($params);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (isset($tag) && empty($tag_ids)) {
            $templates = [];
        }

    } catch (PDOException $e) {
        echo '接続失敗' . $e->getMessage();
        exit();
    }

} else {
    exit();
}


?>
<?php require ('header.php'); ?>

<body>

    <div class="wrapper">
        <?php require ('menu.php'); ?>
        <main>
            <div class="modal fade" id="detail" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">詳細</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <!-- modal-body -->
                        <div class="modal-body" id="modal_req">
                            <table class="table table-bordered">
                                <tr>
                                    <td>テンプレート名</td>
                                    <td><span id="subject"></span></td>
                                </tr>
                                <tr>
                                    <td>本文</td>
                                    <td><span id="content"></span></td>
                                </tr>
                            </table>
                            <br>
                            <br>
                        </div>
                        <div class="modal-footer">
                            <input type="hidden" id="content2">
                            <button type="button" class="btn btn-primary" onclick="copyToClipboard(this)">コピー</button>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-between">
                <div class="d-flex align-items-center">
                    <h1>顧客 テンプレート</h1>
                    <p class="px-4">
                        <?= $company . $name ?>
                    </p>
                </div>
                <?php require ('dropdown.php'); ?>
            </div>
            <form action="" method="get" class="d-flex align-items-center">
                <input type="hidden" name="user_id" value="<?= $user_id; ?>">
                <input type="hidden" name="company" value="<?= $company; ?>">
                <input type="hidden" name="name" value="<?= $name; ?>">
                <label for="search_segment" class="me-3">区分</label>
                <select name="segment" id="">
                    <option value="">全選択</option>
                    <option value="メール配信" <?= (isset($segment) && $segment == 'メール配信') ? 'selected' : '' ?>>
                        メール配信</option>
                    <option value="フォーム営業" <?= (isset($segment) && $segment == 'フォーム営業') ? 'selected' : '' ?>>
                        フォーム営業</option>
                    <option value="SNS" <?= (isset($segment) && $segment == 'SNS') ? 'selected' : '' ?>>
                        SNS</option>
                </select>
                <label for="search_tag" class="mx-3">タグ検索</label>
                <select name="tag" id="">
                    <option value="">全選択</option>
                    <?php foreach ($tag_array as $each): ?>
                        <option value="<?= $each; ?>" <?= (isset($tag) && $tag == $each) ? 'selected' : '' ?>>
                            <?= $each; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-success mx-2">検索</button>
                <a href="<?= $_SERVER['HTTP_REFERER']; ?>" class="btn btn-secondary">戻る</a>
            </form>
            <br>
            <table class="table">
                <tr>
                    <th>No.</th>
                    <th>区分</th>
                    <!-- <th>差出アドレス</th> -->
                    <th>テンプレート名</th>
                    <th></th>
                </tr>
                <?php
                foreach ($templates as $key => $temp):

                    $stmt = $db->prepare("SELECT email, from_name FROM froms WHERE id = :from_id");
                    $stmt->bindParam(':from_id', $temp['from_id'], PDO::PARAM_INT);
                    $stmt->execute();
                    $froms = $stmt->fetch();
                    $from_email = $froms['email'];

                    // 差し込みコードを変換
                    $content_ = $temp['content'];

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

                    // 企業情報
                    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :user_id");
                    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
                    $stmt->execute();
                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (isset($company)) {
                        $replacement = !empty($company) ? $company : '';
                        $content_ = str_replace('{company}', $replacement, $content_);
                    }
                    if (isset($users[0]['surename'])) {
                        $content_ = str_replace('{lastname}', $users[0]['surename'], $content_);
                    }
                    if (isset($users[0]['name'])) {
                        $content_ = str_replace('{firstname}', $users[0]['name'], $content_);
                    }
                    if (isset($users[0]['name'])) {
                        $content_ = str_replace('{firstname}', $users[0]['name'], $content_);
                    }
                    if (isset($froms['from_name'])) {
                        $content_ = str_replace('{fromname}', $froms['from_name'], $content_);
                    }

                    $content = nl2br($content_);
                    ?>
                    <tr>
                        <td>
                            <?= $key + 1 ?>
                        </td>
                        <td>
                            <?= $temp['division'] ?>
                        </td>
                        <!-- <td>
                            <?= $from_email; ?>
                        </td> -->
                        <td>
                            <?= $temp['subject'] ?>
                        </td>
                        <td>


                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#detail"
                                onclick="handleClick(`<?= $temp['subject'] ?>`, `<?= $content ?>`)">
                                詳細</button>

                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </main>
    </div>

    <script>
        async function copyToClipboard(obj) {
            const element = obj.previousElementSibling;
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(element.value);
                copy_alert();
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = element.value;
                textarea.style.position = 'absolute';
                textarea.style.left = '-99999999px';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                try {
                    document.execCommand('copy')
                    copy_alert();
                } catch (err) {
                    alert('コピーに失敗しました。');
                } finally {
                    textarea.remove();
                }
            }
        }

        function copy_alert() {
            $(document).ready(function () {
                toastr.options.timeOut = 1500; // 1.5s
                toastr.success('コピーしました。');
            });
        }

        function handleClick(subject, content) {
            document.getElementById("subject").textContent = subject;
            document.getElementById("content").innerHTML = content;
            document.getElementById("content2").value = content.replace(/<br\s*\/?>/mg, "");
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.0.1/css/toastr.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.0.1/js/toastr.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"
        integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous">
        </script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous">
        </script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous">
        </script>

</body>

</html>