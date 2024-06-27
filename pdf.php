<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (isset($_SESSION['csv_data']))
    unset($_SESSION["csv_data"]);
if (isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);

require ('common.php');

$per_page = 10; // 1ページに表示するログの数
$current_page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? $_GET['page'] : 1;
$offset = ($current_page - 1) * $per_page;

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
            $sql = "SELECT * FROM tags WHERE table_name = 'pdf_versions' AND tags LIKE '%$tag%'";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll();
            if (!empty($result)) {
                foreach ($result as $row) {
                    $tag_ids[] = $row['table_id'];
                }
            }
        }
    }

    // クエリにLIMITとOFFSETを追加
    $sql = "SELECT * FROM pdf_versions WHERE deleted = 0 AND cid=? AND (pdf_id, uploaded_at) IN (SELECT pdf_id, MAX(uploaded_at) FROM pdf_versions WHERE cid=? GROUP BY pdf_id)";

    if (!empty($tag_ids)) {
        $placeholders = implode(',', array_fill(0, count($tag_ids), '?'));
        $sql .= " AND pdf_version_id IN ($placeholders)";
    }

    // $sql .= " ORDER BY pdf_version_id DESC LIMIT ? OFFSET ?";
    $sql .= " ORDER BY pdf_version_id DESC";
    $stmt = $db->prepare($sql);

    $params = [$client_id, $client_id];
    if (!empty($tag_ids)) {
        $params = array_merge($params, $tag_ids);
    }

    // $params = array_merge($params, [$per_page, $offset]);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->execute();
    $pdf_versions_data = $stmt->fetchAll();

    if (isset($tag) && empty($tag_ids) && $tag !== '未選択') {
        $pdf_versions_data = [];
    }
    $sql_count = "SELECT COUNT(*) as total_count FROM pdf_versions WHERE deleted = 0 AND cid=:client_id";
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->bindValue(':client_id', $client_id, PDO::PARAM_STR);
    $stmt_count->execute();
    $result = $stmt_count->fetch();
    $current = $result['total_count'];
    // $current = count($pdf_versions_data);
    $total_pages = ceil($current / $per_page);

    $max = $ClientData['max_pdfs'];
    $limit = $ClientData['max_pdfs'] - $current;
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}

