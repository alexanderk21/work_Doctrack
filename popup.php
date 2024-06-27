<?php
session_start();

if (isset($_SESSION['csv_data']))
    unset($_SESSION["csv_data"]);
if (isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);

require ('common.php');

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    if ($ClientUser['role'] == 1) {
        $tag_array = [];
        $stmt = $db->prepare("SELECT * FROM tag_sample WHERE client_id=? ");
        $stmt->execute([$client_id]);
        $tags = $stmt->fetchAll();
        foreach ($tags as $t) {
            $tag_array[] = $t['tag_name'];
        }
        $tag_array[] = '未選択';
    } else {
        $tag_array = explode(',', $ClientUser['tags']);
    }

    if (isset($_GET['tag']) && $_GET['tag'] != '') {
        $tag = $_GET['tag'];
        $tag_ids = [];
        if ($tag !== '未選択') {
            $sql = "SELECT * FROM tags WHERE table_name = 'popups' AND tags LIKE '%$tag%'";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll();
            foreach ($result as $row) {
                $tag_ids[] = $row['table_id'];
            }
        }
    }

    $sql = "SELECT * FROM popups WHERE deleted = 0 AND cid=?";

    if (!empty($tag_ids)) {
        $placeholders = implode(',', array_fill(0, count($tag_ids), '?'));
        $sql .= " AND id IN ($placeholders)";
    }

    $stmt = $db->prepare($sql);
    $params = [$client_id];
    if (!empty($tag_ids)) {
        $params = array_merge($params, $tag_ids);
    }
    $stmt->execute($params);
    $popups_data = $stmt->fetchAll();
    if (isset($tag) && empty($tag_ids) && $tag !== '未選択') {
        $popups_data = [];
    }

    $stmt = $db->prepare("SELECT pdf_id FROM pdf_versions WHERE deleted = 0 AND  cid=?");
    $stmt->execute([$client_id]);
    $pdf_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $unique_pdf_ids = array_unique($pdf_ids);

    // popupsテーブルで使用されているpdf_idを取得
    $stmt = $db->prepare("SELECT pdf_id FROM popups WHERE deleted = 0 AND cid=?");
    $stmt->execute([$client_id]);
    $used_pdf_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // pdf_versionsテーブルから、指定されたcidに関連するpdf_idを取得
    $stmt = $db->prepare("SELECT * FROM pdf_versions WHERE deleted = 0 AND  cid=? AND (pdf_id, uploaded_at) IN (SELECT pdf_id, MAX(uploaded_at) FROM pdf_versions WHERE cid=? GROUP BY pdf_id)");
    $stmt->bindValue(1, $client_id, PDO::PARAM_STR);
    $stmt->bindValue(2, $client_id, PDO::PARAM_STR);
    $stmt->execute();
    $pdf_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}

