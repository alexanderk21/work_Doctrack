<?php
session_start();

if (isset($_SESSION['csv_data']))
    unset($_SESSION["csv_data"]);
if (isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);

require ('./common.php');

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $sql = "SELECT * FROM schedules WHERE cid=? AND process = 0";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $schedules_data = $stmt->fetchAll();


} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
;
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
                <a class="tab_btn btn btn-secondary" href="./schedules.php">予約一覧</a>
                <a class="tab_btn btn btn-primary" href="./log.php">個別ログ</a>
                <a class="tab_btn btn btn-primary" href="./stop.php">配信停止</a>
                <a class="tab_btn btn btn-primary" href="./address_setting.php">アドレス設定</a>
            </div>
            <br>
            <table class="table">
                <tr>
                    <th>予約日時</th>
                    <th>差出アドレス</th>
                    <th>テンプレート名</th>
                    <th>宛先数</th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <?php if (!empty($schedules_data))
                    foreach (array_reverse($schedules_data, true) as $key => $row):
                        $csv_data = unserialize($row['to_data']); ?>
                        <?php
                        $users_id = '';
                        foreach ($csv_data as $each) {
                            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND cid = ?");
                            $stmt->execute([$each[0], $client_id]);
                            $user = $stmt->fetch();
                            if ($users_id != '') {
                                $users_id .= ',';
                            }
                            $users_id .= $user['user_id'];
                        }
                        ?>
                        <tr>
                            <td>
                                <?= substr($row['scheduled_datetime'], 0, 16) ?>
                            </td>
                            <td>
                                <?= $row['from_email'] ?>
                            </td>
                            <td>
                                <?= $row['subject'] ?>
                            </td>
                            <td>
                                <?= count($csv_data) ?>
                            </td>
                            <td>
                                <a class="btn btn-warning" href="./clients_list.php?users_id=<?= $users_id; ?>">一覧</a>
                            </td>
                            <td>
                                <div class="modal fade" id="detail" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">宛先アドレス数　<span id="cnt"></span>件</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <!-- modal-body -->
                                            <div class="modal-body" id="modal_req">
                                                <table>
                                                    <tr>
                                                        <td>差出アドレス</td>
                                                        <td>
                                                            <span id="from_email"></span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>メール件名</td>
                                                        <td>
                                                            <span id="subject"></span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>メール本文</td>
                                                        <td>
                                                            <span id="content"></span>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <br>
                                                <br>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary"
                                                    data-dismiss="modal">閉じる</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#detail"
                                    onClick="handleClick(`<?= $row['from_email']; ?>`,`<?= $row['subject']; ?>`,`<?= $row['content']; ?>`,`<?= count($csv_data); ?>`);">
                                    詳細</button>
                            </td>
                            <td>
                                <form action="back/cancel_schedule.php" method="post">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button type="submit" class="btn btn-secondary" onclick="return cancel();">予約中止</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
            </table>
        </main>
    </div>


    <script>
        function handleClick(from_email, subject, content, cnt) {
            document.getElementById("from_email").innerText = from_email;
            document.getElementById("subject").innerText = subject;
            document.getElementById("content").innerText = content;
            document.getElementById("cnt").innerText = cnt;
        }

        function cancel() {
            var select = confirm("予約をキャンセルしますか？");
            return select;
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