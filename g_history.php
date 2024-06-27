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

    $sql = "SELECT * FROM g_users WHERE client_id=? AND users_ids != '' ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $g_his_data = $stmt->fetchAll();

    $sql = "SELECT * FROM clients WHERE cid=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $users = $stmt->fetchAll();


    $user_names = [];
    foreach ($users as $u) {
        $user_names[$u['id']] = $u['last_name'] . $u['first_name'];
    }

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


    table.dataTable thead .sorting_desc {
        background-image: url(./img/sort_desc.png) !important;
    }

    table.dataTable thead .sorting_asc {
        background-image: url(./img/sort_asc.png) !important;
    }
</style>

<body>
    <div class="wrapper">
        <?php require ('menu.php'); ?>
        <main>
            <div class="d-flex justify-content-between">
                <div class="header-with-help">
                    <h1>登録履歴</h1>
                </div>
                <?php require ('dropdown.php'); ?>
            </div>
            <table class="table" id="csv_his_table">
                <thead>
                    <tr>
                        <th>登録日時</th>
                        <th>Googleアカウント</th>
                        <th>実行ユーザー</th>
                        <th>登録件数</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>

                    <?php foreach ($g_his_data as $key => $row): ?>
                        <tr>
                            <td>
                                <?= date('Y-m-d H:i', strtotime($row['created_at'])) ?>
                            </td>
                            <td>
                                <?= $row['email'] ?>
                            </td>
                            <td>
                                <?= $user_names[$row['cuser_id']] ?>
                            </td>
                            <td>
                                <?= count(explode(',', $row['users_ids'])) ?>
                            </td>
                            <td>
                                <a href="./clients_list.php?users_id=<?= $row['users_ids']; ?>"
                                    class="btn btn-secondary">一覧</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
    </div>
    <script>
        $(document).ready(function () {
            $('#csv_his_table').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Japanese.json"
                },
                "lengthMenu": false,
                "searching": false,
                "order": [
                    [0, 'desc']
                ]
            });
            $('#csv_his_table_length').addClass('d-none');
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