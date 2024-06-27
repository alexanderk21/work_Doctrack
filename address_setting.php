<?php
session_start();

if (isset($_SESSION['csv_data']))
    unset($_SESSION["csv_data"]);
if (isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);
if (isset($_SESSION['email_data']))
    unset($_SESSION['email_data']);

require ('common.php');
try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare("SELECT * FROM clients WHERE cid=?");
    $stmt->execute([$client_id]);
    $clients = $stmt->fetchAll();

    $client2from = [];
    foreach ($clients as $c) {
        $client2from[$c['id']] = $c;
    }

    $stmt = $db->prepare("SELECT * FROM froms WHERE cid=?");
    $stmt->execute([$client_id]);
    $data = $stmt->fetchAll();
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}

$max = (int) $ClientData['max_froms'];
$current = count($data);
$limit = (int) $ClientData['max_froms'] - count($data);

?>

<?php require ('header.php'); ?>
<style>
    menu {
        height: 100vh;
    }

    .dataTables_length {
        display: none;
    }

    .dataTables_filter {
        display: none;
    }

    .dataTables_paginate {
        float: left !important;
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
                <a class="tab_btn btn btn-primary" href="./stop.php">配信停止</a>
                <a class="tab_btn btn btn-secondary" href="./address_setting.php">アドレス設定</a>
            </div>
            <br>
            <table>
                <td>
                    <?php require ('./common/restrict_modal.php'); ?>
                    <div class="modal fade" id="new" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                        aria-labelledby="staticBackdropLabel" aria-hidden="true">

                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">新規登録</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-bs-label="Close">

                                    </button>
                                </div>
                                <!-- modal-body -->
                                <div class="modal-body" id="modal_req_new">
                                    <form action="./back/new_from.php" id="new_form" method="post">
                                        <table class="table">
                                            <tr>
                                                <td>差出アドレス</td>
                                                <td>
                                                    <span class="require-note">必須</span> <input type="email"
                                                        name="email" id="new_email" required>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>差出人名</td>
                                                <td>
                                                    <span class="require-note">必須</span><input type="text"
                                                        name="from_name" id="new_from_name" required>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>SMTP HOST</td>
                                                <td><span class="require-note">必須</span><input type="text"
                                                        name="smtp_host" id="new_smtp_host" required></td>
                                            </tr>
                                            <tr>
                                                <td>PASS</td>
                                                <td><span class="require-note">必須</span><input type="password"
                                                        name="smtp_pw" id="new_smtp_pw" required></td>
                                            </tr>
                                            <tr>
                                                <td>署名</td>
                                                <td class="space-1">
                                                    <textarea name="signature" cols="30" rows="10"></textarea>
                                                </td>
                                            </tr>
                                        </table>
                                        <br>
                                        <br>
                                </div>
                                <div class="modal-footer">
                                    <input type="hidden" name="cid" value="<?= $client_id ?>">
                                    <button type="submit" class="btn btn-primary" id="block_btn">新規登録</button>
                                    </form>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="type" value="<?= $ClientData['max_froms_type'] ?>">
                    <input type="hidden" id="max_value" value="<?= $ClientData['max_froms'] ?>">
                    <input type="hidden" id="used_value" value="<?= count($data) ?>">
                    <?php if ($ClientData['max_froms_type'] == 1 && $limit <= 0): ?>
                        <?php require_once ('./limitModal.php'); ?>
                        <button type="button" class="btn btn-primary" id="modal_button" data-bs-toggle="modal"
                            data-bs-target="#limitModal">新規登録</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary" id="modal_button" data-bs-toggle="modal"
                            data-bs-target="#new">新規登録</button>
                    <?php endif; ?>
                </td>
            </table>
            <br>

            <div class="modal fade" id="detail" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                aria-labelledby="staticBackdropLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content" id="modal_content">
                        <form action="./back/ch_from.php" method="post">
                            <div class="modal-header">
                                <h5 class="modal-title">差出元</h5>
                                <button type="button" id="close" class="btn-close" data-bs-dismiss="modal"
                                    aria-bs-label="Close">

                                </button>
                            </div>
                            <!-- modal-body -->
                            <div class="modal-body" id="modal_req">
                                <table>
                                    <tr>
                                        <td>差出アドレス</td>
                                        <td>
                                            <span class="require-note">必須</span>
                                            <span id="show_email"></span>
                                            <input type="hidden" name="email" id="email" disabled>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>差出人</td>
                                        <td>
                                            <span class="require-note">必須</span>
                                            <input type="text" name="from_name" id="from_name" required disabled>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>SMTP HOST</td>
                                        <td>
                                            <span class="require-note">必須</span>
                                            <input type="text" name="smtp_host" id="smtp_host" required disabled>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>PASS</td>
                                        <td>
                                            <span class="require-note">必須</span>
                                            <input type="password" name="smtp_pw" id="smtp_pw" required disabled>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>署名</td>
                                        <td class="space-1">
                                            <textarea name="signature" id="signature" cols="30" rows="10"
                                                disabled></textarea>
                                        </td>
                                    </tr>
                                </table>
                                <br>
                                <br>
                            </div>
                            <div class="modal-footer">
                                <input type="hidden" name="cid" value="<?= $cid ?>">
                                <input type="hidden" name="from_id" id="from_id">
                                <button onclick="del()" id="delBtn" type="button" class="btn btn-danger me-2"
                                    <?= in_array('削除', $func_limit) ? 'disabled' : ''; ?>>削除</button>
                                <button type="button" id="edit_button" class="btn btn-dark" onclick="enableEditing()"
                                    <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                                <button type="submit" id="save_button" class="btn btn-success"
                                    style="display:none">保存</button>
                                <button type="button" class="btn btn-secondary ms-2" onclick="cancelEditing()"
                                    data-bs-dismiss="modal">閉じる</button>
                            </div>
                        </form>
                        <form action="./back/del_from.php" id="del_form" method="post">
                            <input type="hidden" name="id" id="del_id" value="">
                            <input type="hidden" name="cid" value="<?= $client_id ?>">
                        </form>
                    </div>
                </div>
            </div>
            <table class="table" id="address_table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>差出名</th>
                        <th>差出アドレス</th>
                        <th>区分</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($data, true) as $key => $row):
                        $dist = (isset($client2from[$row['cuser_id']]) && $row['email'] == $client2from[$row['cuser_id']]['email']) ? '標準ユーザー' : '追加アドレス';
                        if ($dist == '標準ユーザー' && $row['cid'] == $row['cuser_id']) {
                            $dist = '契約ユーザー';
                        } ?>
                        <tr>
                            <td>
                                <?= $key + 1 ?>
                            </td>
                            <td>
                                <?= $row['from_name'] ?>
                            </td>
                            <td>
                                <?= $row['email'] ?>
                                <?php if ($row['smtp_host'] == ''): ?>
                                    <span class="text-danger">未設定</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $dist; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#detail"
                                    onClick="handleClick(`<?= $row['email']; ?>`,`<?= $row['signature']; ?>`,`<?= $row['from_name']; ?>`,`<?= $row['id']; ?>`,`<?= $row['smtp_host']; ?>`,`<?= $row['smtp_pw']; ?>`, `<?= $dist; ?>`);">
                                    詳細</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
    </div>
    <!-- <script src="./assets/js/client/restrict.js"></script> -->
    <script>
        let inputs = document.querySelectorAll('#modal_req input, #modal_req textarea, #modal_req select');
        let editButton = document.getElementById('edit_button');
        let saveButton = document.getElementById('save_button');
        let closeBtn = document.getElementById('close');

        function enableEditing() {
            inputs.forEach(input => {
                if (input.value == '未設定') {
                    input.value = '';
                    input.classList.remove('text-danger')
                }
                input.disabled = false;
            });

            editButton.style.display = 'none';
            saveButton.style.display = 'inline-block';
        }
        function handleClick(email, signature, from_name, from_id, smtp_host, smtp_pw, dist) {
            document.getElementById("email").value = email;
            document.getElementById("show_email").innerText = email;
            document.getElementById("signature").value = signature;
            document.getElementById("from_id").value = from_id;
            document.getElementById("del_id").value = from_id;

            document.getElementById("from_name").value = from_name;
            document.getElementById("smtp_host").value = smtp_host;

            var nameElement = document.getElementById('from_name');
            if (from_name == '') {
                nameElement.value = '未設定';
                nameElement.classList.add('text-danger')
            } else {
                nameElement.value = from_name;
                nameElement.classList.remove('text-danger')
            }

            var hostElement = document.getElementById('smtp_host');
            if (smtp_host == '') {
                hostElement.value = '未設定';
                hostElement.classList.add('text-danger')
            } else {
                hostElement.value = smtp_host;
                hostElement.classList.remove('text-danger')
            }

            var passElement = document.getElementById('smtp_pw');
            if (smtp_pw == '') {
                passElement.type = 'text';
                passElement.classList.add('text-danger')
                passElement.value = '未設定';
            } else {
                passElement.type = 'password';
                passElement.classList.remove('text-danger')
                passElement.value = smtp_pw;
            }

            if (dist == 'ログインアドレス') {
                $('#email').prop('readonly', true)
                $('#delBtn').hide();
            } else {
                $('#email').prop('readonly', false)
                $('#delBtn').show();
            }
        }
        function del() {
            var select = confirm("データを削除してもよろしいですか？");
            let form = document.getElementById('del_form');
            if (select) {
                form.submit();
            } else {
                return;
            }
        }


        closeBtn.addEventListener('click', function () {
            inputs.forEach(input => {
                input.disabled = true;
            });
            saveButton.style.display = "none";
            editButton.style.display = "block";
        });

        function cancelEditing() {
            inputs.forEach(input => {
                input.disabled = true;
            });
            saveButton.style.display = "none";
            editButton.style.display = "block";
        }

        let buttonEl = document.getElementById("block_btn");
        buttonEl.addEventListener("click", function () {
            var limit = <?= $limit ?>;
            var max_froms_type = <?= $ClientData['max_froms_type'] ?>;
            if (limit == 0 && max_froms_type == 1) {
                alert("上限数が0なので新規登録はできません。");
            }
        })


    </script>
    <script>
        $(document).ready(function () {
            $('#address_table').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Japanese.json"
                },
                "dom": '<"top"p>rt<"bottom"i><"clear">'
            });
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