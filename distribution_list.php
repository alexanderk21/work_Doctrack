<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (isset($_SESSION['csv_data'])) {
    unset($_SESSION["csv_data"]);
}
if (isset($_SESSION['csv_data_form'])) {
    unset($_SESSION["csv_data_form"]);
}

require ('./common.php');

$cnt = 0;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1; // 現在のページ番号

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $sql = "SELECT DATE_FORMAT(`created_at`, '%Y-%m') as `grouping_logs`,
    COUNT(`created_at`) as count
    FROM `logs`
    WHERE cid=?
    GROUP BY `grouping_logs`";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $grouping_logs = $stmt->fetchAll();

    $sql = "SELECT * FROM logs WHERE cid = :client_id";
    if (!empty($_GET['search'])) {
        $sql .= " AND from_email LIKE :search";
    }
    if (!empty($_GET['subject'])) {
        $sql .= " AND subject LIKE :subject";
    }
    if (!empty($_GET['date1']) && !empty($_GET['date2'])) {
        $sql .= " AND created_at BETWEEN :date1 AND :date2";
    }
    if (!empty($_GET['token'])) {
        $sql .= " AND token = :token";
    }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':client_id', $client_id, PDO::PARAM_STR);
    if (!empty($_GET['search'])) {
        $stmt->bindValue(':search', '%' . $_GET['search'] . '%', PDO::PARAM_STR);
    }
    if (!empty($_GET['subject'])) {
        $stmt->bindValue(':subject', '%' . $_GET['subject'] . '%', PDO::PARAM_STR);
    }
    if (!empty($_GET['date1']) && !empty($_GET['date2'])) {
        $stmt->bindValue(':date1', $_GET['date1'], PDO::PARAM_STR);
        $stmt->bindValue(':date2', $_GET['date2'], PDO::PARAM_STR);
    }
    if (!empty($_GET['token'])) {
        $stmt->bindValue(':token', $_GET['token'], PDO::PARAM_STR);
    }

    $stmt->execute();
    $logs_data_all = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT * FROM schedules WHERE cid = ? AND process = 1");
    $stmt->execute([$client_id]);
    $process_data = $stmt->fetchAll();

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}

$result = [];
foreach ($logs_data_all as $lda) {
    if (!isset($result[$lda['token']])) {
        $result[$lda['token']] = [];
    }
    $result[$lda['token']][] = $lda;
}

$result = array_merge($process_data, $result);

$total_count = count($result);
$total_pages = ceil($total_count / 10);

$show_data = array_slice($result, ($page - 1) * 10, 10);
$send_list_data = [];
?>

<?php require ('header.php'); ?>

