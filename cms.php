<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (isset($_SESSION['csv_data']))
    unset($_SESSION["csv_data"]);
if (isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);

require ('common.php');
$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$url_dir = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$url_dir = str_replace(basename($url_dir), '', $url_dir);

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $tag_all_array = [];
    $stmt = $db->prepare("SELECT * FROM tag_sample WHERE client_id=?");
    $stmt->execute([$client_id]);
    $tag_data = $stmt->fetchAll();
    foreach ($tag_data as $t) {
        $tag_all_array[] = $t['tag_name'];
    }
    if ($ClientUser['role'] == 1) {
        $tag_array = $tag_all_array;
        $tag_array[] = '未選択';
    } else {
        $tag_array = explode(',', $ClientUser['tags']);
    }

    $search_tag = $_POST['search_tag'] ?? '';
    $sql = "SELECT * FROM cmss WHERE client_id=?";
    if ($search_tag != '' && $search_tag != '未選択') {
        $like_tag = "%" . $search_tag . "%";
        $sql .= " AND tag LIKE '$like_tag'";
    }
    $sql .= " ORDER BY updated_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $cms_data = $stmt->fetchAll();

    $sql = "SELECT cms_id, COUNT(*) AS count FROM user_cms_access WHERE cid = ? GROUP BY cms_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $user_cms_access = $stmt->fetchAll();

    $access_cnt = [];
    foreach ($user_cms_access as $each) {
        $access_cnt[$each['cms_id']] = $each;
    }

    $sql = "SELECT count(*) FROM cmss WHERE client_id=:client_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':client_id' => $client_id]);
    $current = $stmt->fetchColumn();

    $max = $ClientData['max_cms'];

    if ($ClientData['max_cms_type'] == 1) {
        $limit = $ClientData['max_cms'] - $current;
    }

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
?>
<?php require ('header.php'); ?>
<style>
    #detail td {
        padding-bottom: 5px;
    }

    .dataTables_filter {
        display: none;
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
                <a class="tab_btn btn btn-primary" href="./pdf.php">PDFファイル</a>
                <a class="tab_btn btn btn-primary" href="./redirect.php">リダイレクト</a>
                <a class="tab_btn btn btn-primary" href="./popup.php">ポップアップ</a>
                <a class="tab_btn btn btn-secondary" href="./cms.php">CMS</a>
            </div>
            <br>
            <div class="mb-2 d-flex align-items-center">
                <?php if ($ClientData['max_cms_type'] == 1 && $limit <= 0): ?>
                    <?php require_once ('./limitModal.php'); ?>
                    <button type="button" class="btn btn-primary" id="modal_button" data-bs-toggle="modal"
                        data-bs-target="#limitModal">新規登録</button>
                <?php else: ?>
                    <button type="button" class="btn btn-primary" id="modal_button" data-bs-toggle="modal"
                        data-bs-target="#new">新規登録</button>
                <?php endif; ?>
                
                <form action="" method="post">
                    <label for="search_tag" class="ms-2">タグ</label>
                    <select name="search_tag" id="search_tag" class="m-2">
                        <option value="">全選択</option>
                        <?php foreach ($tag_array as $each): ?>
                            <option value="<?= $each; ?>" <?= $each == $search_tag ? 'selected' : ''; ?>><?= $each; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-success">検索</button>
                </form>
            </div>
            <table id="cms_table">
                <thead>
                    <tr>
                        <th style="max-width: 50px;">No.</th>
                        <th>タイトル</th>
                        <th>アクセス数</th>
                        <th>設置数</th>
                        <th>更新日時</th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1;
                    foreach ($cms_data as $cd):
                        if ($search_tag == '未選択') {
                            if (!empty(json_decode($cd['tag'], true))) {
                                continue;
                            }
                        } else {
                            $check_tag = is_null($cd['tag']) ? ['未選択'] : json_decode($cd['tag'], true);
                            if (!in_array('未選択', $tag_array) && empty($check_tag)) {
                                continue;
                            } else {
                                if (empty($check_tag)) {
                                    $check_tag[] = '未選択';
                                }
                                $commonValues = array_intersect($check_tag, $tag_array);
                                if (empty($commonValues)) {
                                    continue;
                                }
                            }
                        }
                        ?>
                        <tr>
                            <td><?= $i++; ?></td>
                            <td><?= $cd['title']; ?></td>
                            <td><?= isset($access_cnt[$cd['cms_id']]) ? $access_cnt[$cd['cms_id']]['count'] : 0; ?></td>
                            <td>
                                <?= count(is_null($cd['content']) ? [] : json_decode($cd['content'], true)); ?>
                            </td>
                            <td><?= date("Y-m-d H:i", strtotime($cd['updated_at'])); ?></td>
                            <td>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#detail"
                                    onclick="handclick(`<?= $cd['id']; ?>`,`<?= $cd['cms_id']; ?>`,`<?= $cd['title']; ?>`,`<?= $cd['logo_img']; ?>`,`<?= $cd['memo']; ?>`,`<?= htmlspecialchars($cd['tag']); ?>`)">詳細</button>
                            </td>
                            <td>
                                <form action="./access_analysis.php" method="get">
                                    <input type="hidden" name="search_cms_id" value="<?= $cd['cms_id']; ?>">
                                    <button class="btn btn-primary">解析</button>
                                </form>
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

        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">新規登録</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <!-- modal-body -->
                <form action="./back/new_cms.php" method="post">
                    <div class="modal-body" id="modal_req_new">
                        <table>
                            <tr>
                                <td style="width: 70px;">タイトル</td>
                                <td>
                                    <span class="require-note">必須</span><input type="text" name="title" required>
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
                        <button type="submit" class="btn btn-primary">新規登録</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                    </div>
                </form>
            </div>
        </div>
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
                <form action="back/ch_cms.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="id_d">
                    <div class="modal-body" id="modal_req">
                        <table>
                            <tr>
                                <td style="width: 105px;">トラッキングID</td>
                                <td class="space-1">
                                    <span id="tracking_id_d"></span>
                                </td>
                            </tr>
                            <tr>
                                <td>タイトル</td>
                                <td>
                                    <span class="require-note">必須</span>
                                    <input type="text" name="title" id="title_d" required disabled>
                                </td>
                            </tr>
                            <tr>
                                <td>ロゴ画像</td>
                                <td class="d-flex align-items-center">
                                    <span class="require-note" style="width: 30px;">必須</span>
                                    <div class="drop-zone ch_drop-zone w-100">
                                        <span
                                            class="drop-zone__prompt ch_drop-zone__prompt">ここにファイルをドロップするか、ファイルを選択</span>
                                        <input type="file" name="logo" id="" class="drop-zone__input" disabled>
                                        <div class="drop-zone__thumb ch_drop-zone__thumb"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>表示説明文</td>
                                <td class="space-1">
                                    <textarea class="w-100" name="memo" id="memo_d" disabled></textarea>
                                </td>
                            </tr>
                            <tr>
                                <td>タグ</td>
                                <td id="tagContainer" class="d-none">
                                    <?php $i = 0;
                                    // array_shift($tag_array);
                                    foreach ($tag_all_array as $each):
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
                        <div class="w-100 d-flex justify-content-between">
                            <div>
                                <button type="button" id="del_button" class="btn btn-danger" onclick=""
                                    <?= in_array('削除', $func_limit) ? 'disabled' : ''; ?>>削除</button>
                                <a id="cms_link" target="_blank" rel="noopener noreferrer"
                                    class="btn btn-primary">プレビュー</a>
                            </div>
                            <div>
                                <button type="button" id="mana_button" class="btn btn-success"
                                    onclick="showMana()">CMS管理</button>
                                <button type="button" id="edit_button" class="btn btn-dark" onclick="enableEditing()"
                                    <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                                <button type="submit" id="save_button" class="btn btn-success"
                                    style='display:none'>保存</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEditing()"
                                    data-bs-dismiss="modal">閉じる</button>
                            </div>
                        </div>
                    </div>
                </form>
                <form action="./cms_mana.php" method="get" id="manaForm">
                    <input type="hidden" name="id" id="mana_id">
                </form>
                <form action="./back/del_cms.php" method="post" id="delForm">
                    <input type="hidden" name="id" id="del_id">
                </form>
            </div>
        </div>
    </div>
    <script>
        var inputs = $('#modal_req input, #modal_req textarea');
        var editButton = $('#edit_button');
        var saveButton = $('#save_button');
        var url_dir = "<?= $url_dir; ?>";

        function enableEditing() {
            inputs.prop('disabled', false);
            editButton.hide();
            saveButton.show();
            $('#currentTags').hide();
            $('#tagContainer').removeClass('d-none');
            $(".ch_drop-zone").addClass('w-100');
            $(".ch_drop-zone__prompt").show();
            $(".ch_drop-zone__thumb").hide();
        }

        function cancelEditing() {
            inputs.prop('disabled', true);
            saveButton.hide();
            editButton.show();
            $('#currentTags').show();
            $('#tagContainer').addClass('d-none');
        }

        function limit_alert() {
            alert('プリプランではCMSを1つまでしか設定できません。');

        }

        function handclick(id, cms_id, title, logo_img, memo, json_tag) {
            var tag = JSON.parse(json_tag);
            if (tag == null) {
                var tagValues = Array();
            } else {
                var tagValues = Object.values(tag);
            }

            if (tagValues.length == 0) {
                tagValues = ["未選択"];
            }

            $('#id_d').val(id);
            $('#mana_id').val(id);
            $('#del_id').val(id);
            $('#tracking_id_d').text(cms_id);
            $('#cms_link').attr('href', url_dir + 'c?t=' + cms_id);
            $('#title_d').val(title);
            $('#memo_d').val(memo);
            $('#currentTags').text(tagValues.map(function (item) {
                return item;
            }).join(', '));
            if (logo_img.length > 0) {
                imageURL = './favi_logos/' + logo_img + '.png';
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

            $('input[name^="detail_tags"]').each(function () {
                if (tagValues.indexOf($(this).val()) !== -1) {
                    $(this).prop('checked', true);
                } else {
                    $(this).prop('checked', false);
                }
            });
        }

        function showMana() {
            $('#manaForm').submit();
        }

        $('#del_button').click(function () {
            $('#delForm').submit();
        });
    </script>
    <script>
        $(document).ready(function () {
            $('#cms_table').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Japanese.json"
                },
                "order": [
                    [4, 'desc']
                ]
            });
        });
    </script>
    <script>
        document.querySelectorAll(".drop-zone__input").forEach((inputElement) => {
            const dropZoneElement = inputElement.closest(".drop-zone");

            // Function to update the thumbnail
            function updateThumbnail(dropZoneElement, file) {
                let thumbnailElement = dropZoneElement.querySelector(".drop-zone__thumb");

                // First time - remove the prompt
                if (dropZoneElement.querySelector(".drop-zone__prompt")) {
                    dropZoneElement.querySelector(".drop-zone__prompt").remove();
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

            // Load older image when the page first loads
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