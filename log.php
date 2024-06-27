<?php
session_start();
require ('./common.php');

$per_page = 10; // 1ページに表示するログの数
$current_page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? $_GET['page'] : 1;
$sql1 = "";
if (isset($_SESSION['csv_data'])) {
    $csv_data = $_SESSION['csv_data'];
    unset($_SESSION["csv_data"]);
}
if (isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $sql = "SELECT * FROM logs WHERE cid=:client_id";
    if (!empty($_GET['date1']) && !empty($_GET['date2'])) {
        $sql .= " AND created_at BETWEEN '" . $_GET['date1'] . "' AND '" . $_GET['date2'] . "'";
    }
    if (!empty($_GET["token"])) {
        $sql .= " AND token = '" . $_GET['token'] . "'";
    }
    if (!empty($_GET['search'])) {
        $sql .= " AND (from_email LIKE '%" . $_GET['search'] . "%'";
        $sql .= " OR to_email LIKE '%" . $_GET['search'] . "%')";
    }

    $sql1 .= $sql . " ORDER BY id DESC LIMIT :offset, :per_page";
    $stmt = $db->prepare($sql1);
    $stmt->bindValue(':client_id', $client_id, PDO::PARAM_STR);
    $stmt->bindValue(':offset', ($current_page - 1) * $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $logs_data = $stmt->fetchAll();


    $stmt_count = $db->prepare($sql);
    $stmt_count->bindValue(':client_id', $client_id, PDO::PARAM_STR);
    $stmt_count->execute();
    $result = $stmt_count->fetchAll();

    $total_count = count($result);
    $total_pages = ceil($total_count / $per_page);


    $logs = [];
    foreach ($result as $key => $log) {
        $rows = [
            'No' => $log['id'],
            '配信日時' => substr($log['created_at'], 0, 16),
            '差出アドレス' => $log['from_email'],
            //    '件名' => $log['subject'],
            '宛先アドレス' => $log['to_email'],
            'レスポンス内容' => $log['status']
        ];
        array_push($logs, $rows);
    }
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
;
if (!empty($logs_data))
    $_SESSION['logs_data'] = $logs_data;
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
                <a class="tab_btn btn btn-primary" href="./distribution_list.php">配信一覧</a>
                <a class="tab_btn btn btn-primary" href="./schedules.php">予約一覧</a>
                <a class="tab_btn btn btn-secondary" href="./log.php">個別ログ</a>
                <a class="tab_btn btn btn-primary" href="./stop.php">配信停止</a>
                <a class="tab_btn btn btn-primary" href="./address_setting.php">アドレス設定</a>
            </div>
            <br>
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
            <table class="search-tools">
                <td><button class="btn btn-success mx-2" type="button" data-toggle="modal"
                        data-target="#searchModal">検索</button></td>
                <form action="back/csv_log.php" id="myForm" method="post">
                    <td><button class="btn btn-secondary" onclick="submitForm()" <?= in_array('CSV出力', $func_limit) ? 'disabled' : ''; ?>>CSV出力</button></td>
                </form>
            </table>
            <br>

            <?php
            $search_params = http_build_query([
                'token' => $_GET['token'] ?? null,
                'search' => $_GET['search'] ?? null,
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

            <table class="table">
                <tr>
                    <th>No.</th>
                    <th>配信日時</th>
                    <th>差出アドレス</th>
                    <th>宛先アドレス</th>
                    <th>レスポンス内容</th>
                </tr>
                <?php $total = count($result);
                $i = $total - ($current_page - 1) * 10;
                if (!empty($logs_data))
                    foreach ($logs_data as $key => $log): ?>
                        <tr>
                            <td><?= $i-- ?></td>
                            <td>
                                <?= substr($log['created_at'], 0, 16) ?>
                            </td>
                            <td>
                                <?= $log['from_email'] ?>
                            </td>
                            <td>
                                <?= $log['to_email'] ?>
                            </td>
                            <td>
                                <?= $log['status'] ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
            </table>
        </main>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"
        integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous">
        </script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous">
        </script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous">
        </script>
    <script>
        function submitForm() {
            // send_list_dataをJSON形式で取得

            var individual_logs = <?= json_encode($logs) ?>;
            // hidden inputを作成してフォームに追加
            var input = document.createElement("input");
            input.setAttribute("type", "hidden");
            input.setAttribute("name", "individual_logs");
            input.setAttribute("value", JSON.stringify(individual_logs));
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
                    location.href = "./log.php";
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