$max = (int) $ClientData['max_pops'];
$current = count($used_pdf_ids);
$limit = (int) $ClientData['max_pops'] - count($used_pdf_ids);

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
        /* background-position: center; */
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

    <div class="wrapper">
        <?php require ('menu.php'); ?>
        <main>
            <div class="d-flex justify-content-between">
                <div class="header-with-help">
                    <h1>トラッキング設定</h1>
                </div>
                <?php require ('dropdown.php'); ?>
            </div>
            <div>
                <a class="tab_btn btn btn-primary" href="./pdf.php">PDFファイル</a>
                <a class="tab_btn btn btn-primary" href="./redirect.php">リダイレクト</a>
                <a class="tab_btn btn btn-secondary" href="./popup.php">ポップアップ</a>
                <a class="tab_btn btn btn-primary" href="./cms.php">CMS</a>
            </div>
            <br>
            <?php require ('./common/restrict_modal.php'); ?>

            <input type="hidden" id="type" value="<?= $ClientData['max_pops_type'] ?>">
            <input type="hidden" id="max_value" value="<?= $ClientData['max_pops'] ?>">
            <input type="hidden" id="used_value" value="<?= count($popups_data) ?>">
            <div class="d-flex">
                <?php if ($ClientData['max_pops_type'] == 1 && $limit <= 0): ?>
                    <?php require_once ('./limitModal.php'); ?>
                    <button type="button" class="btn btn-primary" id="modal_button" data-bs-toggle="modal"
                        data-bs-target="#limitModal">新規登録</button>
                <?php else: ?>
                    <button type="button" class="btn btn-primary" id="modal_button" data-bs-toggle="modal"
                        data-bs-target="#new">新規登録</button>
                <?php endif; ?>

                <form action="" method="get">
                    <label for="search_tag" class="ms-3">タグ検索</label>
                    <select name="tag" id="">
                        <option value="">全選択</option>
                        <?php foreach ($tag_array as $each): ?>
                            <option value="<?= $each; ?>" <?= (isset($tag) && $tag == $each) ? 'selected' : '' ?>>
                                <?= $each; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-success">検索</button>
                </form>

            </div>
            <br>
            <br>

            <table class="table" id="popup_table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>タイトル</th>
                        <th>PDFタイトル</th>
                        <th>トリガー</th>
                        <th>クリック数</th>
                        <th>更新日時</th>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach (array_reverse($popups_data, true) as $key => $row):

                        try {
                            $db = new PDO($dsn, $user, $pass, [
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            ]);
                            $stmt = $db->prepare("SELECT * FROM pdf_versions WHERE pdf_id=?");
                            $stmt->execute([$row['pdf_id']]);
                            $pdf_versions_data = $stmt->fetch(PDO::FETCH_ASSOC);

                            $query = "SELECT COUNT(*) FROM popup_access WHERE popup_id = :popup_id";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':popup_id', $row['id'], PDO::PARAM_INT);
                            $stmt->execute();
                            $click_cnt = $stmt->fetchColumn();

                            $stmt = $db->prepare("SELECT * FROM tags WHERE table_name = 'popups' AND table_id=?");
                            $stmt->execute([$row['id']]);
                            $tag_data_ = $stmt->fetch(PDO::FETCH_ASSOC);

                            if (!isset($tag_data_['tags'])) {
                                $popup2tag = '未選択';
                            } else {
                                $popup2tag = $tag_data_['tags'];
                            }

                            if (isset($_GET['tag']) && $_GET['tag'] == '未選択') {
                                if ($popup2tag != '未選択') {
                                    continue;
                                }
                            } else {
                                $check_tag = explode(',', $popup2tag);
                                $commonValues = array_intersect($check_tag, $tag_array);
                                if (empty($commonValues)) {
                                    continue;
                                }
                            }

                        } catch (PDOException $e) {
                            echo '接続失敗' . $e->getMessage();
                            exit();
                        }
                        ?>
                        <tr>
                            <td>
                                <?= $key + 1 ?>
                            </td>
                            <td>
                                <?= $row['title'] ?>
                            </td>
                            <td>
                                <?= $pdf_versions_data['title'] ?>
                            </td>
                            <td>
                                <?= $row['popup_trigger'] ?>
                            </td>
                            <td>
                                <?= $click_cnt ?>
                            </td>
                            <td>
                                <?= substr($row['created_at'], 0, 16) ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#detail"
                                    onClick="handleClick(`<?= $row['id']; ?>`,`<?= $row['title']; ?>`,`<?= $pdf_versions_data['title']; ?>`,`<?= $row['url']; ?>`,`<?= $row['popup_trigger']; ?>`,`<?= $row['trigger_parameter']; ?>`,`<?= $row['trigger_parameter2']; ?>`,`<?= $row['memo']; ?>`,`<?= $row['img_name']; ?>`,`<?= $popup2tag; ?>`);">
                                    詳細</button>

                            </td>
                            <td>
                                <a href="./access_analysis.php?popup_id=<?= $row['pdf_id']; ?>"
                                    class="btn btn-primary">解析</a>
                            </td>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" <?php if ($row['switch'] == "ON")
                                        echo "checked"; ?>
                                        id="switch_<?php echo $row['id']; ?>" <?= $client_id == $ClientUser['id'] ? '' : 'disabled'; ?>>
                                    <span class="slider round switch_<?php echo $row['id']; ?>"
                                        data-content="<?php echo $row['switch'] === 'ON' ? '有効' : '無効'; ?>"></span>
                                </label>
                                <script>
                                    document.getElementById('switch_<?php echo $row['id']; ?>').addEventListener('change', function () {
                                        var onOff = this.checked ? 'ON' : 'OFF';
                                        onOff == 'ON' ? document.querySelector('.switch_<?php echo $row['id']; ?>').setAttribute('data-content', '有効') : document.querySelector('.switch_<?php echo $row['id']; ?>').setAttribute('data-content', '無効');
                                        // Ajaxリクエストを使用してDBを更新する
                                        var xhr = new XMLHttpRequest();
                                        xhr.open('POST', './back/ch_switch.php', true);
                                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                        xhr.send('switch=' + onOff + '&id=<?php echo $row['id']; ?>');
                                    });
                                </script>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
    </div>
    <?php
    $tag_array = array_diff($tag_array, ["未選択"]);
    ?>
    <div class="modal fade" id="new" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-labelledby="staticBackdropLabel" aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered" style="max-width: 600px;" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">新規登録</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <!-- modal-body -->
                <div class="modal-body" id="modal_req_new">
                    <form action="./back/new_popup.php" method="post" id="new_form" enctype="multipart/form-data">
                        <table>
                            <tr>
                                <td>タイトル</td>
                                <td>
                                    <span class="require-note">必須</span><input type="text" name="title">
                                </td>

                            </tr>
                            <tr>
                                <td>PDFタイトル</td>
                                <td><span class="require-note">必須</span>
                                    <select name="pdf_id" id="pdf-title" onchange="newButtonActive()">
                                        <option value="" selected disabled>未選択</option>
                                        <?php foreach ($pdf_data as $data): ?>
                                            <option value="<?= $data['pdf_id'] ?>">
                                                <?= $data['title'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>画像挿入</td>
                                <td class="d-flex align-items-center">
                                    <span class="require-note" style="width: 28px;">必須</span>
                                    <div class="drop-zone w-100">
                                        <span class="drop-zone__prompt">ここにファイルをドロップするか、ファイルを選択</span>
                                        <input type="file" name="popup_img" id="popup_img" class="drop-zone__input"
                                            required>
                                        <div class="drop-zone__thumb d-none" id="new_img"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>URL</td>
                                <td>
                                    <span class="require-note">必須</span><input type="text" name="url"
                                        placeholder="URLを入力してください。" required>
                                </td>
                            </tr>
                            <tr>
                                <td>トリガー</td>
                                <td><span class="require-note">必須</span>
                                    <select name="trigger" onchange="updateTriggerParameterLabel()">
                                        <option value="閲覧時間">閲覧時間</option>
                                        <option value="ページ数">ページ数</option>
                                        <option value="アクセス数">アクセス数</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>数値</td>
                                <td><span class="require-note">必須</span>
                                    <input type="number" min="1" name="trigger_parameter" required>
                                    <span id="trigger_parameter_label">秒</span>
                                </td>
                            </tr>
                            <tr id="t2_new" style="display: none;">
                                <td></td>
                                <td><span class="require-note">必須</span>
                                    <input type="number" min="1" name="trigger_parameter2">
                                    <span>秒後に表示</span>
                                </td>
                            </tr>
                            <tr class="d-none">
                                <td>表示説明文</td>
                                <td>
                                    <textarea name="memo" cols="30" rows="10"></textarea>
                                </td>
                            </tr>
                            <tr class="mb-2">
                                <td class="p-1">タグ</td>
                                <td class="p-1">
                                    <?php $i = 0;
                                    foreach ($tag_array as $each):
                                        $i++; ?>
                                        <input type="checkbox" name="select_tags[<?= $i; ?>]" value="<?= $each; ?>" />
                                        <label for="">
                                            <?= $each; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        </table>
                        <br>
                        <br>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="cid" value="<?= $client_id ?>">
                    <button type="submit" id="new-btn" class="btn btn-secondary">新規登録</button>
                    </form>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="detail" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content" id="modal_content">
                <div class="modal-header" id="modal_header">
                    <h5 class="modal-title">詳細</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                        onclick="cancelEditing()"></button>
                </div>
                <!-- modal-body -->
                <form action="back/ch_popup.php" method="post" enctype="multipart/form-data">
                    <div class="modal-body" id="modal_req">
                        <table>
                            <tr>
                                <td>タイトル</td>
                                <td class="space-1">
                                    <span name="detail_title" id="title_d"></span>
                                    <!-- <input type="text" name="detail_title" id="title_d" onblur="detailTitleValidation()" disabled> -->
                                </td>
                            </tr>
                            <tr>
                                <td>PDFタイトル</td>
                                <td class="space-1">
                                    <span id="pdf_title"></span>
                                </td>
                            </tr>
                            <tr>
                                <td>画像挿入</td>
                                <td class="d-flex align-items-center">
                                    <span class="require-note" style="width: 28px;">必須</span>
                                    <div class="drop-zone ch_drop-zone w-100">
                                        <span
                                            class="drop-zone__prompt ch_drop-zone__prompt">ここにファイルをドロップするか、ファイルを選択</span>
                                        <input type="file" name="popup_img" id="popup_img_ch" class="drop-zone__input"
                                            disabled>
                                        <div class="drop-zone__thumb ch_drop-zone__thumb"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>URL</td>
                                <td>
                                    <span class="require-note">必須</span>
                                    <input type="text" name="url" id="url_d" required disabled>
                                </td>
                            </tr>
                            <tr>
                                <td>トリガー</td>
                                <td class="space-1">
                                    <select name="popup_trigger" id="popup_trigger_d"
                                        onchange="updateTriggerParameterLabel2()" disabled>
                                        <option value="閲覧時間">閲覧時間</option>
                                        <option value="ページ数">ページ数</option>
                                        <option value="アクセス数">アクセス数</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>数値</td>
                                <td class="space-1">
                                    <input type="number" min="1" name="trigger_parameter" id="trigger_parameter_d"
                                        required disabled>
                                    <span id="trigger_parameter_label2"></span>
                                </td>
                            </tr>
                            <tr id="t2">
                                <td></td>
                                <td class="space-1">
                                    <input type="number" min="1" name="trigger_parameter2" id="trigger_parameter2_d"
                                        disabled>
                                    <span>秒後に表示</span>
                                </td>
                            </tr>
                            <tr>
                                <td>表示説明文</td>
                                <td class="space-1">
                                    <textarea name="memo" cols="30" rows="10" id="memo_d" disabled></textarea>
                                </td>
                            </tr>
                            <tr>
                                <td>タグ</td>
                                <td id="tagContainer" class="d-none">
                                    <?php $i = 0;
                                    foreach ($tag_array as $each):
                                        $i++; ?>
                                        <input type="checkbox" name="detail_tags[<?= $i; ?>]" value="<?= $each; ?>"
                                            disabled />
                                        <label for="">
                                            <?= $each; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </td>
                                <td id="currentTags"></td>
                            </tr>
                        </table>
                        <br>
                        <br>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="id" id="id_d">
                        <div class="w-100 d-flex justify-content-between">
                            <div>
                                <button type="button" id="delete_pdf" class="btn btn-danger" onclick="deletePopup()"
                                    <?= in_array('削除', $func_limit) ? 'disabled' : ''; ?>>削除</button>
                            </div>
                            <div class="d-flex">
                                <button type="button" id="edit_button" class="btn btn-dark" onclick="enableEditing()"
                                    <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                                <button type="submit" id="save_button" class="btn btn-success"
                                    style='display:none'>保存</button>
                                <button type="button" class="btn btn-secondary ms-2" onclick="cancelEditing()"
                                    data-bs-dismiss="modal">閉じる</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- <script src="./assets/js/client/restrict.js"></script> -->
    <script>
        const popupsData = <?= json_encode($popups_data) ?>;
        const titleInput = document.querySelector('input[name="title"]');
        const submitButton = document.querySelector('button[type="submit"]');
        const detailTitleInput = document.querySelector('input[name="detail_title"]');
        const tp_2 = document.getElementById("trigger_parameter2_d");

        let editButton = document.getElementById('edit_button');
        let saveButton = document.getElementById('save_button');
        // let closeBtn = document.getElementById('close');

        titleInput.addEventListener('blur', () => {
            const title = titleInput.value;
            const existingPopup = popupsData.find(popup => popup.title === title);

            if (existingPopup) {
                alert('同じタイトルのポップアップが既に存在します。別のタイトルを入力してください。');
                titleInput.value = '';
                submitButton.disabled = true;
            } else {
                submitButton.disabled = false;
            }
        });

        let inputs = {};

        function handleClick(tracking_id, title, pdf_title, url, popup_trigger, trigger_parameter, trigger_parameter2, memo, img_name, tags) {
            document.getElementById("id_d").value = tracking_id;
            document.getElementById("title_d").innerHTML = title;
            document.getElementById("pdf_title").textContent = pdf_title;
            document.getElementById("url_d").value = url;
            document.getElementById("popup_trigger_d").value = popup_trigger;
            document.getElementById("trigger_parameter_d").value = trigger_parameter;
            document.getElementById("trigger_parameter2_d").value = trigger_parameter2;
            document.getElementById("memo_d").value = memo;
            imageURL = './popup_img/' + tracking_id + '.png';
            var img = new Image();
            img.src = imageURL;

            img.onload = function () {
                var imageWidth = img.width;
                var imageHeight = img.height;
                var rate = imageWidth / imageHeight;
                if (rate > 1.5) {
                    setWidth = '300px';
                    setHeight = (300 / rate) + 'px';
                } else {
                    setWidth = (200 * rate) + 'px';
                    setHeight = '200px';
                }
                $(".ch_drop-zone").removeClass('w-100');
                $(".ch_drop-zone").css("width", setWidth);
                $(".ch_drop-zone").css("height", setHeight);
            };

            $(".ch_drop-zone__prompt").hide();
            $(".ch_drop-zone__thumb").show();
            $(".ch_drop-zone__thumb").css("background-image", "url('" + imageURL + "')");

            document.getElementById("currentTags").innerHTML = tags;
            var tagsArray = tags.split(',');

            var checkboxes = document.querySelectorAll('input[name^="detail_tags"]');

            checkboxes.forEach(function (checkbox) {
                if (tagsArray.indexOf(checkbox.value) !== -1) {
                    checkbox.checked = true;
                } else {
                    checkbox.checked = false;
                }
            });
            // document.getElementById("img_link").setAttribute('href', './popup_img/' + tracking_id + '.png');

            const triggerParameterLabel = document.getElementById("trigger_parameter_label2");

            const t2 = document.getElementById("t2");

            if (popup_trigger === "閲覧時間") {
                triggerParameterLabel.textContent = "秒";
                t2.style.display = 'none';
                tp_2.disabled = true;
                inputs = document.querySelectorAll('#modal_req input:not(#trigger_parameter2_d), #modal_req textarea');

            } else if (popup_trigger === "ページ数") {
                triggerParameterLabel.textContent = "ページ";
                t2.style.display = 'table-row';
                t2.setAttribute("required", true);
                inputs = document.querySelectorAll('#modal_req input, #modal_req textarea');
            } else if (popup_trigger === "アクセス数") {
                triggerParameterLabel.textContent = "回";
                t2.style.display = 'table-row';
                t2.setAttribute("required", true);
                inputs = document.querySelectorAll('#modal_req input, #modal_req textarea');
            }

            handleSelect();
        }

        function handleSelect() {
            const selectElement = document.getElementById('popup_trigger_d');

            if (editButton.style.display === 'none' && saveButton.style.display === 'inline-block') {
                selectElement.disabled = false;
            } else {
                selectElement.disabled = true;
            }
        }

        function enableEditing() {
            inputs.forEach(input => {
                input.disabled = false;
            });
            $('#tagContainer').removeClass('d-none');
            $('#currentTags').addClass('d-none');
            editButton.style.display = 'none';
            saveButton.style.display = 'inline-block';
            $(".ch_drop-zone").addClass('w-100');
            $(".ch_drop-zone__prompt").show();
            $(".ch_drop-zone__thumb").hide();
            handleSelect();
        }

        function cancelEditing() {
            inputs.forEach(input => {
                input.disabled = true;
            });
            tp_2.disabled = true;
            saveButton.style.display = "none";
            editButton.style.display = "block";
            $('#tagContainer').addClass('d-none');
            $('#currentTags').removeClass('d-none');
            let dropZoneElement = document.querySelector(".drop-zone");
            let thumbnailElement = dropZoneElement.querySelector(".drop-zone__thumb");
            if (thumbnailElement) {
                thumbnailElement.remove();
                // Optionally, you can also reset the input element value if you want
                let inputElement = dropZoneElement.querySelector(".drop-zone__input");
                if (inputElement) {
                    inputElement.value = "";
                }
            }
        }

        function updateTriggerParameterLabel() {
            const triggerSelect = document.getElementsByName("trigger")[0];
            const triggerParameterLabel = document.getElementById("trigger_parameter_label");
            const t2 = document.getElementById("t2_new");

            if (triggerSelect.value === "閲覧時間") {
                triggerParameterLabel.textContent = "秒";
                t2.style.display = 'none';
                t2.removeAttribute("required");
                tp_2.disabled = true;
                inputs = document.querySelectorAll('#modal_req input:not(#trigger_parameter2_d), #modal_req textarea');
            } else if (triggerSelect.value === "ページ数") {
                triggerParameterLabel.textContent = "ページ";
                t2.style.display = 'table-row';
                t2.setAttribute("required", true);
                tp_2.disabled = false;
                inputs = document.querySelectorAll('#modal_req input, #modal_req textarea');
            } else if (triggerSelect.value === "アクセス数") {
                triggerParameterLabel.textContent = "回";
                t2.style.display = 'table-row';
                t2.setAttribute("required", true);
                tp_2.disabled = false;
                inputs = document.querySelectorAll('#modal_req input, #modal_req textarea');
            }
        }

        // 初期表示時にもラベルを更新する
        updateTriggerParameterLabel();

        function updateTriggerParameterLabel2() {
            const triggerSelect = document.getElementsByName("popup_trigger")[0];
            const triggerParameterLabel = document.getElementById("trigger_parameter_label2");
            const t2 = document.getElementById("t2");

            if (triggerSelect.value === "閲覧時間") {
                triggerParameterLabel.textContent = "秒";
                t2.style.display = 'none';
                t2.removeAttribute("required");
                tp_2.disabled = true;
            } else if (triggerSelect.value === "ページ数") {
                triggerParameterLabel.textContent = "ページ";
                t2.style.display = 'table-row';
                t2.setAttribute("required", true);
                tp_2.disabled = false;
            } else if (triggerSelect.value === "アクセス数") {
                triggerParameterLabel.textContent = "回";
                t2.style.display = 'table-row';
                t2.setAttribute("required", true);
                tp_2.disabled = false;
            }
        }

        function detailTitleValidation() {

            const title = detailTitleInput.value;
            const existingPopup = popupsData.find(popup => popup.title === title);

            if (existingPopup) {
                alert('同じタイトルのリダイレクトが既に存在します。別のタイトルを入力してください。');
                detailTitleInput.value = '';
                saveButton.disabled = true;
            } else {
                saveButton.disabled = false;
            }
        };

        function deletePopup() {
            var id = document.getElementById('id_d').value;
            var result = confirm("データを削除してもよろしいですか？");
            if (result) {
                window.location.href = "./back/del_popup.php?id=" + id;
            }
        }

        $('#popup_img').change(function () {
            $('#new_img').removeClass('d-none');
        })
    </script>
    <script>
        $(document).ready(function () {
            $('#popup_table').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Japanese.json"
                },
                "dom": '<"top"p>rt<"bottom"i><"clear">',
                "order": [
                    [5, 'desc']
                ]
            });
        });
    </script>
    <script>
        document.querySelectorAll(".drop-zone__input").forEach((inputElement) => {
            const dropZoneElement = inputElement.closest(".drop-zone");

            function updateThumbnail(dropZoneElement, file) {
                let thumbnailElement = dropZoneElement.querySelector(".drop-zone__thumb");

                if (dropZoneElement.querySelector(".drop-zone__prompt")) {
                    dropZoneElement.querySelector(".drop-zone__prompt").remove();
                }

                thumbnailElement.dataset.label = file.name;

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

            const olderImageURL = "path/to/older/image.jpg";
            const thumbnailElement = dropZoneElement.querySelector(".drop-zone__thumb");
            thumbnailElement.style.backgroundImage = `url('${olderImageURL}')`;

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
    </script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
        crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI"
        crossorigin="anonymous"></script>
</body>

</html>