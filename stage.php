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

    if (isset($_POST['stage_name'])) {
        $stmt = $db->prepare("UPDATE stages SET stage_name = ?, stage_point = ?, stage_memo = ? WHERE id = ?");
        $stmt->execute([$_POST['stage_name'], $_POST['stage_point'], $_POST['stage_memo'], $_POST['stage_id']]);
    }
    if (isset($_POST['del_stage_id'])) {
        $stmt = $db->prepare("DELETE FROM stages WHERE id = ?");
        $stmt->execute([$_POST['del_stage_id']]);
    }

    $stmt = $db->prepare("SELECT * FROM stages WHERE client_id=? ORDER BY stage_point DESC");
    $stmt->execute([$client_id]);
    $stages_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($ClientData['status'] == 'Free') {

        if (count($stages_data) > 2) {
            $remaining_ids = array_slice($stages_data, 2);
            $stages_data = array_slice($stages_data, 0, 2);

            foreach ($remaining_ids as $item) {
                $id = $item['id'];
                $delete_stmt = $db->prepare("DELETE FROM stages WHERE id=?");
                $delete_stmt->execute([$id]);
            }
        }
    }

    if (empty($stages_data)) {
        $sql = "INSERT INTO stages (client_id,stage_name,stage_point,stage_memo) VALUES (:client_id,:stage_name,:stage_point,:stage_memo)";
        $stmt = $db->prepare($sql);
        $params = array(':client_id' => $client_id, ':stage_name' => '0ポイント', ':stage_point' => '0', ':stage_memo' => '');
        $stmt->execute($params);

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $stage = [];
    $stage_range = [];
    foreach ($stages_data as $key => $val) {
        $stage[$val['stage_point']] = $val['stage_name'];
        $custom_count[$val['stage_name']] = 0;
    }
    // $stage = array_reverse($stage, true);

    $sql = "SELECT * FROM actions WHERE client_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $actions = $stmt->fetchAll();
    $action = [];
    foreach ($actions as $val) {
        $action[$val['action_name']] = $val;
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE cid=?");
    $stmt->execute([$client_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $row) {
        $point = 0;
        $sql = "SELECT upa.*, pv.title FROM user_pdf_access upa
        JOIN pdf_versions pv ON upa.pdf_version_id = pv.pdf_version_id
        WHERE upa.cid=? AND upa.user_id=?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$client_id, $row['user_id']]);
        $user_pdf_access = $stmt->fetchAll();

        $access_pdfs = [];
        $user_pdf_access_count = 0;
        foreach ($user_pdf_access as $access) {
            if (!in_array($access['pdf_version_id'], $access_pdfs)) {
                $user_pdf_access_count++;
                $access_pdfs[] = $access['pdf_version_id'];
            }
        }
        //リダイレクト
        $sql = "SELECT ura.*, r.title FROM user_redirects_access ura
        JOIN redirects r ON ura.redirect_id = r.id
        WHERE ura.cid=? AND ura.user_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$client_id, $row['user_id']]);
        $user_redirects_access = $stmt->fetchAll();

        // 合計アクセス数を計算する
        $totalAccessCount = $user_pdf_access_count + count($user_redirects_access);
        if (isset($action['アクセス数']) && $action['アクセス数']['action_state'] == 1) {
            $point += (int) ($totalAccessCount / $action['アクセス数']['action_value']) * $action['アクセス数']['action_point'];
        }
        // 合計閲覧時間を計算する
        $totalDuration = array_reduce($user_pdf_access, function ($carry, $item) {
            return $carry + ($item['duration'] !== null ? $item['duration'] : 0);
        }, 0);
        if (isset($action['閲覧時間']) && $action['閲覧時間']['action_state'] == 1) {
            $point += (int) ($totalDuration / $action['閲覧時間']['action_value']) * $action['閲覧時間']['action_point'];
        }
        //ページ移動数
        $total_page_moved = 0;
        foreach ($user_pdf_access as $access) {
            if ($access['page'] > 0 && $access['duration'] > 0) {
                $total_page_moved++;
            }
        }
        if (isset($action['ページ移動数']) && $action['ページ移動数']['action_state'] == 1) {
            $point += (int) ($total_page_moved / $action['ページ移動数']['action_value']) * $action['ページ移動数']['action_point'];
        }
        // CTAクリック数の合計を計算する
        $query = "SELECT COUNT(*) FROM popup_access WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $row['user_id'], PDO::PARAM_STR);
        $stmt->execute();
        $totalCtaClicks = $stmt->fetchColumn();
        if (isset($action['CTAクリック数']) && $action['CTAクリック数']['action_state'] == 1) {
            $point += (int) ($totalCtaClicks / $action['CTAクリック数']['action_value']) * $action['CTAクリック数']['action_point'];
        }
        //最終アクセス期間
        $final_pdf = (!empty($user_pdf_access)) ? date('Y-m-d', strtotime($user_pdf_access[0]['accessed_at'])) : date('1970-1-1');
        $final_redirect = (!empty($user_redirects_access)) ? date('Y-m-d', strtotime($user_redirects_access[0]['accessed_at'])) : date('1970-1-1');
        if ($final_pdf > $final_redirect) {
            $final_access = $final_pdf;
        } else {
            $final_access = $final_redirect;
        }
        $today = new DateTime();
        $interval = $today->diff(new DateTime($final_access));
        $not_access_range = $interval->days;
        if (isset($action['未アクセス期間']) && $action['未アクセス期間']['action_state'] == 1) {
            $point += (int) ($not_access_range / $action['未アクセス期間']['action_value']) * $action['未アクセス期間']['action_point'];
        }
        if ($point < 0) {
            $point = 0;
        }

        if (!empty($stage)) {
            foreach ($stage as $key => $val) {
                if ($point >= (int) $key) {
                    $custom_count[$val]++;
                    break;
                }
            }
        }
    }

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}

