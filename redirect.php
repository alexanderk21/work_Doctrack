<?php
session_start();
require ('common.php');

$per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $per_page;

if (isset($_SESSION['csv_data'])) {
    $csv_data = $_SESSION['csv_data'];
    $csv_cnt = count($csv_data);
} else {
    $csv_cnt = 0;
}

if (isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);
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
            $sql = "SELECT * FROM tags WHERE table_name = 'redirects' AND tags LIKE '%$tag%'";
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
    $sql = "SELECT * FROM redirects WHERE deleted = 0 AND cid=?";

    if (!empty($tag_ids)) {
        $placeholders = implode(',', array_fill(0, count($tag_ids), '?'));
        $sql .= " AND id IN ($placeholders)";
    }

    // $sql .= " ORDER BY created_at LIMIT ? OFFSET ?";
    $sql .= " ORDER BY created_at";
    $stmt = $db->prepare($sql);

    $params = [$client_id];
    if (!empty($tag_ids)) {
        $params = array_merge($params, $tag_ids);
    }

    // $params = array_merge($params, [$per_page, $offset]);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->execute();
    $data = $stmt->fetchAll();

    if (isset($tag) && empty($tag_ids) && $tag !== '未選択') {
        $data = [];
    }


    // 総データ数を取得
    $stmt = $db->prepare("SELECT COUNT(*) FROM redirects WHERE cid=? AND deleted = 0");
    $stmt->execute([$client_id]);
    $current = $stmt->fetchColumn();

    $total_pages = ceil($current / $per_page);

    $max = $ClientData['max_redirects'];
    $limit = $ClientData['max_redirects'] - $current;

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
                <a class="tab_btn btn btn-secondary" href="./redirect.php">リダイレクト</a>
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
                        <div class="modal-body" id="modal_req_new">
                            <form action="./back/new_redirect.php" method="post" enctype="multipart/form-data">
                                <table>
                                    <tr>
                                        <td style="min-width: 100px;">タイトル</td>
                                        <td>
                                            <span class="require-note">必須</span><input type="text" name="title"
                                                required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>リダイレクト先</td>
                                        <td>
                                            <span class="require-note">必須</span><input type="text" name="redirect_url"
                                                placeholder="URLを入力してください。" required>
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
                            <button type="submit" class="btn btn-primary">新規登録</button>
                            </form>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                        </div>
                    </div>
                </div>
            </div>
            <input type="hidden" id="type" value="<?= $ClientData['max_redirects_type'] ?>">
            <input type="hidden" id="max_value" value="<?= $ClientData['max_redirects'] ?>">
            <input type="hidden" id="used_value" value="<?= $current ?>">

            <div class="modal fade" id="csv" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5>CSVで一括登録</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form enctype="multipart/form-data" action="./back/csv_input.php" method="post">
                                <input name="select" id="select" type="file" />
                                <br><br>
                                <p>※CSVデータ項目は トラッキングID/タイトル/リダイレクト先 /表示説明文で作成してください。</p>
                                <br>
                        </div>
                        <div class="modal-footer">
                            <input type="hidden" name="cid" value="<?= $client_id ?>">
                            <button type="submit" class="btn btn-secondary">登録</button>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
                        </div>
                    </div>
                    </form>
                </div>
            </div>

            <div class="d-flex">
                <?php if ($ClientData['max_redirects_type'] == 1 && $limit <= 0): ?>
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
                <button type="button" class="btn btn-secondary ms-2" data-toggle="modal"
                    data-target="#csv">CSV登録</button>
            </div>

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
                        <div class="modal-body" id="modal_req">
                            <form action="back/ch_redilect.php" method="post" enctype="multipart/form-data">
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
                                        <td>リダイレクト先</td>
                                        <td>
                                            <span class="require-note">必須</span>
                                            <input type="text" name="redirect_url" id="url_d" required disabled>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>サムネイル画像</td>
                                        <td class="">
                                            <div class="drop-zone ch_drop-zone w-100">
                                                <span
                                                    class="drop-zone__prompt ch_drop-zone__prompt">ここにファイルをドロップするか、ファイルを選択</span>
                                                <input type="file" name="redl_img" id="redl_img"
                                                    class="drop-zone__input ch_drop-zone__input" disabled>
                                                <div class="drop-zone__thumb ch_drop-zone__thumb"></div>
                                            </div>
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
                                                $i++;
                                                if ($each == '未選択')
                                                    continue; ?>
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
                            <input type="hidden" name="tracking_id" id="tracking_id_d2">
                            <div class="w-100 d-flex justify-content-between">
                                <div>
                                    <button type="button" id="delete_pdf" class="btn btn-danger"
                                        onclick="deleteRedirect()" <?= in_array('削除', $func_limit) ? 'disabled' : ''; ?>>削除</button>
                                    <a id="redirect_link" target="_blank" rel="noopener noreferrer"
                                        class="btn btn-primary">プレビュー</a>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="button" id="edit_button" class="btn btn-dark"
                                        onclick="enableEditing()" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                                    <button type="submit" id="save_button" class="btn btn-success"
                                        style='display:none'>保存</button>
                                    <button type="button" class="btn btn-secondary ms-1" onclick="cancelEditing()"
                                        data-bs-dismiss="modal">閉じる</button>
                                </div>
                            </div>
                            </form>

                        </div>
                    </div>
                </div>
            </div>
            <table class="table" id="red_table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>タイトル</th>
                        <th>リダイレクト先</th>
                        <th>アクセス数</th>
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

                    foreach (array_reverse($data, true) as $key => $row):

                        $url_ = $row['id'] . '/';
                        $t_url = $url_dir . 'r?t=' . $url_;

                        try {
                            $db = new PDO($dsn, $user, $pass, [
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            ]);
                            $query = "SELECT COUNT(*) FROM user_redirects_access WHERE redirect_id  = :redirect_id AND cid = :cid";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':redirect_id', $row['id'], PDO::PARAM_STR);
                            $stmt->bindParam(':cid', $client_id, PDO::PARAM_STR);
                            $stmt->execute();
                            $access_count = $stmt->fetchColumn();

                            $stmt = $db->prepare("SELECT * FROM tags WHERE table_name = 'redirects' AND table_id=?");
                            $stmt->execute([$row['id']]);
                            $tag_data_ = $stmt->fetch(PDO::FETCH_ASSOC);

                            if (!isset($tag_data_['tags'])) {
                                $redl2tag = '未選択';
                            } else {
                                $redl2tag = $tag_data_['tags'];
                            }

                            if (isset($_GET['tag']) && $_GET['tag'] == '未選択') {
                                if ($redl2tag != '未選択') {
                                    continue;
                                }
                            } else {
                                $check_tag = explode(',', $redl2tag);
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
                                <?= $row['url'] ?>
                            </td>
                            <td>
                                <?= $access_count ?>
                            </td>
                            <td>
                                <?= substr($row['updated_at'], 0, 16) ?>
                            </td>
                            <td>

                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#detail"
                                    onClick="handleClick(`<?= $row['id']; ?>`,`<?= $row['title']; ?>`,`<?= $row['url']; ?>`,`<?= $row['memo']; ?>`,`<?= $redl2tag; ?>`, `<?= $row['redl_img']; ?>`);">
                                    詳細</button>
                            </td>
                            <td>
                                <form action="access_analysis.php" method="get">
                                    <input type="hidden" name="search_title" value="<?= $row['title'] ?>">
                                    <button type="submit" class="btn btn-primary">解析</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
    </div>
    <script>
        const redirectData = <?= json_encode($data) ?>;
        const redirectDataIds = redirectData.map(redirect => redirect[0]);
        // const trackingIdInput = document.querySelector('input[name="tracking_id"]');
        const titleInput = document.querySelector('input[name="title"]');
        const submitButton = document.querySelector('button[type="submit"]');
        const detailTitleInput = document.querySelector('input[name="detail_title"]');

        titleInput.addEventListener('blur', () => {
            const title = titleInput.value;
            const existingRedirect = redirectData.find(redirect => redirect.title === title);

            if (existingRedirect) {
                alert('同じタイトルのリダイレクトが既に存在します。別のタイトルを入力してください。');
                titleInput.value = '';
                submitButton.disabled = true;
            } else {
                submitButton.disabled = false;
            }
        });

        let inputs = document.querySelectorAll('#modal_req input, #modal_req textarea');
        let editButton = document.getElementById('edit_button');
        let saveButton = document.getElementById('save_button');
        let closeBtn = document.getElementById('close');


        function detailTitleValidation() {

            const title = detailTitleInput.value;
            const existingRedirect = redirectData.find(redirect => redirect.title === title);

            if (existingRedirect) {
                alert('同じタイトルのリダイレクトが既に存在します。別のタイトルを入力してください。');
                titleInput.value = '';
                saveButton.disabled = true;
            } else {
                saveButton.disabled = false;
            }
        };


        function enableEditing() {
            inputs.forEach(input => {
                input.disabled = false;
            });

            editButton.style.display = 'none';
            saveButton.style.display = 'inline-block';
            $('#tagContainer').removeClass('d-none');
            $('#currentTags').addClass('d-none');
            $(".ch_drop-zone").addClass('w-100');
            $(".ch_drop-zone__prompt").show();
            $(".ch_drop-zone__thumb").hide();
        }

        function handleClick(tracking_id, title, url, memo, tags, redl_img) {
            document.getElementById("tracking_id_d").innerText = tracking_id;
            document.getElementById("title_d").value = title;
            document.getElementById("url_d").value = url;
            if (redl_img.length > 0) {
                imageURL = './redl_img/' + redl_img;
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
            } else {
                $(".drop-zone__prompt").show();
                $(".drop-zone__thumb").hide();
            }
            document.getElementById("tracking_id_d2").value = tracking_id;
            document.getElementById("memo_d").value = memo;
            document.getElementById("redirect_link").setAttribute('href', url);
            document.getElementById("currentTags").innerText = tags;
            var tagsArray = tags.split(',');
            console.log(tags);

            var checkboxes = document.querySelectorAll('input[name^="detail_tags"]');

            checkboxes.forEach(function (checkbox) {
                if (tagsArray.indexOf(checkbox.value) !== -1) {
                    checkbox.checked = true;
                } else {
                    checkbox.checked = false;
                }
            });
        }
        function cancelEditing() {
            inputs.forEach(input => {
                input.disabled = true;
            });
            saveButton.style.display = "none";
            editButton.style.display = "block";
            $('#tagContainer').addClass('d-none');
            $('#currentTags').removeClass('d-none');
        }

        $('#new').on('hidden.bs.modal', function () {
        });

        const buttonEl = document.getElementById('modal_button');

        buttonEl.addEventListener('click', function () {
            var limit = <?= $limit; ?>;
            if (limit == 0) {
                alert("上限数が0なので新規登録はできません。");
            } else {
                document.getElementById('new_form').submit();
            }
        });

        function deleteRedirect() {
            var id = document.getElementById('tracking_id_d2').value;
            var result = confirm("データを削除してもよろしいですか？");
            if (result) {
                window.location.href = "./back/del_redirect.php?id=" + id;
            }
        }
    </script>
    <script>
        $(document).ready(function () {
            $('#red_table').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Japanese.json"
                },
                "dom": '<"top"p>rt<"bottom"i><"clear">',
                "order": [
                    [4, 'desc']
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
                    document.querySelector(".drop-zone__prompt").classList.add('d-none');
                }


                thumbnailElement.classList.remove("d-none");

                thumbnailElement.dataset.label = file.name;

                if (file.type.startsWith("image/")) {
                    const reader = new FileReader();

                    reader.readAsDataURL(file);
                    reader.onload = () => {
                        thumbnailElement.style.backgroundImage = `url('${reader.result}')`;
                        thumbnailElement.style.display = 'block'; // or 'inline-block', 'flex', etc., as appropriate
                    };
                } else {
                    thumbnailElement.style.backgroundImage = null;
                    thumbnailElement.style.display = 'none';
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