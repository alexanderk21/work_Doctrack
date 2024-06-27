<?php
session_start();
require ('./common.php');

if (isset($_SESSION['csv_data_form'])) {
    $csv_data = $_SESSION['csv_data_form'];

    $csv_cnt = count($csv_data);
} else {
    $csv_cnt = 0;
}

if ($_POST['tag']) {
    if (isset($_SESSION['csv_data']))
        unset($_SESSION["csv_data"]);
}

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $tag_array = [];
    $stmt = $db->prepare("SELECT * FROM tag_sample WHERE client_id=?");
    $stmt->execute([$client_id]);
    $tag_data = $stmt->fetchAll();
    foreach ($tag_data as $t) {
        $tag_array[] = $t['tag_name'];
    }

    if (isset($_POST['tag']) && $_POST['tag'] != '') {
        $tag = $_POST['tag'];
        $tag_ids = [];
        $sql = "SELECT * FROM tags WHERE table_name = 'templates' AND tags LIKE '%$tag%'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll();
        if (!empty($result)) {
            foreach ($result as $row) {
                $tag_ids[] = $row['table_id'];
            }
        }
    }

    $sql = "SELECT * FROM templates WHERE cid=?";

    if (!empty($tag_ids)) {
        $placeholders = implode(',', array_fill(0, count($tag_ids), '?'));
        $sql .= " AND id IN ($placeholders)";
    }

    $sql .= " ORDER BY created_at DESC";

    $stmt = $db->prepare($sql);
    $params = [$client_id];
    if (!empty($tag_ids)) {
        $params = array_merge($params, $tag_ids);
    }
    $stmt->execute($params);
    $template_data = $stmt->fetchAll();
    if (isset($tag) && empty($tag_ids)) {
        $template_data = [];
    }

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
?>
<?php require ('header.php'); ?>
<style>
    .drop-zone {
        height: 200px;
        padding: 25px;
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

    <div class="wrapper">
        <?php require ('menu.php'); ?>
        <main>
            <div class="d-flex justify-content-between">
                <div class="header-with-help">
                    <h1>フォーム営業</h1>
                </div>
                <?php require ('dropdown.php'); ?>
            </div>
            <div>
                <a class="tab_btn btn btn-secondary" href="./form_submission.php">営業文作成</a>
                <a class="tab_btn btn btn-primary" href="./send_list.php">作成一覧</a>
            </div>
            <br>
            作成顧客数
            <?= $csv_cnt ?>件
            <br>
            <br>
            <h2>顧客リスト</h2>
            <form enctype="multipart/form-data" action="./back/read_csv_form.php" method="post">
                <div class="drop-zone w-25 mb-2">
                    <span class="drop-zone__prompt">ここにファイルをドロップするか、ファイルを選択</span>
                    <input type="file" name="csvFile" id="csvFile" class="drop-zone__input" required>
                </div>
                <!-- <input name="csvFile" id="csvFile" type="file" /> -->
                <input type="hidden" name="cid" value="<?= $client_id ?>">
                <button class="btn btn-secondary">ファイルを開く</button>
            </form>
            <br>
            <?php if (isset($_SESSION['csv_data_form'])): ?>
                <!-- <div>
                    <label for="from_select" style="min-width: 110px;">差出アドレス</label>
                    <select name="from_select" id="from_select">
                        <?php foreach ($from_data as $each): ?>
                            <option value="<?= $each['id']; ?>" <?= $each['id'] == $from_email ? 'selected' : ''; ?>>
                                <?= $each['email']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div> -->
                <br>
                <form action="" id="tagForm" method="post" class="me-2">
                    <label for="tag" style="min-width: 110px;">タ グ</label>
                    <select name="tag" id="tag">
                        <option value="">全選択</option>
                        <?php foreach ($tag_array as $each): ?>
                            <option value="<?= $each; ?>" <?= (isset($tag) && $tag == $each) ? 'selected' : '' ?>>
                                <?= $each; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <!-- <input type="hidden" class="from" name="from" value="<?= $from_email; ?>"> -->
                </form>
                <br>
                <script>
                    var tagSelect = document.getElementById('tag');
                    tagSelect.addEventListener('change', function () {
                        document.getElementById('tagForm').submit();
                    });
                </script>
                <form action="form_confirm.php" method="post">
                    <!-- <input type="hidden" class="from" name="from" value="<?= $from_email; ?>"> -->
                    <label for="id" style="min-width: 110px;">テンプレート</label>
                    <select name="id" id="template-select">
                        <option value="" selected disabled>未選択</option>
                        <?php
                        if (isset($_SESSION['csv_data_form'])):
                            foreach ($template_data as $template): ?>
                                <?= $template['division'] == 'フォーム営業' ? "<option value=\"{$template['id']}\">{$template['subject']}</option>" : '' ?>
                                <?php
                            endforeach;
                        endif;
                        ?>
                    </select>
                    <br>
                    <br>
                    <table>
                        <td>
                            <button class="btn btn-secondary" id="confirm-btn" disabled>確認画面</button>
                        </td>
                    </table>
                </form>
            <?php endif; ?>
        </main>
    </div>
    <script>
        const selectEl = document.getElementById('template-select');
        const buttonEl = document.getElementById('confirm-btn');

        selectEl.addEventListener('change', () => {
            if (selectEl.value === '') {
                buttonEl.disabled = true;
            } else {
                buttonEl.disabled = false;
            }
        });
        function back(template) {
            if (template != "0") {
                selectEl.value = template;
                buttonEl.disabled = false;
            } else {
                selectEl.value = "";
                buttonEl.disabled = true;
            }
        }
        back(<?= isset($_GET['template']) ? $_GET['template'] : "0" ?>);

        // $('#from_select').change(function () {
        //     $('.from').val($(this).val());
        // });
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
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
        crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI"
        crossorigin="anonymous"></script>
</body>

</html>