<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (isset($_SESSION['csv_data']))
    unset($_SESSION["csv_data"]);
if (isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);
if (isset($_SESSION['email_data']))
    unset($_SESSION["email_data"]);
require ('common.php');

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    if (isset($_POST['action_name'])) {
        $stmt = $db->prepare("UPDATE actions SET action_value = ?, action_point = ?, action_memo = ? WHERE id = ?");
        $stmt->execute([$_POST['action_value'], $_POST['action_point'], $_POST['action_memo'], $_POST['action_id']]);
    }

    $stmt = $db->prepare("SELECT status FROM clients WHERE id=?");
    $stmt->execute([$client_id]);
    $client_status = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM actions WHERE client_id=?");
    $stmt->execute([$client_id]);
    $actions_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($actions_data)) {
        $action_names = ['アクセス数', '閲覧時間', 'ページ移動数', 'CTAクリック数', '未アクセス期間'];
        foreach ($action_names as $name) {
            $sql = "INSERT INTO actions (client_id,action_name";
            if ($name == '未アクセス期間') {
                $sql .= ", action_point";
            }
            if ($name == 'アクセス数') {
                $sql .= ", action_state";
            }
            $sql .= ") VALUES (:cid,:action_name";
            if ($name == '未アクセス期間') {
                $sql .= ", -1";
            }
            if ($name == 'アクセス数') {
                $sql .= ", 1";
            }
            $sql .= ")";
            $stmt = $db->prepare($sql);
            $params = array(':cid' => $client_id, ':action_name' => $name);
            $stmt->execute($params);
        }
        $stmt = $db->prepare("SELECT * FROM actions WHERE client_id=?");
        $stmt->execute([$client_id]);
        $actions_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
?>

<?php require ('header.php'); ?>

<body>
    <div class="wrapper">
        <?php require ('menu.php'); ?>
        <main>
            <div class="d-flex justify-content-between">
                <div class="header-with-help">
                    <h1>スコアリング</h1>
                </div>
                <?php require ('dropdown.php'); ?>
            </div>
            <div>
                <a class="tab_btn btn btn-primary" href="./stage.php">ステージ</a>
                <a class="tab_btn btn btn-secondary" href="./action.php">アクション</a>
            </div>
            <br>
            <!-- modal -->
            <div class="modal fade" id="detail" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                aria-labelledby="staticBackdropLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content" id="modal_content">
                        <div class="modal-header">
                            <h5 class="modal-title">詳細</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                                onclick="cancelEditing()"></button>
                        </div>
                        <!-- modal-body -->
                        <form action="" method="POST">
                            <div class="modal-body" id="modal_req">
                                <table class="table table-bordered">
                                    <tr>
                                        <td>アクション</td>
                                        <td>
                                            <input type="hidden" name="action_id" id="action_id" value="" />
                                            <input class="w-100" name="action_name" id="action_name" readonly />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>数値</td>
                                        <td>
                                            <input type="number" name="action_value" id="action_value" min="1" disabled
                                                required />
                                            <span id="action_unit"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>ポイント</td>
                                        <td><input type="number" name="action_point" id="action_point" disabled
                                                required /></td>
                                    </tr>
                                    <tr>
                                        <td>メモ</td>
                                        <td><textarea class="w-100" name="action_memo" id="action_memo"
                                                disabled></textarea></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="modal-footer">
                                <input type="hidden" name="client_id" value="<?= $client_id ?>">
                                <button type="button" id="edit_button" class="btn btn-dark"
                                    onclick="enableEditing()" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                                <button type="submit" id="save_button" class="btn btn-success"
                                    style="display:none">保存</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEditing()"
                                    data-bs-dismiss="modal">閉じる</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- end modal -->
            <!-- table -->
            <table class="table">
                <tr>
                    <th>アクション</th>
                    <th>数値</th>
                    <th>ポイント</th>
                    <th>更新日時</th>
                    <th></th>
                    <th>有効/無効</th>
                </tr>
                <?php foreach ($actions_data as $row): ?>
                    <tr>
                        <td>
                            <?= $row['action_name']; ?>
                        </td>
                        <td>
                            <?= $row['action_value']; ?>
                        </td>
                        <td>
                            <?= $row['action_point']; ?>
                        </td>
                        <td>
                            <?= substr($row['updated_at'], 0, 16); ?>
                        </td>
                        <td>
                            <button type="button" data-bs-toggle="modal" data-bs-target="#detail" class="btn btn-primary"
                                onclick="handleClick(`<?= $row['id']; ?>`,`<?= $row['action_name']; ?>`,`<?= $row['action_value']; ?>`,`<?= $row['action_point']; ?>`,`<?= $row['action_memo']; ?>`);">詳細</button>
                        </td>
                        <td>
                            <label class="switch">
                                <input type="checkbox" id="checkbox_<?= $row['id'] ?>" <?php echo $row['action_state'] == '1' ? 'checked' : ''; ?> value="<?= $row['action_state'] ?>"
                                    onchange="<?= ($row['action_name'] == 'アクセス数') ? 'alert_not_change(this)' : (($client_status == 'Free') ? 'alert_free(this, `' . $row['id'] . '`)' : 'chActionState(`' . $row['id'] . '`, this)') ?>"  <?= $client_id == $ClientUser['id'] ? '' : 'disabled' ; ?>>

                                <span class="slider round" id="toggleState_<?= $row['id']; ?>"
                                    data-content="<?php echo $row['action_state'] == '0' ? '無効' : '有効'; ?>">
                                </span>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>

            </table>
            <!-- end table -->
        </main>
    </div>
    <script>

        let inputs = document.querySelectorAll('#modal_req input:not(#user_id), #modal_req textarea');
        let editButton = document.getElementById('edit_button');
        let saveButton = document.getElementById('save_button');
        let closeBtn = document.getElementById('close');

        function handleClick(action_id, action_name, action_value, action_point, action_memo) {
            document.getElementById("action_id").value = action_id;
            document.getElementById("action_name").value = action_name;
            document.getElementById("action_value").value = action_value;
            document.getElementById("action_point").value = action_point;
            document.getElementById("action_memo").value = action_memo;
            document.getElementById("action_point").min = 1;
            document.getElementById("action_point").removeAttribute("max");
            if (action_name == '閲覧時間') {
                document.getElementById("action_unit").innerText = '秒';
            } else if (action_name == 'ページ移動数') {
                document.getElementById("action_unit").innerText = 'ページ';
            } else if (action_name == 'CTAクリック数' || action_name == 'アクセス数') {
                document.getElementById("action_unit").innerText = '回';
            } else if (action_name == '未アクセス期間') {
                document.getElementById("action_unit").innerText = '日';
                document.getElementById("action_point").max = -1;
                document.getElementById("action_point").removeAttribute("min");
            }
        }
        function enableEditing() {
            inputs.forEach(input => {
                input.disabled = false;
            });

            editButton.style.display = 'none';
            saveButton.style.display = 'inline-block';
        }
        function cancelEditing() {
            inputs.forEach(input => {
                input.disabled = true;
            });
            saveButton.style.display = "none";
            editButton.style.display = "block";
        }
        function chActionState(id, checkbox) {
            var state = checkbox.value;
            var toggle_id = '#toggleState_' + id;
            var toggle = document.querySelector(toggle_id);

            if (toggle) {
                console.log(state);
                toggle.setAttribute('data-content', state == 1 ? '無効' : '有効');
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', './back/ch_action_state_display.php');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('id=' + id + '&state=' + state);
            checkbox.value = (state == 1) ? 0 : 1;
        }
        function alert_free(e, id) {
            e.checked = false;
            alert('無料プランはこのアクションを有効化できません。');
            return;
        }
        function alert_not_change(checkbox) {
            alert('「アクセス数」は無効化できません。');
            checkbox.checked = !checkbox.checked;
            return;
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