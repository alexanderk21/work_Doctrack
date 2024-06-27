<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (isset($_SESSION['csv_data']))
    unset($_SESSION["csv_data"]);
if (isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);

require ('common.php');
try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    if (isset($_POST['lift_mail'])) {
        $stmt = $db->prepare("SELECT * FROM stops WHERE cid=? AND mail=?");
        $stmt->execute([$client_id, $_POST['lift_mail']]);
        $lift_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lift_data) {
            $stmt = $db->prepare("UPDATE stops SET division='停止解除' WHERE id=?");
            $stmt->execute([$lift_data['id']]);
        } else {
            echo "<script>
                    alert('メールアドレスが存在しません。');
                </script>";
        }
    }
    $sql = "SELECT * FROM stops WHERE cid=?";
    if (isset($_POST['search_mail'])) {
        $date1_value = $_POST['date1'] ?? '';
        $date2_value = $_POST['date2'] ?? '';
        $search_mail = $_POST['search_mail'] ?? '';
        $division = $_POST['division'] ?? '';
        if (!empty($_POST['date1']) && !empty($_POST['date2'])) {
            $sql .= " AND created_at BETWEEN '$date1_value' AND '$date2_value'";
        } else if (!empty($_POST['date1'])) {
            $sql .= " AND created_at >= '$date1_value' ";
        } else if (!empty($_POST['date2'])) {
            $sql .= " AND created_at <= '$date2_value' ";
        }

        if ($search_mail != "") {
            $sql .= " AND mail LIKE '%" . $search_mail . "%' ";
        }

        if ($division != "") {
            $sql .= " AND division = '$division' ";
        }
    }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $stop_data = $stmt->fetchAll();

    $index = count($stop_data);

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
?>

<?php require ('header.php'); ?>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.js"></script>
<style>
    .modal-body {
        height: auto;
        overflow-y: auto;
    }

    @media (min-width: 576px) {
        .modal-dialog {
            max-width: 600px;
            margin: 1.75rem auto;
        }
    }

    menu {
        height: 100vh;
    }

    .dataTables_length {
        display: none;
    }
</style>

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
                <a class="tab_btn btn btn-primary" href="./log.php">個別ログ</a>
                <a class="tab_btn btn btn-secondary" href="./stop.php">配信停止</a>
                <a class="tab_btn btn btn-primary" href="./address_setting.php">アドレス設定</a>
            </div>
            <br>
            <table>
                <td>
                    <button class="btn btn-primary me-2" id="modal_button" data-bs-toggle="modal"
                        data-bs-target="#csv_stop">CSV登録</button>
                    <div class="modal fade" id="csv_stop" data-bs-backdrop="static" data-bs-keyboard="false"
                        tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <div class="modal-content" id="modal_content">
                                <div class="modal-header">
                                    <h5 class="modal-title">CSV登録</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <!-- modal-body -->
                                <form enctype="multipart/form-data" action="./back/read_csv_stop.php" method="POST">
                                    <div class="modal-body" id="">
                                        <table class="table table-bordered">
                                            <tr>
                                                <td>
                                                    <input class="w-100" name="csvFile" id="csvFile" type="file"
                                                        required />
                                                    <input type="hidden" name="cid" value="<?= $client_id; ?>">
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-primary">新規登録</button>
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">閉じる</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <button class="btn btn-success me-2" id="modal_button" data-bs-toggle="modal"
                        data-bs-target="#search_modal">検索</button>
                    <div class="modal fade" id="search_modal" data-bs-backdrop="static" data-bs-keyboard="false"
                        tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <div class="modal-content" id="modal_content">
                                <div class="modal-header">
                                    <h5 class="modal-title">検索</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <!-- modal-body -->
                                <form action="" method="POST">
                                    <div class="modal-body" id="">
                                        <table class="table table-bordered">
                                            <tr>
                                                <td>期間</td>
                                                <td class="d-flex justify-content-between align-items-center">
                                                    <input class="w-2/5" type="date" name="date1" id="date1" />
                                                    ~
                                                    <input class="w-2/5" type="date" name="date2" id="date2" />
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>メールアドレス</td>
                                                <td>
                                                    <input class="w-100" name="search_mail" id="search_mail"
                                                        placeholder="検索するメールアドレスを入力してください。" />
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>登録区分</td>
                                                <td>
                                                    <select name="division" id="division">
                                                        <option value="">未選択</option>
                                                        <option value="配信失敗">配信失敗</option>
                                                        <option value="CSV登録">CSV登録</option>
                                                        <option value="停止解除">停止解除</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-primary">検索</button>
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">閉じる</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <button class="btn btn-warning me-2" id="modal_button" data-bs-toggle="modal"
                        data-bs-target="#lift_stop">停止解除</button>
                    <div class="modal fade" id="lift_stop" data-bs-backdrop="static" data-bs-keyboard="false"
                        tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <div class="modal-content" id="modal_content">
                                <div class="modal-header">
                                    <h5 class="modal-title">停止解除</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <!-- modal-body -->
                                <form action="" method="POST">
                                    <div class="modal-body" id="">
                                        <table class="table table-bordered">
                                            <tr>
                                                <td>メールアドレス</td>
                                                <td>
                                                    <input class="w-100" name="lift_mail" id="lift_mail" required />
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-primary">停止解除</button>
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">閉じる</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <button class="btn btn-dark" <?= in_array('CSV出力', $func_limit) ? 'disabled' : ''; ?>>CSV出力</button>
                </td>
            </table>
            <table class="table" id="stop_table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>登録日時</th>
                        <th>宛先アドレス</th>
                        <th>登録区分</th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>

                    <?php foreach ($stop_data as $key => $row): ?>
                        <tr>
                            <td>
                                <?= $index-- ?>
                            </td>
                            <td>
                                <?= date('Y-m-d H:i', strtotime($row['created_at'])) ?>
                            </td>
                            <td>
                                <?= $row['mail'] ?>
                            </td>
                            <td>
                                <?= $row['division'] ?>
                            </td>
                            <td></td>
                            <td></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
    </div>
    <script>
        $(document).ready(function () {
            $('#stop_table').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Japanese.json"
                },
                "lengthMenu": false,
                "searching": false
            });
            $('#stop_table_length').addClass('d-none');
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