$current = count($stages_data);
$max = $ClientData['max_stage'];
$limit = $max - $current;
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
                <a class="tab_btn btn btn-secondary" href="./stage.php">ステージ</a>
                <a class="tab_btn btn btn-primary" href="./action.php">アクション</a>
            </div>
            <br>
            <?php if ($ClientData['max_cms_type'] == 1 && $limit <= 0): ?>
                <?php require_once ('./limitModal.php'); ?>
                <button type="button" class="btn btn-primary" id="modal_button" data-bs-toggle="modal"
                    data-bs-target="#limitModal">新規登録</button>
            <?php else: ?>
                <button type="button" class="btn btn-primary" id="modal_button" data-bs-toggle="modal"
                    data-bs-target="#new">新規登録</button>
            <?php endif; ?>
            <!-- new modal -->
            <div class="modal fade" id="new" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                aria-labelledby="staticBackdropLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content" id="modal_content">
                        <div class="modal-header">
                            <h5 class="modal-title">新規登録</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <!-- modal-body -->
                        <form action="./back/new_stage.php" method="POST">
                            <div class="modal-body" id="">
                                <table class="table table-bordered">
                                    <tr>
                                        <td>ステージ</td>
                                        <td>
                                            <input class="w-100" name="stage_name" id="stage_name" required />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>ポイント</td>
                                        <td><input type="number" name="stage_point" id="stage_point" min="1" required />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>メモ</td>
                                        <td><textarea class="w-100" name="stage_memo" id="stage_memo"></textarea></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="modal-footer">
                                <input type="hidden" name="client_id" value="<?= $client_id ?>">
                                <button type="submit" class="btn btn-success">新規登録</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- end new modal -->
            <!-- edit modal -->
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
                        <form action="" method="POST" id="deleteForm">
                            <input type="hidden" name="del_stage_id" id="del_stage_id" value="" />
                        </form>
                        <form action="" method="POST">
                            <div class="modal-body" id="modal_req">
                                <table class="table table-bordered">
                                    <tr>
                                        <td>ステージ</td>
                                        <td>
                                            <input type="hidden" name="stage_id" id="ch_stage_id" value="" />
                                            <input class="w-100" name="stage_name" id="ch_stage_name" disabled />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>ポイント</td>
                                        <td><input type="number" name="stage_point" id="ch_stage_point" min="1" disabled
                                                required /></td>
                                    </tr>
                                    <tr>
                                        <td>メモ</td>
                                        <td><textarea class="w-100" name="stage_memo" id="ch_stage_memo"
                                                disabled></textarea></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="modal-footer">
                                <input type="hidden" name="client_id" value="<?= $client_id ?>">
                                <button type="button" id="del_button" class="btn btn-danger" onclick="deleteStage()"
                                    <?= in_array('削除', $func_limit) ? 'disabled' : ''; ?>>削除</button>
                                <button type="button" id="edit_button" class="btn btn-dark" onclick="enableEditing()"
                                    <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                                <button type="submit" id="save_button" class="btn btn-success"
                                    style="display:none">保存</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEditing()"
                                    data-bs-dismiss="modal">閉じる</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- end edit modal -->
            <!-- table -->
            <table class="table">
                <tr>
                    <th>No</th>
                    <th>ステージ</th>
                    <th>ポイント</th>
                    <th>顧客数</th>
                    <th>更新日時</th>
                    <th></th>
                    <th></th>
                    <!-- <th>有効/無効</th> -->
                </tr>
                <?php $i = 1;
                foreach ($stages_data as $row): ?>
                    <tr>
                        <td>
                            <?= $i++; ?>
                        </td>
                        <td>
                            <?= $row['stage_name']; ?>
                        </td>
                        <td>
                            <?= $row['stage_point']; ?>
                        </td>
                        <td>
                            <?= $custom_count[$row['stage_name']]; ?>
                        </td>
                        <td>
                            <?= substr($row['updated_at'], 0, 16); ?>
                        </td>
                        <td>
                            <?php $href = [];
                            $href['selected_stage'][] = $row['stage_name']; ?>
                            <a href="clients_list.php?custom_info=&<?= http_build_query($href); ?>"
                                class="btn btn-warning">一覧</a>
                        </td>
                        <td>
                            <button type="button" data-bs-toggle="modal" data-bs-target="#detail" class="btn btn-primary"
                                onclick="handleClick(`<?= $row['id']; ?>`,`<?= $row['stage_name']; ?>`,`<?= $row['stage_point']; ?>`,`<?= $row['stage_memo']; ?>`);">詳細</button>
                        </td>
                        <!-- <td>
                            <label class="switch">
                                <input type="checkbox" <?php echo $row['stage_state'] == '1' ? 'checked' : ''; ?>
                                    onchange="chstageState(`<?= $row['id']; ?>`,`<?= $row['stage_state']; ?>`)">

                                <span class="slider round" id="toggleState_<?= $row['id']; ?>"
                                    data-content="<?php echo $row['stage_state'] == '0' ? '無効' : '有効'; ?>">
                                </span>
                            </label>
                        </td> -->
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

        function limit_stage_num() {
            alert('プリプランではステージを2つまでしか設定できません。');
        }

        function handleClick(stage_id, stage_name, stage_point, stage_memo) {
            document.getElementById("del_stage_id").value = stage_id;
            document.getElementById("ch_stage_id").value = stage_id;
            document.getElementById("ch_stage_name").value = stage_name;
            document.getElementById("ch_stage_point").value = stage_point;
            document.getElementById("ch_stage_memo").value = stage_memo;
            if (stage_point == 0) {
                document.getElementById("ch_stage_point").readOnly = true;
                document.getElementById('del_button').style.display = 'none';
            } else {
                document.getElementById("ch_stage_point").readOnly = false;
                document.getElementById('del_button').style.display = 'block';
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
        function chstageState(id, state) {
            var toggle_id = '#toggleState_' + id;
            var toggle = document.querySelector(toggle_id);

            if (toggle) {
                toggle.setAttribute('data-content', state == 1 ? '無効' : '有効');
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', './back/ch_stage_state_display.php');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('id=' + id + '&state=' + state);

        }
        function deleteStage() {
            $("#deleteForm").submit();
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