<body>

    <div class="wrapper">
        <?php require ('menu.php'); ?>
        <main>
            <div class="d-flex justify-content-between">
                <div class="header-with-help">
                    <h1>メール配信</h1>
                </div>
                <?php require ('dropdown.php'); ?>
            </div>
            <div>
                <a class="tab_btn btn btn-primary" href="./email_distribution.php">配信作成</a>
                <a class="tab_btn btn btn-secondary" href="./distribution_list.php">配信一覧</a>
                <a class="tab_btn btn btn-primary" href="./schedules.php">予約一覧</a>
                <a class="tab_btn btn btn-primary" href="./log.php">個別ログ</a>
                <a class="tab_btn btn btn-primary" href="./stop.php">配信停止</a>
                <a class="tab_btn btn btn-primary" href="./address_setting.php">アドレス設定</a>
            </div>
            <br>
            <table class="search-tools">
                <td><button class="btn btn-success mx-2" type="button" data-toggle="modal"
                        data-target="#searchModal">検索</button></td>
                <td><button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#total">集
                        計</button></td>
                <form action="back/csv_log.php" id="myForm" method="post">
                    <td><button class="btn btn-secondary" onclick="submitForm()" <?= in_array('CSV出力', $func_limit) ? 'disabled' : ''; ?>>CSV出力</button></td>
                </form>
            </table>
            <div class="modal fade" id="total" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                aria-labelledby="staticBackdropLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content" id="modal_content">
                        <div class="modal-header">
                            <h5 class="modal-title">集計</h5>
                            <button type="button" id="close" class="btn-close" data-bs-dismiss="modal"
                                aria-bs-label="Close">

                            </button>
                        </div>
                        <!-- modal-body -->
                        <div class="modal-body" id="modal_req">
                            <table class="table account-info">
                                <tr>
                                    <th>月</th>
                                    <th>配信数</th>
                                </tr>
                                <?php foreach (array_reverse($grouping_logs, true) as $key => $log):
                                    $cnt += $log['count']; ?>
                                    <tr>
                                        <td>
                                            <?= $log['grouping_logs'] ?>
                                        </td>
                                        <td>
                                            <?= $log['count'] ?>件
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td>
                                        <p>合計</p>
                                    </td>
                                    <td>
                                        <?= $cnt ?>件
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
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
                                        <td>メールアドレス</td>
                                        <td class="p-1">
                                            <input type="text" name="search" id="search-input"
                                                placeholder="検索するメールアドレスを入力" value="<?php if (!empty($_GET['search']))
                                                    echo $_GET['search']; ?>">
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
            <br>
            <?php
            $search_params = http_build_query([
                'search' => $_GET['search'] ?? null,
                'date1' => $_GET['date1'] ?? null,
                'date2' => $_GET['date2'] ?? null
            ]);
            $pagination_links = '';
            $maxVisable = 5;
            $start_page = (ceil($page / $maxVisable) - 1) * $maxVisable + 1;
            $end_page = min($start_page + $maxVisable - 1, $total_pages);
            $next = $end_page < $total_pages ? $end_page + 1 : $total_pages;
            $prev = $page <= 5 ? max($page - 1, 1) : $start_page - 5;
            $prev_disabled = $page == 1 ? "page-link inactive-link" : "page-link";
            $next_disabled = ($page == $total_pages || $total_pages == 0) ? "page-link inactive-link" : "page-link";

            $pagination_links .= '<li class="page-item"><a class="' . $prev_disabled . '" href="?' . $search_params . '&page=' . $prev . '">«前</a></li>';
            for ($i = $start_page; $i <= $end_page; $i++) {
                $active_class = ($i == $page) ? 'active' : '';
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

            <table class="table">
                <tr>
                    <th>配信日時</th>
                    <th>差出アドレス</th>
                    <th>テンプレート名</th>
                    <th>宛先数</th>
                    <th>アクセス数</th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <?php
                foreach ($show_data as $key => $log):
                    if (isset($log['process'])): 
                        $custom_num = count(unserialize($log['to_data']));
                    ?>
                        <tr>
                            <td>
                                <?= substr($log['scheduled_datetime'], 0, 16); ?>
                            </td>
                            <td>
                                <?= $log['from_email']; ?>
                            </td>
                            <td>
                                <?= $log['subject']; ?>
                            </td>
                            <td>
                                <?= $custom_num; ?>
                            </td>
                            <td>
                                0
                            </td>
                            <td class="text-center" colspan="4">
                                配信処理中
                            </td>
                        </tr>
                    <?php else:
                        $access_count = 0;
                        $user_pdf_access_count = 0;
                        $user_redirects_access_count = 0;

                        $pdf_ids_str = "";
                        $redirect_ids_str = "";
                        $filter_customers = [];
                        foreach ($log as $l) {
                            $filter_customers[] = $l['user_id'];
                            // 正規表現を使ってcontentからtokenを取得する
                            $token_regex = '/\?t=([A-Za-z0-9_-]+)\/([A-Za-z0-9_-]+)/';
                            $token = $l['token'];
                            preg_match_all($token_regex, $l['content'], $matches);

                            foreach ($matches[0] as $k => $v) {
                                if (isset($matches[0][$k - 1]) && $v == $matches[0][$k - 1]) {
                                    unset($matches[0][$k - 1]);
                                    unset($matches[1][$k - 1]);
                                    unset($matches[2][$k - 1]);
                                }
                            }

                            foreach ($matches[2] as $key => $used_user_id) {
                                $query = "SELECT * FROM users WHERE user_id = :user_id";
                                $stmt = $db->prepare($query);
                                $stmt->execute(array(':user_id' => $used_user_id));
                                $ex_user = $stmt->fetchAll();
                                if (!empty($ex_user)) {
                                    $used_tracking_id = $matches[1][$key];
                                    // PDFのバージョンを取得する
                                    $pdf_version = null;
                                    $query = "SELECT * FROM pdf_versions WHERE pdf_id = :pdf_id";
                                    $stmt = $db->prepare($query);
                                    $stmt->execute(array(':pdf_id' => $used_tracking_id));
                                    $pdf_versions = $stmt->fetchAll();
                                    // PDFバージョンが見つかった場合
                                    if (!empty($pdf_versions)) {
                                        // ユーザーがPDFにアクセスした回数を取得する
                                        $query = "SELECT access_id FROM user_pdf_access WHERE pdf_version_id = :pdf_version_id AND user_id = :user_id AND token = :token AND page = 1";
                                        $stmt = $db->prepare($query);
                                        foreach ($pdf_versions as $pdf_version) {
                                            $stmt->execute(array(':pdf_version_id' => $pdf_version['pdf_version_id'], ':user_id' => $used_user_id, ':token' => $token));
                                            $result = $stmt->fetchAll();

                                            if ($result !== false) {
                                                foreach ($result as $r) {
                                                    $pdf_ids_str .= "'" . $r['access_id'] . "',";
                                                    $user_pdf_access_count++;
                                                }
                                            }
                                        }
                                    }
                                    $redirects = null;
                                    $query = "SELECT * FROM redirects WHERE id = :id";
                                    $stmt = $db->prepare($query);
                                    $stmt->execute(array(':id' => $used_tracking_id));
                                    $redirects = $stmt->fetch();

                                    if (!empty($redirects)) {
                                        $query = "SELECT access_id FROM user_redirects_access WHERE redirect_id = :redirect_id AND token = :token AND user_id = :user_id";
                                        $stmt = $db->prepare($query);
                                        $stmt->execute(array(':redirect_id' => $used_tracking_id, ':user_id' => $used_user_id, ':token' => $token));
                                        $result = $stmt->fetchAll();
                                        if ($result !== false) {
                                            foreach ($result as $r) {
                                                $redirect_ids_str .= "'" . $r['access_id'] . "',";
                                                $user_redirects_access_count++;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        $access_count = $user_pdf_access_count + $user_redirects_access_count;

                        $stmt = $db->prepare("SELECT * FROM templates WHERE id=?");
                        $stmt->execute([$log[0]['template_id']]);
                        $template_data = $stmt->fetch(PDO::FETCH_ASSOC);

                        $stmt = $db->prepare("SELECT * FROM logs WHERE token=? AND cid = ?");
                        $stmt->execute([$log[0]['token'], $client_id]);
                        $token2log = $stmt->fetchAll();
                        $users_id = '';
                        foreach ($token2log as $lo) {
                            $users_id .= urldecode($lo['user_id']) . ',';
                        }

                        $row = [
                            'No' => $log[0]['id'],
                            '配信日時' => substr($log[0]['created_at'], 0, 16),
                            '差出アドレス' => $log[0]['from_email'],
                            'テンプレート名' => $log[0]['subject'],
                            '宛先数' => count($token2log),
                            'アクセス数' => $access_count
                        ];
                        array_push($send_list_data, $row);
                        ?>
                        <tr>
                            <td>
                                <?= substr($log[0]['created_at'], 0, 16) ?>
                            </td>
                            <td>
                                <?= $log[0]['from_email'] ?>
                            </td>
                            <td>
                                <?= $log[0]['subject'] ?>
                            </td>
                            <td>
                                <?= count($token2log) ?>
                            </td>
                            <td>
                                <?= $access_count ?>
                            </td>
                            <td>
                                <form action="log.php" method="get">
                                    <input type="hidden" name="token" value="<?= $log[0]['token'] ?>">
                                    <button class="btn btn-warning">個別ログ</button>
                                </form>
                            </td>
                            <td>
                                <a href="./clients_list.php?users_id=<?= $users_id; ?>" class="btn btn-secondary">一覧</a>
                            </td>
                            <td>
                                <div class="modal fade" id="detail_<?= $log[0]['token']; ?>" data-bs-backdrop="static"
                                    data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel"
                                    aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">宛先アドレス数　<span id="cnt"><?= count($token2log); ?></span>件
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                    aria-label="Close"></button>
                                            </div>
                                            <!-- modal-body -->
                                            <div class="modal-body" id="modal_req">
                                                <table class="table table-bordered">
                                                    <tr>
                                                        <td style="min-width: 120px;">テンプレート名</td>
                                                        <td>
                                                            <span id="subject"><?= $log[0]['subject']; ?></span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>本文</td>
                                                        <td>
                                                            <span id="content"><?= nl2br($template_data['content']); ?></span>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <br>
                                                <br>
                                            </div>
                                            <div class="modal-footer d-flex justify-content-between">
                                                <div>
                                                    <form action="back/del_distribution.php" method="post">
                                                        <input type="hidden" name="token" value="<?= $log[0]['token'] ?>">
                                                        <button class="btn btn-danger" <?= in_array('削除', $func_limit) ? 'disabled' : ''; ?>>削除</button>
                                                    </form>
                                                </div>
                                                <div class="d-flex">
                                                    <form action="back/dl_csv_sent.php" method="get">
                                                        <input type="hidden" name="token" value="<?= $log[0]['token'] ?>">
                                                        <input type="hidden" name="subject" value="<?= $log[0]['subject'] ?>">
                                                        <button class="btn btn-secondary" <?= in_array('CSV出力', $func_limit) ? 'disabled' : ''; ?>>CSV出力</button>
                                                    </form>
                                                    <button type="button" class="btn btn-secondary ms-1"
                                                        data-bs-dismiss="modal">閉じる</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#detail_<?= $log[0]['token']; ?>">
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
                                        echo '<input type="hidden" name="search_pdf_red_null" value="-1">';
                                    }
                                    ?>
                                    <button type="submit" class="btn btn-primary">解析</button>
                                </form>
                            </td>
                        </tr>
                        <?php
                    endif;
                endforeach; ?>

            </table>
        </main>
    </div>
    <script>
        function handleClick(subject, content, cnt) {
            document.getElementById("subject").innerText = subject;
            document.getElementById("content").innerText = content;
            document.getElementById("cnt").innerText = cnt;
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

        let search_input = document.getElementById('search-input');
        search_input.addEventListener('mouseenter', function () {

            search_input.addEventListener('paste', function (e) {
                e.preventDefault();
                const text = (e.clipboardData || window.clipboardData).getData('text').trim();
                search_input.value = text;
            });

            if (search_input.value != "") {
                document.querySelector('table.search-tools span.search-after').style.display = "block";
                let searchCancelButton = document.querySelector('table.search-tools span.search-after');
                searchCancelButton.addEventListener('click', function () {
                    location.href = "./distribution_list.php";
                });
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
        crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI"
        crossorigin="anonymous"></script>
</body>

</html>