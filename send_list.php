<?php
session_start();

if (isset($_SESSION['csv_data']))
    unset($_SESSION["csv_data"]);
if (isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);

require ('./common.php');

$per_page = 10;
$current_page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? $_GET['page'] : 1;

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $sql = "SELECT *
    FROM form_logs
    WHERE (token, id) IN (
        SELECT token, MAX(id) AS max_id
        FROM form_logs 
        WHERE cid = :client_id ";

    if (!empty($_GET['date1']) && !empty($_GET['date2'])) {
        $sql .= " AND created_at BETWEEN '" . $_GET['date1'] . "' AND '" . $_GET['date2'] . "'";
    }
    if (!empty($_GET['title'])) {
        $sql .= " AND subject LIKE '%" . $_GET['title'] . "%'";
    }
    $sql .= " GROUP BY token)";
    $sql_count = $sql . " ORDER BY id";
    $sql .= " ORDER BY id DESC LIMIT :offset, :per_page";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':client_id', $client_id, PDO::PARAM_STR);
    $stmt->bindValue(':offset', ($current_page - 1) * $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $logs_data = $stmt->fetchAll();

    $stmt = $db->prepare($sql_count);
    $stmt->bindValue(':client_id', $client_id, PDO::PARAM_STR);
    $stmt->execute();
    $total_items = $stmt->fetchAll();



    function grouping(array $array, string $focus_key): array
    {
        $g_array = [];
        foreach ($array as $arr) {
            $key = $arr[$focus_key];
            $g_array[$key][] = $arr;
        }
        return $g_array;
    }

    $grouped_array = grouping($logs_data, 'token');
    $total_array = grouping($total_items, 'token');

    $total_count = count($total_items);
    $total_pages = ceil($total_count / $per_page);

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}

if (!empty($logs_data))
    $_SESSION['logs_data'] = $logs_data;

$send_list_data = []; // 空の配列を作成

if (!empty($total_array))
    foreach ($total_array as $key => $log):
        $access_count = 0;
        $user_pdf_access_count = 0;
        $user_redirects_access_count = 0;

        $pdf_ids_str = "";
        $redirect_ids_str = "";

        foreach ($log as $l) {
            // 正規表現を使ってcontentからtokenを取得する
            $token_regex = '/\?token=([A-Za-z0-9_-]+)\/([A-Za-z0-9_-]+)\/([A-Za-z0-9_-]+)\/([A-Za-z0-9_-]+)/';
            preg_match_all($token_regex, $l['content'], $matches);

            foreach ($matches[4] as $key => $used_user_id) {
                $used_tracking_id = $matches[3][$key];

                // PDFのバージョンを取得する
                $pdf_version = null;
                $query = "SELECT * FROM pdf_versions WHERE pdf_id = :pdf_id";
                $stmt = $db->prepare($query);
                $stmt->execute(array(':pdf_id' => $used_tracking_id));
                $pdf_versions = $stmt->fetchAll();

                // PDFバージョンが見つかった場合
                if (!empty($pdf_versions)) {
                    $pdf_ids_str .= "'" . $used_tracking_id . "',";
                    // ユーザーがPDFにアクセスした回数を取得する
                    $query = "SELECT COUNT(*) AS access_count FROM user_pdf_access WHERE pdf_version_id = :pdf_version_id AND user_id = :user_id AND page = 0";
                    $stmt = $db->prepare($query);
                    foreach ($pdf_versions as $pdf_version) {
                        $stmt->execute(array(':pdf_version_id' => $pdf_version['pdf_version_id'], ':user_id' => $used_user_id));
                        $result = $stmt->fetch();
                        if ($result !== false) {
                            $user_pdf_access_count += $result['access_count'];
                        }
                    }
                }

                $redirects = null;
                $query = "SELECT * FROM redirects WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->execute(array(':id' => $used_tracking_id));
                $redirects = $stmt->fetch();

                if (!empty($redirects)) {
                    $redirect_ids_str .= "'" . $used_tracking_id . "',";
                    $query = "SELECT COUNT(*) AS access_count FROM user_redirects_access WHERE redirect_id = :redirect_id AND user_id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->execute(array(':redirect_id' => $used_tracking_id, ':user_id' => $used_user_id));
                    $result = $stmt->fetch();
                    if ($result !== false) {
                        $user_redirects_access_count = $result['access_count'];
                    }
                }
            }
        }

        // PDFのアクセス数とリダイレクトのアクセス数を合計する
        $access_count = $user_pdf_access_count + $user_redirects_access_count;
        // // 結果を表示する
        // echo "PDFのアクセス数: $user_pdf_access_count" . PHP_EOL;
        // echo "リダイレクトのアクセス数: $user_redirects_access_count" . PHP_EOL;
        // echo "総アクセス数: $access_count" . PHP_EOL;
        // echo "<br><br>";

        $stmt = $db->prepare("SELECT * FROM templates WHERE id=?");
        $stmt->execute([$log[0]['template_id']]);
        $template_data = $stmt->fetch(PDO::FETCH_ASSOC);

        //総客数の計算
        $sql = 'SELECT COUNT(*) as user_cnt FROM form_logs WHERE token = :token';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':token', $log[0]['token'], PDO::PARAM_STR);
        $stmt->execute();
        $user_cnt = $stmt->fetch(PDO::FETCH_ASSOC)['user_cnt'];

        //進捗の計算
        $sql = 'SELECT COUNT(*) as report_cnt FROM form_logs WHERE token = :token AND csv_date_registered IS NOT NULL';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':token', $log[0]['token'], PDO::PARAM_STR);
        $stmt->execute();
        $report_cnt = $stmt->fetch(PDO::FETCH_ASSOC)['report_cnt'];

        $progress = ($report_cnt / $user_cnt) * 100;

        // 表示する行を配列として作成して、$send_list_dataに追加する
        $row = [
            '作成日時' => substr($log[0]['created_at'], 0, 16),
            'テンプレート名' => $log[0]['subject'],
            '顧客数' => $user_cnt,
            'アクセス数' => $access_count
        ];
        array_push($send_list_data, $row);
    endforeach;