?>
<?php require ('header.php'); ?>
<style>
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
    <div id="pageMessages">

    </div>
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
                <a class="tab_btn btn btn-secondary" href="./pdf.php">PDFファイル</a>
                <a class="tab_btn btn btn-primary" href="./redirect.php">リダイレクト</a>
                <a class="tab_btn btn btn-primary" href="./popup.php">ポップアップ</a>
                <a class="tab_btn btn btn-primary" href="./cms.php">CMS</a>
            </div>
            <br>
            <?php require ('./common/restrict_modal.php'); ?>
            <div class="modal fade" id="new" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                aria-labelledby="staticBackdropLabel" aria-hidden="true">

                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">新規登録</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <!-- modal-body -->
                        <form action="./back/new_pdf.php" method="post" enctype="multipart/form-data">
                            <div class="modal-body" id="modal_req_new">
                                <table>
                                    <tr>
                                        <td>タイトル</td>
                                        <td>
                                            <span class="require-note">必須</span><input type="text" name="title"
                                                onblur="titleValidation()" required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>PDFファイル</td>
                                        <td class="d-flex align-items-center">
                                            <span class="require-note" style="width: 28px;">必須</span>
                                            <div class="drop-zone w-100">
                                                <span class="drop-zone__prompt">ここにファイルをドロップするか、ファイルを選択</span>
                                                <input type="file" name="pdf_file" id="pdf_file"
                                                    class="drop-zone__input" required>
                                                <div class="drop-zone__thumb d-none" id="new_img"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="d-none">
                                        <td>表示説明文</td>
                                        <td class="space-1">
                                            <textarea name="memo"></textarea>
                                        </td>
                                    </tr>
                                    <tr class="mb-2">
                                        <td class="p-1">タグ</td>
                                        <td class="p-1">
                                            <?php $i = 0;
                                            foreach ($tag_array as $each):
                                                $i++;
                                                if ($each == '未選択')
                                                    continue; ?>
                                                <input type="checkbox" name="select_tags[<?= $i; ?>]"
                                                    value="<?= $each; ?>" />
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
                                <button type="submit" class="btn btn-primary" disabled>新規登録</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <input type="hidden" id="type" value="<?= $ClientData['max_pdfs_type'] ?>">
            <input type="hidden" id="max_value" value="<?= $ClientData['max_pdfs'] ?>">
            <input type="hidden" id="used_value" value="<?= $current ?>">
            <div class="d-flex align-items-center">

                <?php if ($ClientData['max_pdfs_type'] == 1 && $limit <= 0): ?>
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
                <a href="./pdf_graph.php" class="btn btn-warning ms-3">グラフ表示</a>
            </div>

            <br>

            <table class="table" id="pdf_table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>タイトル</th>
                        <th>アクセス数</th>
                        <th>閲覧時間</th>
                        <th>ページ移動数</th>
                        <th>ＣＴＡクリック数</th>
                        <th>更新日時</th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                    $url_dir = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                    $url_dir = str_replace(basename($url_dir), '', $url_dir);

                    foreach ($pdf_versions_data as $key => $row):

                        $url_ = $row['pdf_id'] . '/';
                        $t_url = $url_dir . 's?t=' . $url_;

                        try {
                            $db = new PDO($dsn, $user, $pass, [
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            ]);
                            $pdf_id = $row['pdf_id'];
                            $cid = $ClientData['id'];

                            $stmt = $db->prepare("SELECT * FROM pdf_versions WHERE deleted = 0 AND  pdf_id=?");
                            $stmt->execute([$pdf_id]);
                            $pdf_version_ids = $stmt->fetchAll();

                            //アクセス数の計算
                            $total_access_cnt = 0;
                            //ページ移動数
                            $total_page_moved = 0;

                            foreach ($pdf_version_ids as $pdf_version_id) {
                                // アクセス数の取得
                                $stmt = $db->prepare("SELECT session_id, cid FROM user_pdf_access WHERE pdf_version_id = :pdf_version_id GROUP BY session_id, cid");
                                $stmt->bindParam(':pdf_version_id', $pdf_version_id['pdf_version_id'], PDO::PARAM_INT);
                                $stmt->execute();

                                $access_cnt = count($stmt->fetchAll());
                                $total_access_cnt += $access_cnt;

                                $stmt->closeCursor();

                                //ページ移動数
                                $stmt = $db->prepare("SELECT page FROM user_pdf_access WHERE pdf_version_id = :pdf_version_id ORDER BY accessed_at");
                                $stmt->bindParam(':pdf_version_id', $pdf_version_id['pdf_version_id'], PDO::PARAM_INT);
                                $stmt->execute();
                                $total_page_moved = count($stmt->fetchAll()) - $access_cnt;

                                $previous_page = -1;
                                $stmt->closeCursor();
                            }

                            //閲覧時間とCTAクリック数
                            $stmt = $db->prepare("
                                                SELECT SUM(duration) as total_duration
                                                FROM user_pdf_access
                                                WHERE pdf_version_id IN (
                                                SELECT pdf_version_id
                                                FROM pdf_versions
                                                WHERE pdf_id = :pdf_id
                                                ) AND cid = :cid
                                            ");
                            $stmt->bindParam(':pdf_id', $pdf_id);
                            $stmt->bindParam(':cid', $cid);
                            $stmt->execute();
                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                            $total_duration = secondsToTime($result['total_duration']);

                            // ポップアップIDを取得するためのクエリ
                            $popup_query = "SELECT id FROM popups WHERE deleted = 0 AND  pdf_id = :pdf_id AND cid = :cid";
                            $popup_stmt = $db->prepare($popup_query);
                            $popup_stmt->bindParam(':pdf_id', $pdf_id, PDO::PARAM_STR);
                            $popup_stmt->bindParam(':cid', $cid, PDO::PARAM_STR);
                            $popup_stmt->execute();
                            $popup_ids = $popup_stmt->fetchAll(PDO::FETCH_COLUMN);

                            // ポップアップIDを使用してクリック数を取得するためのクエリ
                            if (!empty($popup_ids)) {
                                $access_query = "SELECT COUNT(*) as total_cta_clicks FROM popup_access WHERE popup_id IN (" . implode(',', array_fill(0, count($popup_ids), '?')) . ")";
                                $access_stmt = $db->prepare($access_query);
                                $access_stmt->execute($popup_ids);
                                $total_cta_clicks = $access_stmt->fetchColumn();
                            } else {
                                $total_cta_clicks = 0;
                            }

                            $stmt = $db->prepare("SELECT * FROM tags WHERE table_name = 'pdf_versions' AND table_id=?");
                            $stmt->execute([$row['pdf_version_id']]);
                            $tag_data_ = $stmt->fetch(PDO::FETCH_ASSOC);

                            if (!isset($tag_data_['tags'])) {
                                $pdf2tag = '未選択';
                            } else {
                                $pdf2tag = $tag_data_['tags'];
                            }

                            if (isset($_GET['tag']) && $_GET['tag'] == '未選択') {
                                if ($pdf2tag != '未選択') {
                                    continue;
                                }
                            } else {
                                $check_tag = explode(',', $pdf2tag);
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
                                <?= ($current_page - 1) * 10 + $key + 1 ?>
                            </td>
                            <td>
                                <?= $row['title'] ?>
                            </td>
                            <td>
                                <?= $total_access_cnt ?>
                            </td>
                            <td>
                                <?= $total_duration ?>
                            </td>
                            <td>
                                <?= $total_page_moved ?>
                            </td>
                            <td>
                                <?= $total_cta_clicks ?>
                            </td>
                            <td>
                                <?= substr($row['updated_at'], 0, 16) ?>
                            </td>
                            <?php if ($row['page_cnt'] != ''): ?>
                                <td>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#detail"
                                        onClick="handleClick(`<?= $row['pdf_version_id']; ?>`,`<?= $row['pdf_id']; ?>`,`<?= $row['title']; ?>`,`<?= $row['memo']; ?>`,`<?= $row['file_name']; ?>`,`<?= $row['pdf_version_id']; ?>`,`<?= $pdf2tag; ?>`,`<?= $row['page_cnt']; ?>`);">
                                        詳細</button>
                                </td>
                                <td>
                                    <form action="access_analysis.php" method="get">
                                        <input type="hidden" name="search_title" value="<?= $row['title'] ?>">
                                        <button type="submit" class="btn btn-primary">解析</button>
                                    </form>
                                </td>
                            <?php else: ?>
                                <td colspan="2" style="text-align: center;">登録処理中</td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <!-- ページネーションリンクを表示 -->
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
                        <form action="back/ch_pdf.php" method="post" enctype="multipart/form-data">
                            <div class="modal-body" id="modal_req">
                                <table>
                                    <tr>
                                        <td style="min-width: 100px;">トラッキングID</td>
                                        <td class="space-1">
                                            <span id="tracking_id_d"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>タイトル</td>
                                        <td>
                                            <span class="require-note">必須</span>
                                            <input type="text" name="detail_title" id="title_d"
                                                onblur="detailTitleValidation()" required disabled>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>PDFファイル</td>
                                        <td class="d-flex align-items-center">
                                            <span class="require-note" style="width: 28px;">必須</span>
                                            <div class="drop-zone ch_drop-zone w-75">
                                                <span
                                                    class="drop-zone__prompt ch_drop-zone__prompt">ここにファイルをドロップするか、ファイルを選択</span>
                                                <input type="file" name="pdf_file" id="pdf_file_ch"
                                                    class="drop-zone__input" disabled>
                                                <div class="drop-zone__thumb ch_drop-zone__thumb"></div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>現在のファイル名：</td>
                                        <td><span id="tracking_id_f"></span></td>
                                    </tr>
                                    <tr>
                                        <td>ページ数</td>
                                        <td class="">
                                            <p class="my-auto" name="page_number" id="page_number_d"></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>表示説明文</td>
                                        <td class="">
                                            <textarea name="memo" id="memo_d" disabled></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>タグ</td>
                                        <td id="tagContainer" class="d-none">
                                            <?php $i = 0;
                                            foreach ($tag_array as $each):
                                                $i++;
                                                if ($each == '未選択') {
                                                    continue;
                                                }
                                                ?>
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
                                <input type="hidden" name="id" id="id_h">
                                <input type="hidden" name="pdf_version_id" id="pdf_version_id">
                                <div class="w-100 d-flex justify-content-between">
                                    <div class="d-flex">
                                        <button type="button" id="delete_pdf" class="btn btn-danger"
                                            onclick="deletePDF()" <?= in_array('削除', $func_limit) ? 'disabled' : ''; ?>>削除</button>
                                        <a id="pdf_link" target="_blank" rel="noopener noreferrer"
                                            class="btn btn-primary">プレビュー</a>
                                    </div>
                                    <div class="d-flex">
                                        <button type="button" id="edit_button" class="btn btn-dark"
                                            onclick="enableEditing()" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                                        <button type="submit" id="save_button" class="btn btn-success"
                                            style='display:none'>保存</button>
                                        <button type="button" class="btn btn-secondary ms-2" onclick="cancelEditing()"
                                            data-bs-dismiss="modal">閉じる</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <form action="./upload_history.php" method="get">
                            <input type="hidden" name="pdf_id" id="u_his_d">
                            <input type="hidden" name="pdf_title" id="u_his_title">
                            <button class="btn btn-primary">アップロード履歴</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let inputs = document.querySelectorAll('#modal_req input, #modal_req textarea');
        let editButton = document.getElementById('edit_button');
        let saveButton = document.getElementById('save_button');

        const pdfVersionsData = <?= json_encode($pdf_versions_data) ?>;
        const titleInput = document.querySelector('input[name="title"]');
        const detailTitleInput = document.querySelector('input[name="detail_title"]')
        const submitButton = document.querySelector('button[type="submit"]');

        function titleValidation() {

            const title = titleInput.value;
            const existingPdfVersion = pdfVersionsData.find(pdfVersion => pdfVersion.title === title);

            if (existingPdfVersion) {
                alert('同じタイトルのPDFファイルが既に存在します。別のタイトルを入力してください。');
                titleInput.value = '';
                submitButton.disabled = true;
            } else {
                submitButton.disabled = false;
            }
        };

        function detailTitleValidation() {

            const title = detailTitleInput.value;
            const existingPdfVersion = pdfVersionsData.find(pdfVersion => pdfVersion.title === title);

            if (existingPdfVersion) {
                alert('同じタイトルのPDFファイルが既に存在します。別のタイトルを入力してください。');
                detailTitleInput.value = '';
                saveButton.disabled = true;
            } else {
                saveButton.disabled = false;
            }
        };

        function enableEditing() {
            inputs.forEach(input => {
                input.disabled = !input.disabled;
            });

            editButton.style.display = editButton.style.display === 'none' ? 'inline-block' : 'none';
            saveButton.style.display = saveButton.style.display === 'none' ? 'inline-block' : 'none';
            $('#tagContainer').removeClass('d-none');
            $('#currentTags').addClass('d-none');
            $(".ch_drop-zone").addClass('w-75');
            $(".ch_drop-zone__prompt").show();
            $(".ch_drop-zone__thumb").hide();
        }

        function handleClick(pdf_version_id, tracking_id, title, memo, file_name, pdf_version_id, tags, page_cnt) {
            document.getElementById("tracking_id_d").innerText = tracking_id;
            document.getElementById("pdf_version_id").value = pdf_version_id;
            document.getElementById("title_d").value = title;
            document.getElementById("page_number_d").innerText = page_cnt + ' ページ';
            document.getElementById("memo_d").value = memo;
            document.getElementById("id_h").value = tracking_id;
            document.getElementById("pdf_link").setAttribute('href', './pdf/' + tracking_id + '_' + pdf_version_id + '.pdf');
            document.getElementById("tracking_id_f").innerText = file_name;
            imageURL = './pdf_img/' + tracking_id + '_' + pdf_version_id + '-0.jpg';

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
                $(".ch_drop-zone").removeClass('w-75');
                $(".ch_drop-zone").css("width", setWidth);
                $(".ch_drop-zone").css("height", setHeight);
            };

            $(".ch_drop-zone__prompt").hide();
            $(".ch_drop-zone__thumb").show();
            $(".ch_drop-zone__thumb").css("background-image", "url('" + imageURL + "')");
            document.getElementById("u_his_d").value = tracking_id;
            document.getElementById("u_his_title").value = title;
            document.getElementById("currentTags").innerText = tags;
            var tagsArray = tags.split(',');

            var checkboxes = document.querySelectorAll('input[name^="detail_tags"]');

            checkboxes.forEach(function (checkbox) {
                if (tagsArray.indexOf(checkbox.value) !== -1) {
                    checkbox.checked = true;
                } else {
                    checkbox.checked = false;
                }
            });
        }

        // let modalBox = document.querySelector('#detail:not(#modal_content)');

        // modalBox.addEventListener('click', function (e) {
        //     if (e.target.tagName == "BUTTON") return;
        //     if (e.target.tagName == "INPUT") return;
        //     if (e.target.tagName == "TEXTAREA") return;
        //     if (e.target.tagName == "SELECT") return;
        //     if (e.target.tagName == "TD") return;
        //     if (e.target.tagName == "FORM") return;
        //     if (e.target.tagName == "DIV" && document.getElementById('detail').getAttribute('aria-modal') != "true") return;
        //     if (document.getElementById('detail').getAttribute('aria-modal') == "true") {
        //         inputs.forEach(input => {
        //             input.disabled = true;
        //         });
        //         saveButton.style.display = "none";
        //         editButton.style.display = "block";
        //     }
        // });

        function cancelEditing() {
            inputs.forEach(input => {
                input.disabled = true;
            });
            saveButton.style.display = "none";
            editButton.style.display = "block";
            $('#tagContainer').addClass('d-none');
            $('#currentTags').removeClass('d-none');
        }

        function deletePDF() {
            var pdf_id = document.getElementById('id_h').value;
            var result = confirm("データを削除してもよろしいですか？");
            if (result) {
                window.location.href = "./back/del_pdf.php?pdf_id=" + pdf_id;
            }
        }

        $('#pdf_file').change(function () {
            $('#new_img').removeClass('d-none');
        })

        $('#new').on('hidden.bs.modal', function () {
            $(this).find('form')[0].reset();
            $(this).find(".drop-zone__thumb").addClass("d-none").css("background-image", "none");
            $(this).find(".drop-zone__prompt").removeClass('d-none');
        });
    </script>
    <script>
        $(document).ready(function () {
            $('#pdf_table').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Japanese.json"
                },
                "dom": '<"top"p>rt<"bottom"i><"clear">',
                "order": [
                    [6, 'desc']
                ]
            });
        });
    </script>
    <script>
        document.querySelectorAll(".drop-zone__input").forEach((inputElement) => {
            const dropZoneElement = inputElement.closest(".drop-zone");
            function updateThumbnail(dropZoneElement, file) {
                $(".ch_drop-zone__prompt").hide();
                $(".ch_drop-zone__thumb").show();
                let thumbnailElement = dropZoneElement.querySelector(".drop-zone__thumb");

                if (dropZoneElement.querySelector(".drop-zone__prompt")) {
                    document.querySelector(".drop-zone__prompt").classList.add('d-none');
                }


                thumbnailElement.classList.remove("d-none");

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

                    const event = new Event('change');
                    inputElement.dispatchEvent(event);
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