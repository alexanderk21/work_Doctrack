<?php
session_start();
require ('./common.php');
if (isset($_SESSION['csv_data']))
    unset($_SESSION["csv_data"]);
if (isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);
if (isset($_SESSION['email_data']))
    unset($_SESSION['email_data']);
try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $cfroms = explode(',', $ClientUser['from_emails']);
    $placeholders = implode(',', array_fill(0, count($cfroms), '?'));
    $sql = "SELECT * FROM froms WHERE id IN ($placeholders)";
    $stmt = $db->prepare($sql);
    $stmt->execute($cfroms);
    $addresses = $stmt->fetchAll();

    if (isset($_POST['send_email'])) {
        $stmt = $db->prepare('UPDATE clients SET send_email = :send_email WHERE id = :id');
        $stmt->bindValue(':id', $ClientUser['id']);
        $stmt->bindValue(':send_email', $_POST['send_email']);
        $stmt->execute();

        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }

    $func_limit = $ClientUser['func_limit'] ? explode(',', $ClientUser['func_limit']) : [];

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
?>
<?php require ('header.php'); ?>

<body>

    <div class="wrapper account-info">
        <?php require ('menu.php'); ?>
        <main>
            <div class="d-flex justify-content-between">
                <div class="account_title">
                    <h1>マイページ</h1>
                </div>
                <?php require ('dropdown.php'); ?>
            </div>

            <table class="table account-info mt-3">
                <tr>
                    <td>ユーザー名</td>
                    <td><?= $ClientUser['last_name'] . ' ' . $ClientUser['first_name'] ?></td>
                </tr>
                <tr>
                    <td>区分</td>
                    <td><?= $ClientUser['id'] == $client_id ? '契約ユーザー' : '標準ユーザー' ?></td>
                </tr>
                <tr>
                    <td>ログインアドレス</td>
                    <td><?= $ClientUser['email'] ?></td>
                </tr>
                <tr>
                    <td>ログインパスワード</td>
                    <td>
                        <input type="password" name="pass" id="pass" readonly value="11111111">
                        <div class="modal fade" id="new" tabindex="-1" role="dialog" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">パスワード変更</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <form action="./back/ch_c_pass.php" method="post">
                                        <div class="modal-body" id="modal_req">
                                            <table>
                                                <tr>
                                                    <td>新しいバスワード</td>
                                                    <td>
                                                        <input type="password" class="password" name="password"
                                                            id="new_pass" placeholder="新しいバスワード" required>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>新しいパスワード（確認）</td>
                                                    <td>
                                                        <input type="password" class="password" name="pass_confirm"
                                                            id="pass_confirm" placeholder="新しいパスワード（確認）" required>
                                                    </td>
                                                </tr>

                                            </table>
                                        </div>
                                        <div class="modal-footer">
                                            <input type="hidden" name="cid" value='<?= $ClientUser['id']; ?>'>
                                            <input type="hidden" name="pass2Id" value='<?= $client_id; ?>'>
                                            <button type="submit" id="chPassBtn" class="btn btn-success"
                                                disabled>保存</button>
                                            <button type="button" class="btn btn-secondary"
                                                data-dismiss="modal">閉じる</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-dark" data-toggle="modal" data-target="#new" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                    </td>
                </tr>
                <tr>
                    <td>追加アドレス</td>
                    <td>
                        <select name="" id="">
                            <?php
                            $send_email = [];
                            $basic_email = '';
                            foreach ($addresses as $address): ?>
                                <?php
                                if (!is_null($ClientUser['send_email']) && $address['id'] == $ClientUser['send_email']) {
                                    $send_email = $address;
                                }
                                if ($ClientUser['email'] == $address['email']) {
                                    $basic_email = $address['id'];
                                }
                                if ($address['id'] == $cfroms[0])
                                    continue;
                                ?>
                                <option value=""><?= $address['email'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td>名刺データ差出アドレス</td>
                    <form action="" method="post">
                        <td class="d-flex align-items-center justify-content-center">
                            <select name="send_email" id="send_email" class="d-none">
                                <?php foreach ($addresses as $address): ?>
                                    <option class="from_select" value="<?= $address['id']; ?>"><?= $address['email'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mb-0" id="currentEmail"><?= $send_email['email'] ?? ''; ?></p>
                            <button type="button" class="btn btn-dark ms-2" id="editEmailBtn"
                                onclick="enableSendEmail()" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                            <button type="submit" class="btn btn-success ms-2 d-none" id="saveEmailBtn">保存</button>
                        </td>
                    </form>
                </tr>
                <tr>
                    <td>タグ制限</td>
                    <td><?= $ClientUser['tags'] ?></td>
                </tr>
                <tr>
                    <td>禁止機能</td>
                    <td>
                        <?php
                        $functions = [
                            '削除' => '削除',
                            '変更' => '変更',
                            'CSV出力' => 'CSV出力',
                            'ユーザー管理' => 'ユーザー管理',
                            '各種設定' => '各種設定',
                            'アフィリエイト' => 'アフィリエイト',
                            '契約情報' => '契約情報'
                        ];

                        foreach ($functions as $key => $label) {
                            if (!in_array($key, $func_limit)) {
                                echo "<span class='text-success ms-2'>◯</span>$label ";
                            } else {
                                echo "<span class='text-danger ms-2'>×</span>$label ";
                            }
                        }
                        ?>
                    </td>
                </tr>
            </table>
        </main>
    </div>
    <script>
        $('.password').on('input', function () {
            var password = $('#new_pass').val();
            var passConfirm = $('#pass_confirm').val();
            var btn = $('#chPassBtn');

            if (passConfirm !== password) {
                $('#pass_confirm').css('border-color', 'red');
                btn.prop('disabled', true);
            } else {
                $('#pass_confirm').css('border-color', 'initial');
                btn.prop('disabled', false);
            }
        });

        $('.from_select').each(function () {
            var send_email = '<?= $send_email['id'] ?? ''; ?>';
            var basic_email = '<?= $basic_email; ?>';
            var fromId = $(this).val();

            if (send_email == '' && fromId == basic_email) {
                $(this).prop('selected', true);
            } else if (fromId == send_email) {
                $(this).prop('selected', true);
            } else {
                $(this).prop('selected', false);
            }
        });

        function enableSendEmail() {
            $('#currentEmail').hide();
            $("#send_email").removeClass('d-none');
            $("#saveEmailBtn").removeClass('d-none');
            $("#editEmailBtn").addClass('d-none');
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