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
    $data = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT * FROM froms WHERE cid=?");
    $stmt->execute([$client_id]);
    $froms = $stmt->fetchAll();

    $emails = array();
    $new_froms = array();
    foreach ($data as $d) {
        $emails[] = $d['email'];
    }

    foreach ($froms as $f) {
        if (!in_array($f['email'], $emails)) {
            $new_froms[] = $f;
        }
    }

    $stmt = $db->prepare("SELECT * FROM tag_sample WHERE client_id=?");
    $stmt->execute([$client_id]);
    $tags = $stmt->fetchAll();

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
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
                    <h1>ユーザー管理</h1>
                </div>
                <?php require ('dropdown.php'); ?>
            </div>
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
                                <form action="./back/new_client_user.php" id="new_form" method="post">
                                    <div class="modal-body" id="modal_req_new">
                                        <table class="table">
                                            <tr>
                                                <td>姓</td>
                                                <td>
                                                    <span class="require-note">必須</span> <input type="last_name"
                                                        name="last_name" id="new_last_name" required>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>名</td>
                                                <td>
                                                    <input type="text" name="first_name" id="new_first_name"
                                                        style="margin-left: 35px;">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>ログインアドレス</td>
                                                <td><span class="require-note">必須</span> <input type="email"
                                                        name="email" id="new_email" required></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="modal-footer">
                                        <input type="hidden" name="cid" value="<?= $client_id; ?>">
                                        <button type="submit" class="btn btn-primary" id="block_btn">新規登録</button>
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">閉じる</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php
                    $current = count($data);
                    $max = $ClientData['max_user'];
                    $limit = $max - $current;
                    ?>
                    <?php if ($ClientData['max_cms_type'] == 1 && $limit <= 0): ?>
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
                        <div class="modal-header">
                            <h5 class="modal-title">詳細</h5>
                            <button type="button" id="close" class="btn-close close" data-bs-dismiss="modal"
                                aria-bs-label="Close">
                            </button>
                        </div>
                        <!-- modal-body -->
                        <form action="./back/ch_address.php" method="post">
                            <div class="modal-body" id="modal_req">
                                <table class="table">
                                    <tr>
                                        <td>姓</td>
                                        <td>
                                            <input type="text" class="enable_change" id="last_name" name="last_name"
                                                style="margin-left: 35px;" disabled>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>名</td>
                                        <td>
                                            <input type="text" class="enable_change" id="first_name" name="first_name"
                                                style="margin-left: 35px;" disabled>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>ログインアドレス</td>
                                        <td>
                                            <span class="require-note">必須</span>
                                            <input type="text" class="enable_change" id="email" name="email" required
                                                disabled>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>ログインパスワード</td>
                                        <td>
                                            <input type="password" value="11111111" disabled>
                                            <button class="btn btn-primary" type="button" id="openChPassModal"
                                                data-bs-dismiss="modal" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="modal-footer d-flex justify-content-between" id="detail_btns">
                                <input type="hidden" name="id" class="pass2Id">
                                <button type="button" id="delAddressBtn" class="btn btn-danger" <?= in_array('削除', $func_limit) ? 'disabled' : ''; ?>>削除</button>
                                <div>
                                    <button type="button" class="btn btn-warning me-2" id="openRoleModal"
                                        data-bs-dismiss="modal">権限管理</button>
                                    <button type="button" id="editAddressBtn" class="btn btn-dark" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                                    <button type="submit" id="saveAddressBtn" class="btn btn-success">保存</button>
                                    <button type="button" class="btn btn-secondary ms-2 close" onclick="cancelEditing()"
                                        data-bs-dismiss="modal">閉じる</button>
                                </div>
                            </div>
                        </form>
                        <form action="./back/del_address.php" id="delForm">
                            <input type="hidden" name="id" class="pass2Id">
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="ch_pass" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">パスワード変更</h5>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <!-- modal-body -->
                        <form action="./back/ch_c_pass.php" method="post">
                            <div class="modal-body" id="modal_req">
                                <table>
                                    <tr>
                                        <td>新しいバスワード</td>
                                        <td>
                                            <input type="password" name="password" id="password" placeholder="新しいバスワード">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>新しいパスワード（確認）</td>
                                        <td>
                                            <input type="password" name="pass_confirm" id="pass_confirm"
                                                placeholder="新しいパスワード（確認）">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="modal-footer">
                                <input type="hidden" name="cid" value='<?= $client_id; ?>'>
                                <input type="hidden" name="id" class="pass2Id" value=''>
                                <button type="submit" id="chPassBtn" class="btn btn-success" disabled>保存</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="ch_role" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">権限管理</h5>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <!-- modal-body -->
                        <form action="./back/ch_role.php" method="post">
                            <div class="modal-body d-flex justify-content-center" id="modal_req">
                                <table>
                                    <tr>
                                        <td style="min-width: 100px;">追加アドレス</td>
                                        <td id="new_froms" class="d-none pb-2">
                                            <input type="hidden" name="add_froms[]" id="basic_from">
                                            <?php foreach ($new_froms as $nf): ?>
                                                <input type="checkbox" name="add_froms[]" id="nf_<?= $nf['id']; ?>"
                                                    class="add_froms" value="<?= $nf['id']; ?>">
                                                <label for="nf_<?= $nf['id']; ?>"><?= $nf['email']; ?></label>
                                            <?php endforeach ?>
                                            <?= (count($new_froms) == 0) ? '<p class="text-danger mb-0">新しく追加されたアドレスはありません。</p>' : ''; ?>
                                        </td>
                                        <td id="old_froms" class="pb-2">
                                            <select name="send_email" id="">
                                                <option id="default_email" value="">追加アドレス</option>
                                                <?php foreach ($new_froms as $f): ?>
                                                    <option class="from_select" value="<?= $f['id']; ?>"><?= $f['email']; ?>
                                                    </option>
                                                <?php endforeach ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr style="border-top: 1px #c5cacf solid;">
                                        <td class="pt-2">タグ制限</td>
                                        <td class="pt-2" id="tag_container">
                                            <div id="new_tags" class="d-none">
                                                <input type="checkbox" class="tag_select" name="select_tag[]" id="tag_0"
                                                    value="未選択">
                                                <label for="tag_0">未選択</label>
                                                <?php foreach ($tags as $tag): ?>
                                                    <input type="checkbox" class="tag_select" name="select_tag[]"
                                                        id="tag_<?= $tag['id']; ?>" value="<?= $tag['tag_name']; ?>">
                                                    <label for="tag_<?= $tag['id']; ?>"><?= $tag['tag_name']; ?></label>
                                                <?php endforeach ?>
                                            </div>
                                            <p id="old_tags" class="mb-0"></p>
                                            <?= (count($tags) == 0) ? '<p class="text-danger mb-0">タグはありません。</p>' : ''; ?>
                                        </td>
                                        <td id="tag_no">
                                            制限なし
                                        </td>
                                    </tr>
                                    <tr style="border-top: 1px #c5cacf solid;">
                                        <td class="pt-2">禁止機能</td>
                                        <td class="pt-2" id="green">
                                            <p class="detail_func">
                                                <?php
                                                $functions = ['削除', '変更', 'CSV出力', 'ユーザー管理', '各種設定', 'アフィリエイト', '契約情報'];
                                                foreach ($functions as $label) {
                                                    echo "<span class='text-success ms-2'>◯</span>$label ";
                                                }
                                                ?>
                                            </p>
                                            <p class="edit_func">
                                                <?php
                                                $functions = ['削除', '変更', 'CSV出力', 'ユーザー管理', '各種設定', 'アフィリエイト', '契約情報'];
                                                foreach ($functions as $label) {
                                                    echo "<input type='checkbox' disabled>$label ";
                                                }
                                                ?>
                                            </p>
                                        </td>
                                        <td class="pt-2" id="red">
                                            <p class="detail_func">
                                                <?php
                                                $functions = ['削除', '変更', 'CSV出力', 'ユーザー管理', '各種設定', 'アフィリエイト', '契約情報'];
                                                foreach ($functions as $label) {
                                                    echo "<span class='text-danger ms-2'>×</span>$label ";
                                                }
                                                ?>
                                            </p>
                                            <p class="edit_func">
                                                <?php
                                                $functions = ['削除', '変更', 'CSV出力', 'ユーザー管理', '各種設定', 'アフィリエイト', '契約情報'];
                                                foreach ($functions as $label) {
                                                    echo "<input type='checkbox' checked disabled>$label ";
                                                }
                                                ?>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="modal-footer">
                                <input type="hidden" name="id" class="pass2Id" value=''>
                                <button type="button" id="editBtn" class="btn btn-dark" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                                <button type="submit" id="saveBtn" class="btn btn-success">保存</button>
                                <button type="button" class="btn btn-secondary close"
                                    data-bs-dismiss="modal">閉じる</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <table class="table" id="users_table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>ユーザー名</th>
                        <th>ログインアドレス</th>
                        <th>区分</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($data, true) as $key => $row): ?>
                        <tr>
                            <td>
                                <?= $key + 1 ?>
                            </td>
                            <td>
                                <?= $row['last_name'] . $row['first_name'] ?>
                            </td>
                            <td>
                                <?= $row['email'] ?>
                            </td>
                            <td>
                                <?= $row['role'] == 1 ? '契約ユーザー' : '標準ユーザー' ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#detail"
                                    onClick="handleClick(`<?= $row['id']; ?>`,`<?= $row['last_name']; ?>`,`<?= $row['first_name']; ?>`,`<?= $row['email']; ?>`,`<?= $row['role']; ?>`,`<?= $row['password']; ?>`,`<?= $row['tags']; ?>`,`<?= $row['send_email']; ?>`,`<?= $row['from_emails'] ?? ''; ?>`,`<?= explode(',', $row['from_emails'])[0]; ?>`);">
                                    詳細</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
    </div>
    <script>
        function handleClick(id, last_name, first_name, email, role, password, tags, send_email, froms, basic_from) {
            $(".pass2Id").val(id);
            $("#basic_from").val(basic_from);

            $("#email").val(email);
            $("#last_name").val(last_name);
            $("#first_name").val(first_name);

            $("#from_email").text(email);
            $("#from_name").text(last_name);

            $("#role").val(role);

            $("#old_tags").text(tags);
            var tagArray = tags.split(',');
            $('.tag_select').each(function () {
                var labelText = $(this).next('label').text();
                if (tagArray.includes(labelText)) {
                    $(this).prop('checked', true);
                } else {
                    $(this).prop('checked', false);
                }
            });

            if (role == 1) {
                $('#tag_container').hide();
                $('#delAddressBtn').hide();
                $('#tag_no').show();
                $('#detail_btns').removeClass('justify-content-between');
                $('#green').show();
                $('#red').hide();
            } else {
                $('#tag_container').show();
                $('#delAddressBtn').show();
                $('#tag_no').hide();
                $('#detail_btns').addClass('justify-content-between');
                $('#green').hide();
                $('#red').show();
            }

            $('#default_email').prop('selected', true);
            var fromArray = froms.split(',');
            $('.from_select').each(function () {
                var fromId = $(this).val();
                if ($.inArray(fromId, fromArray) !== -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
            $('.add_froms').each(function () {
                var addId = $(this).val();
                if ($.inArray(addId, fromArray) !== -1) {
                    $(this).prop('checked', true)
                } else {
                    $(this).prop('checked', false)
                }
            })

            $('.detail_func').show();
            $('.edit_func').hide();
        }

        $(document).ready(function () {
            $('#saveBtn').hide();
            $('#saveAddressBtn').hide();
            $('.close').click(function () {
                $('#saveBtn').hide();
                $('#editBtn').show();
                $('#saveAddressBtn').hide();
                $('#editAddressBtn').show();
                $('#new_tags').addClass('d-none');
                $('#old_tags').removeClass('d-none');
                $('#new_froms').addClass('d-none');
                $('#old_froms').removeClass('d-none');
            });

            $('#editBtn').click(function () {
                $('#saveBtn').show();
                $('#editBtn').hide();
                $('#old_tags').addClass('d-none');
                $('#new_tags').removeClass('d-none');
                $('#old_froms').addClass('d-none');
                $('#new_froms').removeClass('d-none');
                $('#role').prop('disabled', false);
                $('.detail_func').hide();
                $('.edit_func').show();
            });

            $('#editAddressBtn').click(function () {
                $('#saveAddressBtn').show();
                $('#editAddressBtn').hide();
                $('.enable_change').prop('disabled', false);
            });

            $('#delAddressBtn').click(function () {
                $('#delForm').submit();
            });

            $('#pass_confirm').on('input', function () {
                var password = $('#password').val();
                var passConfirm = $(this);
                var btn = $('#chPassBtn');

                if (passConfirm.val() != password) {
                    passConfirm.css('border-color', 'red');
                    btn.prop('disabled', true);
                } else {
                    passConfirm.css('border-color', 'white');
                    btn.prop('disabled', false);
                }
            });

            $('#openChPassModal').click(function () {
                $('#detail').modal('hide');
                $('#ch_pass').modal('show');
            });

            $('#openTransModal').click(function () {
                $('#detail').modal('hide');
                $('#transSetting').modal('show');
            });

            $('#openRoleModal').click(function () {
                $('#detail').modal('hide');
                $('#ch_role').modal('show');
            });

            $('#openAddModal').click(function () {
                $('#ch_role').modal('hide');
                $('#add_address').modal('show');
            });

            $('#users_table').DataTable({
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