?>

<?php require ('header.php'); ?>

<body>

    <div class="wrapper">
        <?php require ('menu.php'); ?>
        <main>
            <div class="d-flex justify-content-between">
                <div class="header-with-help">
                    <h1>フォーム営業</h1>
                </div>
                <?php require ('dropdown.php'); ?>
            </div>
            <div>
                <a class="tab_btn btn btn-primary" href="./form_submission.php">営業文作成</a>
                <a class="tab_btn btn btn-secondary" href="./send_list.php">作成一覧</a>
            </div>
            <br>
            <table>
                <td><button class="btn btn-success mx-2" type="button" data-toggle="modal"
                        data-target="#searchModal">検索</button></td>
                <form id="myForm" action="back/csv_send_list.php" method="post">
                    <td><button type="button" class="btn btn-secondary" onclick="submitForm()" <?= in_array('CSV出力', $func_limit) ? 'disabled' : ''; ?>>CSV出力</button></td>
                </form>
            </table>
            <br>
            <div class="modal fade" id="detail" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                aria-labelledby="staticBackdropLabel" aria-hidden="true">

                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">件数<span id="cnt"></span>件</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <!-- modal-body -->
                        <div class="modal-body" id="modal_req">
                            <table class="table table-bordered">
                                <tr>
                                    <td style="min-width: 120px;">テンプレート名</td>
                                    <td>
                                        <span id="subject"></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>本文</td>
                                    <td>
                                        <span id="content"></span>
                                    </td>
                                </tr>
                            </table>
                            <br>
                            <br>
                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <div>
                                <form action="back/del_form.php" method="post">
                                    <input type="hidden" name="token" id="del_token" value="">
                                    <button class="btn btn-danger" <?= in_array('削除', $func_limit) ? 'disabled' : ''; ?>>削除</button>
                                </form>
                            </div>
                            <div class="d-flex">
                                <form action="back/dl_csv_form.php" method="get">
                                    <input type="hidden" name="token" id="csv_token" value="">
                                    <button class="btn btn-secondary me-2" <?= in_array('CSV出力', $func_limit) ? 'disabled' : ''; ?>>CSV出力</button>
                                </form>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="searchModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 600px;">
                    <div class="modal-content">
                        <form action="" method="get">
                            <div class="modal-header">
                                <h5 class="modal-title">検索</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <!-- modal-body -->
                            <div class="modal-body" id="">
                                <table>
                                    <tr style="width: 100%;">
                                        <td>期間</td>
                                        <td class="p-1">
                                            <input type="date" name="date1" value="<?php if (!empty($_GET['date1']))
                                                echo $_GET['date1']; ?>">
                                            ～
                                            <input type="date" name="date2" value="<?php if (!empty($_GET['date2']))
                                                echo $_GET['date2']; ?>">
                                        </td>
                                    </tr>
                                    <tr style="width: 100%;">
                                        <td>テンプレート名</td>
                                        <td class="p-1">
                                            <input class="w-100" type="text" name="title" id="search-input"
                                                value="<?= ($_GET['title']) ?? ''; ?>">
                                        </td>
                                    </tr>
                                </table>
                                <br>
                                <br>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-primary">検索</button>
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <table class="table">
                <?php
                $search_params = http_build_query([
                    'date1' => $_GET['date1'] ?? null,
                    'date2' => $_GET['date2'] ?? null
                ]);
                $pagination_links = '';
                $maxVisable = 5;
                $start_page = (ceil($current_page / $maxVisable) - 1) * $maxVisable + 1;
                $end_page = min($start_page + $maxVisable - 1, $total_pages);
                $next = $end_page < $total_pages ? $end_page + 1 : $total_pages;
                $prev = $current_page <= 5 ? max($current_page - 1, 1) : $start_page - 5;
                $prev_disabled = $current_page == 1 ? "page-link inactive-link" : "page-link";
                $next_disabled = ($current_page == $total_pages || $total_pages == 0) ? "page-link inactive-link" : "page-link";

                $pagination_links .= '<li class="page-item"><a class="' . $prev_disabled . '" href="?' . $search_params . '&page=' . $prev . '">«前</a></li>';
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $active_class = ($i == $current_page) ? 'active' : '';
                    $pagination_links .= '<li class="page-item ' . $active_class . '"><a class="page-link" href="?' . $search_params . '&page=' . $i . '">' . $i . '</a></li>';
                }
                $pagination_links .= '<li class="page-item"><a class="' . $next_disabled . '" href="?' . $search_params . '&page=' . $next . '"   >次»</a></li>';
                ?>

                <!-- ページネーションを表示 -->

                <div class="pagination">
                    <ul class="pagination">
                        <?= $pagination_links ?>
                    </ul>
                </div>
                <!-- ページネーションリンクを表示 -->

                <tr>
                    <th>作成日時</th>
                    <th>テンプレート名</th>
                    <th>顧客数</th>
                    <th>アクセス数</th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <?php

                if (!empty($grouped_array))
                    foreach ($grouped_array as $key => $log):
                        $access_count = 0;
                        $user_pdf_access_count = 0;
                        $user_redirects_access_count = 0;

                        $pdf_ids_str = "";
                        $redirect_ids_str = "";

                        foreach ($log as $l) {
                            // 正規表現を使ってcontentからtokenを取得する
                            $token_regex = '/\?token=([A-Za-z0-9_-]+)\/([A-Za-z0-9_-]+)\/([A-Za-z0-9_-]+)\/([A-Za-z0-9_-]+)/';
                            preg_match_all($token_regex, $l['content'], $matches);

                            $users_id = '';
                            foreach ($matches[4] as $key => $used_user_id) {

                                $used_tracking_id = $matches[3][$key];

                                // PDFのバージョンを取得する
                                $pdf_version = null;
                                $query = "SELECT * FROM pdf_versions WHERE pdf_id = :pdf_id";
                                $stmt = $db->prepare($query);
                                $stmt->execute(array(':pdf_id' => $used_tracking_id));
                                $pdf_versions = $stmt->fetchAll();

                                // PDFバージョンが見つかった場合
                                if (!empty($pdf_versions)) {
                                    $pdf_ids_str .= "'" . $used_tracking_id . "',";
                                    // ユーザーがPDFにアクセスした回数を取得する
                                    $query = "SELECT COUNT(*) AS access_count FROM user_pdf_access WHERE pdf_version_id = :pdf_version_id AND user_id = :user_id AND page = 0";
                                    $stmt = $db->prepare($query);
                                    foreach ($pdf_versions as $pdf_version) {
                                        $stmt->execute(array(':pdf_version_id' => $pdf_version['pdf_version_id'], ':user_id' => $used_user_id));
                                        $result = $stmt->fetch();
                                        if ($result !== false) {
                                            $user_pdf_access_count += $result['access_count'];
                                        }
                                    }
                                }

                                $redirects = null;
                                $query = "SELECT * FROM redirects WHERE id = :id";
                                $stmt = $db->prepare($query);
                                $stmt->execute(array(':id' => $used_tracking_id));
                                $redirects = $stmt->fetch();

                                if (!empty($redirects)) {
                                    $redirect_ids_str .= "'" . $used_tracking_id . "',";
                                    $query = "SELECT COUNT(*) AS access_count FROM user_redirects_access WHERE redirect_id = :redirect_id AND user_id = :user_id";
                                    $stmt = $db->prepare($query);
                                    $stmt->execute(array(':redirect_id' => $used_tracking_id, ':user_id' => $used_user_id));
                                    $result = $stmt->fetch();
                                    if ($result !== false) {
                                        $user_redirects_access_count = $result['access_count'];
                                    }
                                }
                            }
                        }

                        // PDFのアクセス数とリダイレクトのアクセス数を合計する
                        $access_count = $user_pdf_access_count + $user_redirects_access_count;

                        $stmt = $db->prepare("SELECT * FROM templates WHERE id=?");
                        $stmt->execute([$log[0]['template_id']]);
                        $template_data = $stmt->fetch(PDO::FETCH_ASSOC);

                        //総客数の計算
                        $sql = 'SELECT * FROM form_logs WHERE token = :token';
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam(':token', $log[0]['token'], PDO::PARAM_STR);
                        $stmt->execute();
                        $form_logs = $stmt->fetchAll();
                        foreach ($form_logs as $e) {
                            $users_id .= urldecode($e['user_id']) . ',';
                        }
                        $users_id = rtrim($users_id, ',');
                        $users_id_chunks = str_split($users_id, 200);

                        $user_cnt = count($form_logs);
                        // $user_cnt = $stmt->fetch(PDO::FETCH_ASSOC)['user_cnt'];
                
                        //進捗の計算
                        $sql = 'SELECT COUNT(*) as report_cnt FROM form_logs WHERE token = :token AND csv_date_registered IS NOT NULL';
                        $stmt = $db->prepare($sql);
                        $stmt->bindParam(':token', $log[0]['token'], PDO::PARAM_STR);
                        $stmt->execute();
                        $report_cnt = $stmt->fetch(PDO::FETCH_ASSOC)['report_cnt'];

                        $progress = ($report_cnt / $user_cnt) * 100;

                        // 表示する行を配列として作成して、$send_list_dataに追加する
                

                        ?>
                        <tr>
                            <td>
                                <?= substr($log[0]['created_at'], 0, 16) ?>
                            </td>
                            <td>
                                <?= $log[0]['subject'] ?>
                            </td>
                            <td>
                                <?= $user_cnt ?>
                            </td>
                            <td>
                                <?= $access_count ?>
                            </td>
                            <td>
                                <form action="./clients_list.php" method="post">
                                    <?php foreach ($users_id_chunks as $e): ?>
                                        <input type="hidden" name="users_id[]" value="<?= $e; ?>">
                                    <?php endforeach; ?>
                                    <button type="submit" class="btn btn-secondary">一覧</button>
                                </form>
                            </td>
                            <td>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#detail"
                                    onClick="handleClick(`<?= $template_data['subject']; ?>`,`<?= $log[0]['content']; ?>`,`<?= $user_cnt; ?>`,`<?= $log[0]['token']; ?>`);">
                                    詳細</button>
                            </td>
                            <td>
                                <form action="access_analysis.php" method="get">
                                    <?php
                                    if (!empty($redirect_ids_str)) {
                                        echo '<input type="hidden" name="search_redirect_id" value="' . $redirect_ids_str . '">';
                                    }
                                    if (!empty($pdf_ids_str)) {
                                        echo '<input type="hidden" name="search_pdf_id" value="' . $pdf_ids_str . '">';
                                    }
                                    if (empty($redirect_ids_str) && empty($pdf_ids_str)) {
                                        echo '<input type="hidden" name="search_pdf_red_null" value="1">';
                                    }

                                    date_default_timezone_set('Asia/Tokyo');
                                    $today = date('Y-m-d\TH:i:s');
                                    $log_created_at = date('Y-m-d\TH:i:s', strtotime($log[0]['created_at']));

                                    echo '<input type="hidden" name="date1" value="' . htmlspecialchars($log_created_at, ENT_QUOTES) . '">';
                                    echo '<input type="hidden" name="date2" value="' . htmlspecialchars($today, ENT_QUOTES) . '">';
                                    ?>
                                    <button type="submit" class="btn btn-primary">解析</button>
                                </form>
                            </td>
                        </tr>
                        <?php
                    endforeach;
                ?>
            </table>
        </main>
    </div>


    <script>
        function handleClick(subject, content, cnt, token) {
            document.getElementById("subject").innerText = subject;
            document.getElementById("content").innerText = content;
            document.getElementById("cnt").innerText = cnt;
            document.getElementById("csv_token").value = token;
            document.getElementById("del_token").value = token;
        }

        function submitForm() {
            // send_list_dataをJSON形式で取得
            var send_list_data = <?= json_encode($send_list_data) ?>;

            // hidden inputを作成してフォームに追加
            var input = document.createElement("input");
            input.setAttribute("type", "hidden");
            input.setAttribute("name", "send_list_data");
            input.setAttribute("value", JSON.stringify(send_list_data));
            document.getElementById("myForm").appendChild(input);

            // フォームを送信
            document.getElementById("myForm").submit();
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