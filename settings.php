<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (isset($_GET['client_id'])) {
    $_SESSION['client_id'] = $_GET['client_id'];
}
if (isset($_SESSION['csv_data']))
    unset($_SESSION["csv_data"]);
if (isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);
if (isset($_SESSION['email_data']))
    unset($_SESSION['email_data']);
$tab = $_GET['tab'] ?? '';
require ('./common.php');
try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $reach_stage = (is_null($ClientData['reach_stage'])) ? [] : explode(',', $ClientData['reach_stage']);
    $resend_state = $ClientData['resend_state'];
    $resend_temp_id = $ClientData['resend_temp'];
    if (is_null($ClientData['resend_timing'])) {
        $resend_date = '';
        $resend_time = '';
    } else {
        $resend_timing = explode(',', $ClientData['resend_timing']);
        $resend_date = $resend_timing[0];
        $resend_time = $resend_timing[1];
    }

    $stmt = $db->prepare("SELECT * FROM templates WHERE cid=? AND division = 'メール配信'");
    $stmt->execute([$client_id]);
    $template_data = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT * FROM stages WHERE client_id=? ORDER BY stage_point");
    $stmt->execute([$client_id]);
    $stage_data = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT * FROM tag_sample WHERE client_id=?");
    $stmt->execute([$client_id]);
    $tag_data = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT * FROM favi_logos WHERE client_id=?");
    $stmt->execute([$client_id]);
    $favi_logo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ClientData['status'] == 'Free') {

        if (count($tag_data) > 3) {
            $remaining_ids = array_slice($tag_data, 3);
            $tag_data = array_slice($tag_data, 0, 3);

            foreach ($remaining_ids as $item) {
                $id = $item['id'];
                $delete_stmt = $db->prepare("DELETE FROM tag_sample WHERE id=?");
                $delete_stmt->execute([$id]);
            }

        }
        $alive_tags = [];
        foreach ($tag_data as $td) {
            $alive_tags[] = $td['tag_name'];
        }
        $stmt = $db->prepare("SELECT tg.tags, tg.id FROM tags tg JOIN templates tm ON tm.id = tg.table_id WHERE tg.table_name = 'templates' AND tm.cid=?");
        $stmt->execute([$client_id]);
        $temp2tags = $stmt->fetchAll();
        foreach ($temp2tags as $tt) {
            $a = explode(',', $tt['tags']);
            $t = [];
            foreach ($a as $e) {
                if (in_array($e, $alive_tags)) {
                    $t[] = $e;
                }
            }
            if (empty($t)) {
                $delete_stmt = $db->prepare("DELETE FROM tags WHERE id=?");
                $delete_stmt->execute([$tt['id']]);
            } else {
                $stmt = $db->prepare("UPDATE tags SET tags = ? WHERE id = ?");
                $stmt->execute([implode(',', $t), $tt['id']]);
            }
        }
        $stmt = $db->prepare("SELECT tg.tags, tg.id FROM tags tg JOIN pdf_versions tm ON tm.pdf_version_id = tg.table_id WHERE tg.table_name = 'pdf_versions' AND tm.cid=?");
        $stmt->execute([$client_id]);
        $temp2tags = $stmt->fetchAll();
        foreach ($temp2tags as $tt) {
            $a = explode(',', $tt['tags']);
            $t = [];
            foreach ($a as $e) {
                if (in_array($e, $alive_tags)) {
                    $t[] = $e;
                }
            }
            if (empty($t)) {
                $delete_stmt = $db->prepare("DELETE FROM tags WHERE id=?");
                $delete_stmt->execute([$tt['id']]);
            } else {
                $stmt = $db->prepare("UPDATE tags SET tags = ? WHERE id = ?");
                $stmt->execute([implode(',', $t), $tt['id']]);
            }
        }
        $stmt = $db->prepare("SELECT tg.tags, tg.id FROM tags tg JOIN redirects tm ON tm.id = tg.table_id WHERE tg.table_name = 'redirects' AND tm.cid=?");
        $stmt->execute([$client_id]);
        $temp2tags = $stmt->fetchAll();
        foreach ($temp2tags as $tt) {
            $a = explode(',', $tt['tags']);
            $t = [];
            foreach ($a as $e) {
                if (in_array($e, $alive_tags)) {
                    $t[] = $e;
                }
            }
            if (empty($t)) {
                $delete_stmt = $db->prepare("DELETE FROM tags WHERE id=?");
                $delete_stmt->execute([$tt['id']]);
            } else {
                $stmt = $db->prepare("UPDATE tags SET tags = ? WHERE id = ?");
                $stmt->execute([implode(',', $t), $tt['id']]);
            }
        }
        $stmt = $db->prepare("SELECT tg.tags, tg.id FROM tags tg JOIN popups tm ON tm.id = tg.table_id WHERE tg.table_name = 'popups' AND tm.cid=?");
        $stmt->execute([$client_id]);
        $temp2tags = $stmt->fetchAll();
        foreach ($temp2tags as $tt) {
            $a = explode(',', $tt['tags']);
            $t = [];
            foreach ($a as $e) {
                if (in_array($e, $alive_tags)) {
                    $t[] = $e;
                }
            }
            if (empty($t)) {
                $delete_stmt = $db->prepare("DELETE FROM tags WHERE id=?");
                $delete_stmt->execute([$tt['id']]);
            } else {
                $stmt = $db->prepare("UPDATE tags SET tags = ? WHERE id = ?");
                $stmt->execute([implode(',', $t), $tt['id']]);
            }
        }
    }

    $stmt = $db->prepare("SELECT * FROM form_users WHERE client_id=:client_id");
    $stmt->execute([':client_id' => $client_id]);
    $form_users_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($form_users_data)) {
        $sql = "INSERT INTO form_users (client_id,email,status) VALUES (:client_id,:email,:status)";
        $stmt = $db->prepare($sql);
        $params = array(':client_id' => $client_id, ':email' => 'test@gmail.com', ':status' => '0');
        $stmt->execute($params);
    }

    $form_users_items = isset($form_users_data['items']) ? json_decode($form_users_data['items'], true) : [];

    $stmt = $db->prepare("SELECT * FROM biz_template_setting WHERE cid=?");
    $stmt->execute([$client_id]);
    $biz_template_setting_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($biz_template_setting_data) {
        $b_template_id = $biz_template_setting_data['template_id'];
        $b_sending_time = $biz_template_setting_data['sending_time'];
    } else {
        $b_template_id = null;
        $b_sending_time = null;
    }

    $stmt = $db->prepare("SELECT * FROM business_schedules WHERE cid=?");
    $stmt->execute([$client_id]);
    $scheduled_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $scheduled_num = count($scheduled_data);

    $current = count($tag_data);
    $max = $ClientData['max_tag'];
    $limit = $ClientData['max_tag'] - count($tag_data);

    $stmt = $db->prepare("SELECT * FROM froms WHERE cid=?");
    $stmt->execute([$client_id]);
    $froms_data = $stmt->fetchAll();

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

    table.dataTable thead .sorting_desc {
        background-image: url(./img/sort_desc.png) !important;
    }

    table.dataTable thead .sorting_asc {
        background-image: url(./img/sort_asc.png) !important;
    }
