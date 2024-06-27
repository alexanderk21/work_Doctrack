<?php
session_start();
require ('common.php');
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (isset($_SESSION['csv_data'])) {
    $csv_data = $_SESSION['csv_data'];
    $csv_cnt = count($csv_data);
} elseif (isset($_POST['email_data'])) {
    $post_email_data = json_decode($_POST['email_data']);
    $email_data = [];
    foreach ($post_email_data as $row) {
        $email_data[] = [
            $row->メールアドレス,
            $row->企業URL,
            $row->姓,
            $row->名,
            $row->企業名,
            $row->顧客ID
        ];
    }
    $_SESSION['email_data'] = $email_data;
    $csv_cnt = count($email_data);
} elseif (isset($_SESSION['email_data'])) {
    $email_data = $_SESSION['email_data'];
    $csv_cnt = count($email_data);
} else {
    $csv_cnt = 0;
}


if (!isset($_POST['tag'])) {
    if (isset($_SESSION['csv_data_form']))
        unset($_SESSION["csv_data_form"]);
}

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

    $cfroms = explode(',', $ClientUser['from_emails']);
    $placeholders = implode(',', array_fill(0, count($cfroms), '?'));
    $sql = "SELECT * FROM froms WHERE id IN ($placeholders) AND smtp_host != '' AND smtp_pw != ''";
    $stmt = $db->prepare($sql);
    $stmt->execute($cfroms);
    $from_data = $stmt->fetchAll();

    // $sql = "SELECT * FROM froms WHERE cuser_id LIKE :cuser_id";
    // $stmt = $db->prepare($sql);
    // $cuser_id_param = '%' . $ClientUser['id'] . '%';
    // $stmt->execute([':cuser_id' => $cuser_id_param]);
    // $from_data = $stmt->fetchAll();

    if (isset($_POST['from'])) {
        $from_email = $_POST['from'];
    } else {
        $from_email = $from_data[0]['id'] ?? '';
    }

    $stmt = $db->prepare("SELECT count(*) FROM logs WHERE cid = ?");
    $stmt->execute([$client_id]);
    $log_cnt = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM schedules WHERE cid = ? AND process = 1");
    $stmt->execute([$client_id]);
    $process_data = $stmt->fetchAll();

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
<div class="wrapper">
    <?php require ('menu.php'); ?>
    <main>
        <div class="d-flex justify-content-between">
            <div class="header-with-help">
                <h1>メール配信</h1>
            </div>
            <?php require ('dropdown.php'); ?>
        </div>
        <div>
            <a class="tab_btn btn btn-secondary" href="./email_distribution.php">配信作成</a>
            <a class="tab_btn btn btn-primary" href="./distribution_list.php">配信一覧</a>
            <a class="tab_btn btn btn-primary" href="./schedules.php">予約一覧</a>
            <a class="tab_btn btn btn-primary" href="./log.php">個別ログ</a>
            <a class="tab_btn btn btn-primary" href="./stop.php">配信停止</a>
            <a class="tab_btn btn btn-primary" href="./address_setting.php">アドレス設定</a>
        </div>
        <br>
        作成顧客数
        <?= $csv_cnt ?>件
        <br>
        <br>
        <?php if (count($process_data) == 0): ?>
            <?php if (!isset($_SESSION['email_data'])): ?>
                <div class="d-flex align-items-center">
                    <h2>宛先アドレス</h2>
                    <a href="./csv_his.php" class="btn btn-primary ms-3">登録履歴</a>
                </div>
                <form enctype="multipart/form-data" action="./back/read_csv.php" method="post">
                    <div class="drop-zone w-25 mb-2">
                        <span class="drop-zone__prompt">ここにファイルをドロップするか、ファイルを選択</span>
                        <input type="file" name="csvFile" id="csvFile" class="drop-zone__input" required <?php isset($_SESSION['email_data']) ? print_r("disabled") : print_r(""); ?>>
                    </div>
                    <input type="hidden" name="used_value" id="used_value" value="<?= count($template_data) ?>">
                    <input type="hidden" name="send_limit" id="send_limit" value="<?= $ClientUser['send_limit'] ?>">
                    <input type="hidden" name="send_limit_type" id="send_limit_type"
                        value="<?= $ClientUser['send_limit_type'] ?>">
                    <input type="hidden" name="cid" value="<?= $client_id ?>">
                    <button class="btn btn-secondary" <?php isset($_SESSION['email_data']) ? print_r("disabled") : print_r(""); ?>>ファイルを開く</button>
                </form>
                <br>
            <?php endif; ?>
            <?php if (isset($_SESSION['email_data']) || isset($_SESSION['csv_data'])): ?>
                <div>
                    <label for="from_select" style="min-width: 110px;">差出アドレス</label>
                    <select name="from_select" id="from_select">
                        <?php foreach ($from_data as $each): ?>
                            <option value="<?= $each['id']; ?>" <?= $each['id'] == $from_email ? 'selected' : ''; ?>>
                                <?= $each['email']; ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (count($from_data) == 0): ?>
                            <option disabled selected>SMTP未設定</option>
                        <?php endif; ?>
                    </select>
                </div>
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
                    <input type="hidden" class="from" name="from" value="<?= $from_email; ?>">
                </form>
                <script>
                    var tagSelect = document.getElementById('tag');
                    tagSelect.addEventListener('change', function () {
                        document.getElementById('tagForm').submit();
                    });
                </script>
                <br>
                <form action="sendEmail.php" method="post">
                    <input type="hidden" class="from" name="from" value="<?= $from_email; ?>">
                    <label for="id" style="min-width: 110px;">テンプレート</label>
                    <select name="id" id="template-select">
                        <option value="" selected>未選択</option>
                        <?php
                        if (isset($_SESSION['email_data']) || isset($_SESSION['csv_data'])):
                            foreach ($template_data as $template): ?>
                                <?= $template['division'] == 'メール配信' ? "<option value=\"{$template['id']}\">{$template['subject']}</option>" : '' ?>
                                <?php
                            endforeach;
                        endif;
                        ?>
                    </select>
                    <br>
                    <br>
                    <table>
                        <td>
                            <button type="submit" class="btn btn-secondary" id="confirm-btn">確認画面</button>
                        </td>
                    </table>
                </form>

            <?php endif; ?>
        <?php else: ?>
            <p class="text-danger">配信処理中</p>
        <?php endif; ?>
    </main>

    <input type="hidden" id="csv_cnt" value="<?= $csv_cnt ?>">
</div>
<?php
if (isset($_GET['err']) && $_GET['err'] == 'empty_file') {
    echo '<script type="text/javascript">
            window.onload = function() {
                alert("ファイルの内容にデータが含まれていません。!");
            }
            </script>';
}
?>
<script>
    <?php if (isset($_GET['err']) && $_GET['err'] == 'more_file'): ?>
        var limit = '<?= $ClientUser['send_limit_type'] == 1 ? $ClientUser['send_limit'] : 200; ?>'
        alert("一度に送信できる上限数は" + limit + "です。!");
    <?php endif; ?>
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

    $('#from_select').change(function () {
        $('.from').val($(this).val());
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
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
    integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
    crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
    integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI"
    crossorigin="anonymous"></script>
</body>

</html>