</style>
<style>
    .drop-zone {
        height: 200px;
        /* padding: 25px; */
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-family: "Quicksand", sans-serif;
        font-weight: 500;
        font-size: 20px;
        cursor: pointer;
        color: #cccccc;
        border: 2px dashed #afbac5;
        border-radius: 10px;
    }

    .drop-zone--over {
        border-style: solid;
    }

    .drop-zone__input {
        display: none;
    }

    .drop-zone__thumb {
        width: 100%;
        height: 100%;
        border-radius: 10px;
        overflow: hidden;
        background-color: #cccccc;
        background-size: cover;
        position: relative;
    }

    .drop-zone__thumb::after {
        content: attr(data-label);
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        padding: 5px 0;
        color: #ffffff;
        background: rgba(0, 0, 0, 0.75);
        font-size: 14px;
        text-align: center;
    }
</style>

<body>
    <div id="pageMessages">
    </div>
    <div class="wrapper">
        <?php require ('menu.php'); ?>
        <input type="hidden" name="user_id" id="user_id" value="<?= $client_id; ?>">
        <main>
            <div class="d-flex justify-content-between">
                <div class="header-with-help">
                    <h1>各種設定</h1>
                </div>
                <?php require ('dropdown.php'); ?>
            </div>
            <div>
                <button class="tab_btn btn  <?= $tab != '' && $tab != 'tag' ? 'btn-primary' : 'btn-secondary'; ?>"
                    id="tab_tag">タグ</button>
                <button class="tab_btn btn <?= $tab != 'link' ? 'btn-primary' : 'btn-secondary'; ?>"
                    id="tab_link">名刺連携</button>
                <button class="tab_btn btn <?= $tab != 'notice' ? 'btn-primary' : 'btn-secondary'; ?>"
                    id="tab_notice">通知</button>
                <button class="tab_btn btn <?= $tab != 'form' ? 'btn-primary' : 'btn-secondary'; ?>"
                    id="tab_form">フォーム連携</button>
                <!-- <button class="tab_btn btn <?= $tab != 'resend' ? 'btn-primary' : 'btn-secondary'; ?>"
                    id="tab_resend">再送メール</button> -->
                <button class="tab_btn btn <?= $tab != 'other' ? 'btn-primary' : 'btn-secondary'; ?>"
                    id="tab_other">その他</button>
            </div>
            <div id="tabbody_tag" class="tabbody <?= $tab != '' && $tab != 'tag' ? 'd-none' : ''; ?>">
                <br>
                <div class="modal fade" id="ch_tag" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                    aria-labelledby="staticBackdropLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content" id="modal_content">
                            <div class="modal-header">
                                <h5 class="modal-title">詳細</h5>
                                <button type="button" class="btn-close" onclick="cancelEditTag()"
                                    data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <!-- modal-body -->
                            <form action="./back/ch_tag.php" method="POST">
                                <input type="hidden" name="ch_id" id="ch_id">
                                <div class="modal-body" id="">
                                    <table class="table table-bordered">
                                        <tr>
                                            <td>タグ名</td>
                                            <td>
                                                <input class="w-100" name="ch_tag_name" id="ch_tag_name" required
                                                    disabled />
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>メモ</td>
                                            <td>
                                                <textarea class="w-100" name="ch_memo" id="ch_memo" disabled></textarea>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" id="del_tag" class="btn btn-danger me-2" <?= in_array('削除', $func_limit) ? 'disabled' : ''; ?>>削除</button>
                                    <button type="submit" id="save_tag" class="btn btn-success">保存</button>
                                    <button type="button" id="edit_tag" class="btn btn-dark" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                                    <button type="button" id="close_tag" class="btn btn-secondary ms-2"
                                        data-bs-dismiss="modal">閉じる</button>
                                </div>
                            </form>
                            <form action="./back/del_tag.php" method="POST" id="delTagForm">
                                <input type="hidden" name="del_tag_id" id="del_tag_id">
                            </form>
                        </div>
                    </div>
                </div>
                
                <?php if ($ClientData['max_tag_type'] == 1 && $limit <= 0): ?>
                    <?php require_once ('./limitModal.php'); ?>
                    <button type="button" class="btn btn-primary" id="modal_button" data-bs-toggle="modal"
                        data-bs-target="#limitModal">新規登録</button>
                <?php else: ?>
                    <button type="button" class="btn btn-primary" id="modal_button" data-bs-toggle="modal"
                        data-bs-target="#new_tag">新規登録</button>
                <?php endif; ?>

                <div class="modal fade" id="new_tag" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                    aria-labelledby="staticBackdropLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content" id="modal_content">
                            <div class="modal-header">
                                <h5 class="modal-title">新規登録</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                            </div>
                            <!-- modal-body -->
                            <form action="./back/new_tag.php" method="POST">
                                <div class="modal-body" id="">
                                    <table class="table table-bordered">
                                        <tr>
                                            <td>タグ名</td>
                                            <td>
                                                <input class="w-100" name="new_tag" id="new_tag" required />
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>メモ</td>
                                            <td>
                                                <textarea class="w-100" name="new_memo" id="new_memo"></textarea>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-primary">新規登録</button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <br>
                <table class="table" id="tag_table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>タグ名</th>
                            <th>更新日時</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1;
                        foreach ($tag_data as $tag): ?>
                            <tr>
                                <td>
                                    <?= $i++; ?>
                                </td>
                                <td>
                                    <?= $tag['tag_name']; ?>
                                </td>
                                <td>
                                    <?= date('Y-m-d H:i', strtotime($tag['updated_at'])); ?>
                                </td>
                                <td>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ch_tag"
                                        onClick="chTagModal(`<?= $tag['id']; ?>`,`<?= $tag['tag_name']; ?>`,`<?= $tag['memo']; ?>`);">詳細</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="tabbody_link" class="tabbody <?= $tab != 'link' ? 'd-none' : ''; ?>">
                <form action="back/ch_biz_template_setting.php" id="schedule_save_form" method="post">
                    <div class="tmp_setting bus_link">
                        <h2>テンプレート</h2>
                        <select name="id" id="template-select" disabled>
                            <?php
                            $template_name = '未設定';
                            $template_selected = '';

                            // テンプレートIDが設定されている場合、そのテンプレートを取得
                            if ($b_template_id) {
                                $query = "SELECT subject, content FROM templates WHERE id = :template_id";
                                $stmt = $db->prepare($query);
                                $stmt->bindParam(':template_id', $b_template_id);
                                $stmt->execute();

                                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($result) {
                                    $template_name = $result['subject'];
                                    $template_selected = 'selected';
                                }
                            }

                            // 「未設定」をデフォルトオプションとして表示
                            echo "<option value=\"\">未設定</option>";

                            foreach ($template_data as $template) {
                                if ($template['division'] == 'メール配信') {
                                    // テンプレートIDがある場合、それを選択状態にする
                                    $selected = ($b_template_id == $template['id']) ? 'selected' : '';
                                    echo "<option value=\"{$template['id']}\" $selected>{$template['subject']}</option>";
                                }
                            }

                            // データがある場合のみ「未設定」を無効にする
                            if ($b_template_id) {
                                echo "<script>document.getElementById('template-select').options[0].disabled = true;</script>";
                            }
                            ?>
                        </select>

                    </div>
                    <div class="d-flex w-25 mt-3 align-items-center justify-content-end">
                        <label class="switch temp">
                            <input type="checkbox" <?php echo $ClientData['temp_display'] === 'ON' ? 'checked' : ''; ?>
                                id="tempSwitch_<?php echo $ClientData['id']; ?>" disabled>
                            <span class="slider round temp"
                                data-content="<?php echo $ClientData['temp_display'] === 'ON' ? '配信中' : '停止中'; ?>"></span>
                        </label>
                        <a href="./g_history.php" class="btn btn-primary mx-2">登録履歴</a>
                        <button type="button" class="btn btn-primary mx-2" data-bs-toggle="modal"
                            data-bs-target="#prev_temp">プレビュー</button>
                        <button type="button" id="editing_tt" class="btn btn-dark" onclick="enableEditing()"
                            <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                        <button type="button" id="save_button" class="btn btn-success" style="display:none"
                            onclick="scheduleSave()">保存</button>
                    </div>
                    <input type="hidden" name="hour" id="hour" value="9">
                    <input type="hidden" name="cid" value="<?= $client_id ?>">
                </form>
                <div class="modal fade" id="prev_temp" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                    aria-labelledby="staticBackdropLabel" aria-hidden="true">

                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                            </div>
                            <!-- modal-body -->
                            <div class="modal-body" id="modal_req">
                                <table class="table table-bordered">
                                    <tr>
                                        <td style="min-width: 120px;">テンプレート名</td>
                                        <td>
                                            <span id=""><?= $result['subject'] ?? ''; ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>本文</td>
                                        <td>
                                            <span
                                                id=""><?= isset($result['content']) ? nl2br($result['content']) : ''; ?></span>
                                        </td>
                                    </tr>
                                </table>
                                <br>
                                <br>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="tabbody_notice" class="tabbody <?= $tab != 'notice' ? 'd-none' : ''; ?>">
                <br>
                <section>
                    <form action="./back/ch_chatwork.php" method="post" id="ch_chatwork">
                        <input type="hidden" name="client_id" value="<?= $client_id; ?>">
                        <table class="table w-50">
                            <tr>
                                <td>Chatwork</td>
                                <td>
                                    <input type="text" name="chatwork_id"
                                        value="<?= $ClientData['chatwork_id'] ?? ''; ?>" placeholder="ルームIDを入力" disabled
                                        required>
                                </td>
                            </tr>
                            <tr>
                                <td>ステージ</td>
                                <td>
                                    <?php foreach ($stage_data as $stage): ?>
                                        <?php if ($stage['stage_point'] != 0): ?>
                                            <input type="checkbox" name="stage[<?= $stage['stage_name']; ?>]"
                                                id="stage<?= $stage['stage_point']; ?>" <?= in_array($stage['stage_name'], $reach_stage) ? 'checked' : ''; ?> disabled>
                                            <label for="stage<?= $stage['stage_point']; ?>">
                                                <?= $stage['stage_name']; ?>
                                            </label>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        </table>
                        <div class="w-50 d-flex justify-content-between">
                            <a href="https://www.chatwork.com/doctrack" target="_blank"
                                class="btn btn-warning">通知専用アカウントをコンタクト追加</a>
                            <button type="button" id="edit_chatwork" class="btn btn-dark" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                            <button id="save_chatwork" class="btn btn-success">保存</button>
                        </div>
                    </form>
                </section>
            </div>
            <div id="tabbody_form" class="tabbody <?= $tab != 'form' ? 'd-none' : ''; ?>">
                <br>
                <section>
                    <form action="./back/ch_form_user.php" method="post" id="ch_form">
                        <input type="hidden" name="client_id" value="<?= $client_id; ?>">
                        <div class="d-flex align-items-center mb-3">
                            <?php if (isset($form_users_data['token']) && $form_users_data['token'] != ''): ?>
                                <a href="./back/cancle_form_user.php" class="btn btn-danger me-2">連携解除</a>
                                <?= $form_users_data['email']; ?>
                            <?php else: ?>
                                <button type="button" id="form_link_btn" target="_blank"
                                    class="btn btn-success">連携登録</button>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex align-items-center">
                            判定メール件名
                            <input class="w-25 mx-1" type="text" name="subject"
                                value="<?= $form_users_data['subject'] ?? ''; ?>" placeholder="判定するメールの件名を入力してください。"
                                disabled required>
                            <span class="require-note">必須</span>
                        </div>
                        <table class="table w-50">
                            <thead>
                                <th></th>
                                <th>フォーム項目名</th>
                                <th>DocTrack顧客項目名</th>
                            </thead>
                            <tr>
                                <td>項目1</td>
                                <td class="d-flex align-items-center justify-content-around">
                                    <input type="text" name="item[1]" value="<?= $form_users_items[1] ?? ''; ?>"
                                        disabled>
                                    <svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg"
                                        xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                                        viewBox="0 0 17.804 17.804"
                                        style="enable-background:new 0 0 17.804 17.804; width: 20px;"
                                        xml:space="preserve">
                                        <g>
                                            <g id="c98_play">
                                                <path
                                                    d="M2.067,0.043C2.21-0.028,2.372-0.008,2.493,0.085l13.312,8.503c0.094,0.078,0.154,0.191,0.154,0.313 c0,0.12-0.061,0.237-0.154,0.314L2.492,17.717c-0.07,0.057-0.162,0.087-0.25,0.087l-0.176-0.04 c-0.136-0.065-0.222-0.207-0.222-0.361V0.402C1.844,0.25,1.93,0.107,2.067,0.043z"
                                                    fill="#000000" style="fill: rgb(255, 193, 7);"></path>
                                            </g>
                                            <g id="Capa_1_78_"></g>
                                        </g>
                                    </svg>
                                </td>
                                <td>企業名</td>
                            </tr>
                            <tr>
                                <td>項目2</td>
                                <td class="d-flex align-items-center justify-content-around">
                                    <input type="text" name="item[2]" value="<?= $form_users_items[2] ?? ''; ?>"
                                        disabled>
                                    <svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg"
                                        xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                                        viewBox="0 0 17.804 17.804"
                                        style="enable-background:new 0 0 17.804 17.804; width: 20px;"
                                        xml:space="preserve">
                                        <g>
                                            <g id="c98_play">
                                                <path
                                                    d="M2.067,0.043C2.21-0.028,2.372-0.008,2.493,0.085l13.312,8.503c0.094,0.078,0.154,0.191,0.154,0.313 c0,0.12-0.061,0.237-0.154,0.314L2.492,17.717c-0.07,0.057-0.162,0.087-0.25,0.087l-0.176-0.04 c-0.136-0.065-0.222-0.207-0.222-0.361V0.402C1.844,0.25,1.93,0.107,2.067,0.043z"
                                                    fill="#000000" style="fill: rgb(255, 193, 7);"></path>
                                            </g>
                                            <g id="Capa_1_78_"></g>
                                        </g>
                                    </svg>
                                </td>
                                <td>姓</td>
                            </tr>
                            <tr>
                                <td>項目3</td>
                                <td class="d-flex align-items-center justify-content-around">
                                    <input type="text" name="item[3]" value="<?= $form_users_items[3] ?? ''; ?>"
                                        disabled>
                                    <svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg"
                                        xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                                        viewBox="0 0 17.804 17.804"
                                        style="enable-background:new 0 0 17.804 17.804; width: 20px;"
                                        xml:space="preserve">
                                        <g>
                                            <g id="c98_play">
                                                <path
                                                    d="M2.067,0.043C2.21-0.028,2.372-0.008,2.493,0.085l13.312,8.503c0.094,0.078,0.154,0.191,0.154,0.313 c0,0.12-0.061,0.237-0.154,0.314L2.492,17.717c-0.07,0.057-0.162,0.087-0.25,0.087l-0.176-0.04 c-0.136-0.065-0.222-0.207-0.222-0.361V0.402C1.844,0.25,1.93,0.107,2.067,0.043z"
                                                    fill="#000000" style="fill: rgb(255, 193, 7);"></path>
                                            </g>
                                            <g id="Capa_1_78_"></g>
                                        </g>
                                    </svg>
                                </td>
                                <td>名</td>
                            </tr>
                            <tr>
                                <td>項目4</td>
                                <td class="d-flex align-items-center justify-content-around">
                                    <input type="text" name="item[4]" value="<?= $form_users_items[4] ?? ''; ?>"
                                        disabled>
                                    <svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg"
                                        xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                                        viewBox="0 0 17.804 17.804"
                                        style="enable-background:new 0 0 17.804 17.804; width: 20px;"
                                        xml:space="preserve">
                                        <g>
                                            <g id="c98_play">
                                                <path
                                                    d="M2.067,0.043C2.21-0.028,2.372-0.008,2.493,0.085l13.312,8.503c0.094,0.078,0.154,0.191,0.154,0.313 c0,0.12-0.061,0.237-0.154,0.314L2.492,17.717c-0.07,0.057-0.162,0.087-0.25,0.087l-0.176-0.04 c-0.136-0.065-0.222-0.207-0.222-0.361V0.402C1.844,0.25,1.93,0.107,2.067,0.043z"
                                                    fill="#000000" style="fill: rgb(255, 193, 7);"></path>
                                            </g>
                                            <g id="Capa_1_78_"></g>
                                        </g>
                                    </svg>
                                </td>
                                <td>メールアドレス</td>
                            </tr>
                            <tr>
                                <td>項目5</td>
                                <td class="d-flex align-items-center justify-content-around">
                                    <input type="text" name="item[5]" value="<?= $form_users_items[5] ?? ''; ?>"
                                        disabled>
                                    <svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg"
                                        xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                                        viewBox="0 0 17.804 17.804"
                                        style="enable-background:new 0 0 17.804 17.804; width: 20px;"
                                        xml:space="preserve">
                                        <g>
                                            <g id="c98_play">
                                                <path
                                                    d="M2.067,0.043C2.21-0.028,2.372-0.008,2.493,0.085l13.312,8.503c0.094,0.078,0.154,0.191,0.154,0.313 c0,0.12-0.061,0.237-0.154,0.314L2.492,17.717c-0.07,0.057-0.162,0.087-0.25,0.087l-0.176-0.04 c-0.136-0.065-0.222-0.207-0.222-0.361V0.402C1.844,0.25,1.93,0.107,2.067,0.043z"
                                                    fill="#000000" style="fill: rgb(255, 193, 7);"></path>
                                            </g>
                                            <g id="Capa_1_78_"></g>
                                        </g>
                                    </svg>
                                </td>
                                <td>企業URL</td>
                            </tr>
                            <tr>
                                <td>項目6</td>
                                <td class="d-flex align-items-center justify-content-around">
                                    <input type="text" name="item[6]" value="<?= $form_users_items[6] ?? ''; ?>"
                                        disabled>
                                    <svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg"
                                        xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                                        viewBox="0 0 17.804 17.804"
                                        style="enable-background:new 0 0 17.804 17.804; width: 20px;"
                                        xml:space="preserve">
                                        <g>
                                            <g id="c98_play">
                                                <path
                                                    d="M2.067,0.043C2.21-0.028,2.372-0.008,2.493,0.085l13.312,8.503c0.094,0.078,0.154,0.191,0.154,0.313 c0,0.12-0.061,0.237-0.154,0.314L2.492,17.717c-0.07,0.057-0.162,0.087-0.25,0.087l-0.176-0.04 c-0.136-0.065-0.222-0.207-0.222-0.361V0.402C1.844,0.25,1.93,0.107,2.067,0.043z"
                                                    fill="#000000" style="fill: rgb(255, 193, 7);"></path>
                                            </g>
                                            <g id="Capa_1_78_"></g>
                                        </g>
                                    </svg>
                                </td>
                                <td>電話番号</td>
                            </tr>
                            <tr>
                                <td>項目7</td>
                                <td class="d-flex align-items-center justify-content-around">
                                    <input type="text" name="item[7]" value="<?= $form_users_items[7] ?? ''; ?>"
                                        disabled>
                                    <svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg"
                                        xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                                        viewBox="0 0 17.804 17.804"
                                        style="enable-background:new 0 0 17.804 17.804; width: 20px;"
                                        xml:space="preserve">
                                        <g>
                                            <g id="c98_play">
                                                <path
                                                    d="M2.067,0.043C2.21-0.028,2.372-0.008,2.493,0.085l13.312,8.503c0.094,0.078,0.154,0.191,0.154,0.313 c0,0.12-0.061,0.237-0.154,0.314L2.492,17.717c-0.07,0.057-0.162,0.087-0.25,0.087l-0.176-0.04 c-0.136-0.065-0.222-0.207-0.222-0.361V0.402C1.844,0.25,1.93,0.107,2.067,0.043z"
                                                    fill="#000000" style="fill: rgb(255, 193, 7);"></path>
                                            </g>
                                            <g id="Capa_1_78_"></g>
                                        </g>
                                    </svg>
                                </td>
                                <td>部署名</td>
                            </tr>
                            <tr>
                                <td>項目8</td>
                                <td class="d-flex align-items-center justify-content-around">
                                    <input type="text" name="item[8]" value="<?= $form_users_items[8] ?? ''; ?>"
                                        disabled>
                                    <svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg"
                                        xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                                        viewBox="0 0 17.804 17.804"
                                        style="enable-background:new 0 0 17.804 17.804; width: 20px;"
                                        xml:space="preserve">
                                        <g>
                                            <g id="c98_play">
                                                <path
                                                    d="M2.067,0.043C2.21-0.028,2.372-0.008,2.493,0.085l13.312,8.503c0.094,0.078,0.154,0.191,0.154,0.313 c0,0.12-0.061,0.237-0.154,0.314L2.492,17.717c-0.07,0.057-0.162,0.087-0.25,0.087l-0.176-0.04 c-0.136-0.065-0.222-0.207-0.222-0.361V0.402C1.844,0.25,1.93,0.107,2.067,0.043z"
                                                    fill="#000000" style="fill: rgb(255, 193, 7);"></path>
                                            </g>
                                            <g id="Capa_1_78_"></g>
                                        </g>
                                    </svg>
                                </td>
                                <td>役職</td>
                            </tr>
                            <tr>
                                <td>項目9</td>
                                <td class="d-flex align-items-center justify-content-around">
                                    <input type="text" name="item[9]" value="<?= $form_users_items[9] ?? ''; ?>"
                                        disabled>
                                    <svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg"
                                        xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
                                        viewBox="0 0 17.804 17.804"
                                        style="enable-background:new 0 0 17.804 17.804; width: 20px;"
                                        xml:space="preserve">
                                        <g>
                                            <g id="c98_play">
                                                <path
                                                    d="M2.067,0.043C2.21-0.028,2.372-0.008,2.493,0.085l13.312,8.503c0.094,0.078,0.154,0.191,0.154,0.313 c0,0.12-0.061,0.237-0.154,0.314L2.492,17.717c-0.07,0.057-0.162,0.087-0.25,0.087l-0.176-0.04 c-0.136-0.065-0.222-0.207-0.222-0.361V0.402C1.844,0.25,1.93,0.107,2.067,0.043z"
                                                    fill="#000000" style="fill: rgb(255, 193, 7);"></path>
                                            </g>
                                            <g id="Capa_1_78_"></g>
                                        </g>
                                    </svg>
                                </td>
                                <td>住所</td>
                            </tr>
                        </table>
                        <div class="w-50 d-flex justify-content-between align-items-center">
                            <div>
                                差出アドレス
                                <select name="fromId" id="" style="max-width: 500px;" disabled>
                                    <option value="">未設定</option>
                                    <?php foreach ($froms_data as $from): ?>
                                        <option value="<?= $from['id']; ?>" <?php
                                          if ($form_users_data['fromId'] == $from['id']) {
                                              echo 'selected';
                                          }
                                          ?>>
                                            <?= $from['email']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="w-50 d-flex justify-content-between align-items-center">
                            <div>
                                テンプレート
                                <select name="template" id="" style="max-width: 500px;" disabled>
                                    <option value="">未設定</option>
                                    <?php foreach ($template_data as $template): ?>
                                        <?php if ($template['division'] == 'メール配信'): ?>
                                            <option value="<?= $template['id']; ?>" <?php
                                              if ($form_users_data['template'] == $template['id']) {
                                                  $confirm_mail = $template;
                                                  echo 'selected';
                                              }
                                              ?>>
                                                <?= $template['subject']; ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="d-flex">
                                <button type="button" class="btn btn-primary mx-2" data-bs-toggle="modal"
                                    data-bs-target="#prev_confirm_mail">プレビュー</button>
                                <button id="edit_form" type="button" class="btn btn-dark" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                                <button id="save_form" class="btn btn-success">保存</button>
                            </div>
                        </div>
                    </form>
                    <div class="modal fade" id="prev_confirm_mail" data-bs-backdrop="static" data-bs-keyboard="false"
                        tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">

                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <!-- modal-body -->
                                <div class="modal-body" id="modal_req">
                                    <table class="table table-bordered">
                                        <tr>
                                            <td style="min-width: 120px;">テンプレート名</td>
                                            <td>
                                                <span id=""><?= $confirm_mail['subject'] ?? ''; ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>本文</td>
                                            <td>
                                                <span
                                                    id=""><?= isset($confirm_mail['content']) ? nl2br($confirm_mail['content']) : ''; ?></span>
                                            </td>
                                        </tr>
                                    </table>
                                    <br>
                                    <br>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <div id="tabbody_resend" class="tabbody <?= $tab != 'resend' ? 'd-none' : ''; ?>">
                <br>
                <section>
                    <div class="d-flex justify-content-between align-items-center w-50">
                        <h2>再送メール</h2>
                        <label class="switch">
                            <input type="checkbox" id="resendSwitch_<?php echo $ClientData['id']; ?>"
                                <?= $resend_state == 1 ? 'checked' : ''; ?>>
                            <span class="slider round resend"
                                data-content="<?= $resend_state == 1 ? 'ON' : 'OFF'; ?>"></span>
                        </label>
                    </div>
                    <form action="./back/ch_resend.php" method="post" id="ch_resend">
                        <input type="hidden" name="client_id" value="<?= $client_id; ?>">
                        <table class="table w-50">
                            <tr>
                                <td>テンプレート</td>
                                <td>
                                    <select name="resend_temp" id="template-select" disabled>
                                        <option value="">未設定</option>
                                        <?php foreach ($template_data as $template): ?>
                                            <option value="<?= $template['id']; ?>" <?= $template['id'] == $resend_temp_id ? 'selected' : ''; ?>>
                                                <?= $template['subject']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td rowspan="2">再送タイミング</td>
                                <td>
                                    <select name="resend_date" id="" disabled>
                                        <?php $resend_dates = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
                                        foreach ($resend_dates as $date): ?>
                                            <option value="<?= $date; ?>" <?= ($date == $resend_date) ? 'selected' : ''; ?>>
                                                <?= $date; ?> 日後
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <select name="resend_time" id="" disabled>
                                        <?php $resend_times = [9, 10, 11, 12, 13, 14, 15, 16, 17, 18];
                                        foreach ($resend_times as $time): ?>
                                            <option value="<?= $time; ?>" <?= ($time == $resend_time) ? 'selected' : ''; ?>>
                                                <?= $time; ?> 時
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <div class="w-50 d-flex justify-content-end">
                            <button type="button" id="edit_resend" class="btn btn-dark" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                            <button id="save_resend" class="btn btn-success">保存</button>
                        </div>
                    </form>
                </section>
                <section>
            </div>
            <div id="tabbody_other" class="tabbody <?= $tab != 'other' ? 'd-none' : ''; ?>">
                <br>
                <section>
                    <div class="d-flex align-items-end">
                        <h2>ファビコン</h2>
                        <p class="ms-3">推奨サイズ64px ×64px</p>
                    </div>
                    <form action="./back/ch_favi_logo.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="client_id" value="<?= $client_id; ?>">
                        <div class="drop-zone w-25">
                            <span class="drop-zone__prompt">ここにファイルをドロップするか、ファイルを選択</span>
                            <input type="file" name="ch_favi" id="ch_favi" class="drop-zone__input" required disabled>
                        </div>
                        <div class="d-flex align-items-center mt-2">
                            <?php if (isset($favi_logo['favi_img']) && $favi_logo['favi_img'] != ''): ?>
                                <p class="my-auto">現在のファイル名: <?= $favi_logo['favi_name']; ?></p>
                                <a href="./favi_logos/<?= $favi_logo['favi_img'] ?>" target="_blank" id="prev_favi"
                                    class="btn btn-primary ms-2">プレビュー</a>
                            <?php else: ?>
                                <p class="my-auto">現在のファイル名: 設定なし</p>
                                <a class="btn btn-light ms-2">プレビュー</a>
                            <?php endif; ?>
                            <button id="save_favi" class="btn btn-success ms-2 d-none">保存</button>
                            <button type="button" id="edit_favi" class="btn btn-dark ms-2" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                        </div>
                    </form>
                </section>
                <section>
                    <h2>ライセンス表記</h2>
                    <label class="switch">
                        <input type="checkbox" <?php echo $ClientData['license_display'] === 'ON' ? 'checked' : ''; ?>
                            id="licenseSwitch_<?php echo $ClientData['id']; ?>">
                        <span class="slider round license"
                            data-content="<?php echo $ClientData['license_display'] === 'ON' ? '表示' : '非表示'; ?>"></span>
                    </label>
                </section>
                <section>
                    <h2>広告表示</h2>
                    <label class="switch">
                        <input type="checkbox" <?php echo $ClientData['ad_display'] === 'ON' ? 'checked' : ''; ?>
                            id="adSwitch_<?php echo $ClientData['id']; ?>">
                        <span class="slider round adsetting"
                            data-content="<?php echo $ClientData['ad_display'] === 'ON' ? '表示' : '非表示'; ?>"></span>
                    </label>
                </section>
            </div>
        </main>
    </div>
    <script src="./assets/js/alert.js"></script>
    <script>
        let status = "<?= $ClientData['status']; ?>";

        $('#new_tag').keypress(function (e) {
            if (e.which === 13) { // 13 is the Enter key code
                e.preventDefault();
                checkTag();
            }
        });

        function limit_tag_num() {
            alert('プリプランではタグを3つまでしか設定できません。');
        }

        var resend = document.querySelector('.resend');
        var resendSwitch = document.getElementById('resendSwitch_<?php echo $ClientData['id']; ?>');
        resendSwitch.addEventListener('change', function () {
            if (status == 'Free') {
                alert("無料プランはライセンス表記を非表示にできません。");
                if (this.checked == false) {
                    this.checked = true; // Always keep it checked for 無料プラン
                } else {
                    this.checked = false;
                }
            } else {
                var onOff = this.checked ? 'ON' : 'OFF';
                onOff == 'ON' ? resend.setAttribute('data-content', 'ON') : resend.setAttribute('data-content',
                    'OFF');
                var xhr = new XMLHttpRequest();
                xhr.open('POST', './back/ch_switch_resend_display.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send('switch=' + onOff + '&id=<?php echo $ClientData['id']; ?>');
            }
        });
        var license = document.querySelector('.license');
        var licenseSwitch = document.getElementById('licenseSwitch_<?php echo $ClientData['id']; ?>');
        licenseSwitch.addEventListener('change', function () {
            if (status == 'Free') {
                alert("無料プランはライセンス表記を非表示にできません。");
                if (this.checked == false) {
                    this.checked = true; // Always keep it checked for 無料プラン
                } else {
                    this.checked = false;
                }
            } else {
                var onOff = this.checked ? 'ON' : 'OFF';
                onOff == 'ON' ? license.setAttribute('data-content', '表示') : license.setAttribute('data-content',
                    '非表示');
                var xhr = new XMLHttpRequest();
                xhr.open('POST', './back/ch_switch_license_display.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send('switch=' + onOff + '&id=<?php echo $ClientData['id']; ?>');
            }
        });
        var adSwitch = document.getElementById('adSwitch_<?php echo $ClientData['id']; ?>');
        var adsetting = document.querySelector('.adsetting');
        adSwitch.addEventListener('change', function () {
            if (status == 'Free') {
                alert("無料プランはライセンス表記を非表示にできません。");
                if (this.checked == false) {
                    this.checked = true; // Always keep it checked for 無料プラン
                } else {
                    this.checked = false;
                }

            } else {
                var onOff = this.checked ? 'ON' : 'OFF';
                onOff == 'ON' ? adsetting.setAttribute('data-content', '表示') : adsetting.setAttribute(
                    'data-content', '非表示');
                var xhr = new XMLHttpRequest();
                xhr.open('POST', './back/ch_switch_ad_display.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send('switch=' + onOff + '&id=<?php echo $ClientData['id']; ?>');
            }
        });

        var bTemplateId = '<?php echo $b_template_id; ?>';
        var bSendingTime = '<?php echo $b_sending_time; ?>';

        // 編集中かどうかの状態を追跡する変数
        var isEditing = false;

        // Selecting elements
        var tempSwitch = document.getElementById('tempSwitch_<?php echo $ClientData['id']; ?>');
        let editingBtn = document.getElementById('editing_tt');
        let busLinkBtn = document.getElementById('bus_link_btn');
        let hourInput = document.getElementById('hour');
        let templateSelect = document.getElementById('template-select');
        var temp = document.querySelector('.slider.temp');
        let saveButton = document.getElementById('save_button');


        // Function to toggle controls based on switch state and PHP variables
        function toggleControls(isChecked) {
            var shouldEnableBusLinkBtn = bTemplateId && bSendingTime;
            if (busLinkBtn != null) {
                busLinkBtn.disabled = isChecked && !shouldEnableBusLinkBtn;
            }
            // 編集中のみ送信時間とテンプレートを無効化
            if (isEditing) {
                hourInput.disabled = !isChecked;
                templateSelect.disabled = !isChecked;
            }
        }

        // Initial control setting
        toggleControls(tempSwitch.checked);

        // Event listener for tempSwitch
        tempSwitch.addEventListener('change', function () {
            const onOff = this.checked ? 'ON' : 'OFF';
            temp.setAttribute('data-content', this.checked ? '配信中' : '停止中');
            toggleControls(this.checked);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', './back/ch_switch_temp_display.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('switch=' + onOff + '&id=<?php echo $ClientData['id']; ?>');

            if (!this.checked) {
                Alert('', '警告', 'OFFの時はメールは送信されません。', 'danger', true, true, 'pageMessages');
            }
        });

        // Function to enable editing
        function enableEditing() {
            editingBtn.style.display = 'none';
            $('#tabbody_link select').prop('disabled', false)
            saveButton.style.display = 'inline-block';
            var tempSwitch = document.getElementById('tempSwitch_<?php echo $ClientData['id']; ?>');
            if (tempSwitch) {
                tempSwitch.disabled = false; // チェックボックスを有効にする
            }
        }

        function scheduleSave() {
            document.getElementById('schedule_save_form').submit()
        }
        function chTagModal(id, tag_name, memo) {
            document.getElementById("ch_id").value = id;
            document.getElementById("del_tag_id").value = id;
            document.getElementById("ch_tag_name").value = tag_name;
            document.getElementById("ch_memo").value = memo;
        }

        let inputs = $('#ch_tag input, #ch_tag textarea');
        $('#save_tag').hide();

        $('#edit_tag').click(function () {
            inputs.prop('disabled', false);
            $('#edit_tag').hide();
            $('#save_tag').show();
        })

        $('#close_tag').click(function () {
            inputs.prop('disabled', true);
            $('#edit_tag').show();
            $('#save_tag').hide();
        })

        $('#del_tag').click(function () {
            $('#delTagForm').submit();
        })

        function cancelEditTag() {
            inputs.prop('disabled', true);
            $('#edit_tag').show();
            $('#save_tag').hide();
        }

        $('#save_form').hide();
        $('#edit_form').click(function () {
            $('#ch_form input, #ch_form select').prop('disabled', false);
            $('#edit_form').hide();
            $('#save_form').show();
        })

        $('#save_chatwork').hide();
        $('#edit_chatwork').click(function () {
            $('#ch_chatwork input').prop('disabled', false);
            $('#edit_chatwork').hide();
            $('#save_chatwork').show();
        })

        $('#save_resend').hide();
        $('#edit_resend').click(function () {
            $('#ch_resend select').prop('disabled', false);
            $('#edit_resend').hide();
            $('#save_resend').show();
        })

        $('#edit_favi').click(function () {
            $('#ch_favi').prop('disabled', false);
            $('#edit_favi').addClass('d-none');
            $('#save_favi').removeClass('d-none');
        })

        $('#edit_logo').click(function () {
            $('#ch_logo').prop('disabled', false);
            $('#edit_logo').addClass('d-none');
            $('#save_logo').removeClass('d-none');
        })

    </script>
    <script src="./assets/js/client/setting.js"></script>
    <script>
        $(document).ready(function () {
            $('#tag_table').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Japanese.json"
                },
                "dom": '<"top"p>rt<"bottom"i><"clear">',
                "order": [
                    [2, 'desc']
                ]
            });
        });
    </script>
    <script>
        document.querySelectorAll(".drop-zone__input").forEach((inputElement) => {
            const dropZoneElement = inputElement.closest(".drop-zone");

            dropZoneElement.addEventListener("click", (e) => {
                inputElement.click();
            });

            inputElement.addEventListener("change", (e) => {
                if (inputElement.files.length) {
                    updateThumbnail(dropZoneElement, inputElement.files[0]);
                }
            });

            dropZoneElement.addEventListener("dragover", (e) => {
                e.preventDefault();
                dropZoneElement.classList.add("drop-zone--over");
            });

            ["dragleave", "dragend"].forEach((type) => {
                dropZoneElement.addEventListener(type, (e) => {
                    dropZoneElement.classList.remove("drop-zone--over");
                });
            });

            dropZoneElement.addEventListener("drop", (e) => {
                e.preventDefault();

                if (e.dataTransfer.files.length) {
                    inputElement.files = e.dataTransfer.files;
                    updateThumbnail(dropZoneElement, e.dataTransfer.files[0]);
                }

                dropZoneElement.classList.remove("drop-zone--over");
            });
        });

        function updateThumbnail(dropZoneElement, file) {
            let thumbnailElement = dropZoneElement.querySelector(".drop-zone__thumb");

            // First time - remove the prompt
            if (dropZoneElement.querySelector(".drop-zone__prompt")) {
                dropZoneElement.querySelector(".drop-zone__prompt").remove();
            }

            // First time - there is no thumbnail element, so lets create it
            if (!thumbnailElement) {
                thumbnailElement = document.createElement("div");
                thumbnailElement.classList.add("drop-zone__thumb");
                dropZoneElement.appendChild(thumbnailElement);
            }

            thumbnailElement.dataset.label = file.name;

            // Show thumbnail for image files
            if (file.type.startsWith("image/")) {
                const reader = new FileReader();

                reader.readAsDataURL(file);
                reader.onload = () => {
                    thumbnailElement.style.backgroundImage = `url('${reader.result}')`;
                };
            } else {
                thumbnailElement.style.backgroundImage = null;
            }
        }

    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const linkBtn = document.getElementById('form_link_btn');
            const userId = `<?= $client_id; ?>`;

            let googleScreen;

            // 名刺連携開始ボタンのイベントハンドラ
            linkBtn?.addEventListener('click', () => {
                let url = `https://in.doc1.jp/google-api-php-client/api/contacts/index.php?client_id=${userId}`;
                let width = 500;
                let height = 600;
                let left = window.screenX + (window.outerWidth - width) / 2;
                let top = window.screenY + (window.outerHeight - height) / 2;
                googleScreen = window.open(url, "_blank", "toolbar=yes,resizable=yes,top=" + top + ",left=" + left + ",width=" + width + ",height=" + height + "");
                const checkGoogleScreenClosed = () => {
                    if (googleScreen && googleScreen.closed) {
                        window.location.reload()
                        window.removeEventListener('focus', checkGoogleScreenClosed);
                    }
                };

                window.addEventListener('focus', checkGoogleScreenClosed);
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