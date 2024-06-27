<?php
session_start();
require ('common.php');

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$directory = dirname($_SERVER['PHP_SELF']);
$current_directory_url = $protocol . "://" . $host . $directory;

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = "SELECT * FROM clients WHERE ref =?";
    $status = $_POST['status_filter'] ?? '';
    if (isset($_POST['status_filter'])) {
        if ($status == 'Free') {
            $sql .= " AND status = 'Free'";
        } elseif ($status == 'Paid') {
            $sql .= " AND (status = 'Basic' OR status = 'Starter' OR status = 'Pro')";
        } elseif ($status == 'Stop') {
            $sql .= " AND status = '利用停止'";
        }
    }
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $promotion_data = $stmt->fetchAll();

    $stmt = $db->prepare('SELECT * FROM ad_setting WHERE cid = :cid');
    $stmt->execute(array(':cid' => $client_id));
    $ad_setting_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $db = null;
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

    table.dataTable thead .sorting_desc {
        background-image: url(./img/sort_desc.png) !important;
    }

    table.dataTable thead .sorting_asc {
        background-image: url(./img/sort_asc.png) !important;
    }
</style>

<body>

    <div class="wrapper">
        <?php require ('menu.php'); ?>
        <main>
            <?php
            function encrypt($data, $key)
            {
                $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
                $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
                return base64_encode($iv . $encrypted);
            }
            $encryptionKey = "your_secret_key";

            $encryptedId = encrypt($client_id, $encryptionKey);
            ?>
            <div class="affiliate">
                <div class="d-flex justify-content-between">
                    <div class="header-with-help">
                        <h1>アフィリエイト</h1>
                    </div>
                    <?php require ('dropdown.php'); ?>
                </div>
                <div class="d-flex align-items-center">
                    <button class="btn btn-primary me-2" onclick="copy_link()">アフィリエイトリンクをコピー</button>
                    <button class="btn btn-warning me-2" data-bs-toggle="modal"
                        data-bs-target="#new">アカウント作成サポート</button>
                    <div class="modal fade" id="new" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                        aria-labelledby="staticBackdropLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">新規登録</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-bs-label="Close">
                                    </button>
                                </div>
                                <!-- modal-body -->
                                <form action="./back/new_client.php" id="new_form" method="post">
                                    <div class="modal-body" id="modal_req_new">
                                        <table class="table">
                                            <tr>
                                                <td>契約メールアドレス</td>
                                                <td><span class="require-note">必須</span> <input type="email"
                                                        name="email" id="new_email" required></td>
                                            </tr>
                                            <tr>
                                                <td>企業名・屋号名</td>
                                                <td>
                                                    <input type="company" name="company" id="new_company"
                                                        style="margin-left: 35px;">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>契約者姓</td>
                                                <td>
                                                    <input type="last_name" name="last_name" id="new_last_name"
                                                        style="margin-left: 35px;">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>契約者名</td>
                                                <td>
                                                    <input type="text" name="first_name" id="new_first_name"
                                                        style="margin-left: 35px;">
                                                </td>
                                            </tr>
                                            <tr class="d-none">
                                                <td>プロモーションコード</td>
                                                <td>
                                                    <input type="text" name="ref" id="new_ref"
                                                        value="<?= $client_id; ?>" style="margin-left: 35px;">
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-primary" id="block_btn">新規登録</button>
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">閉じる</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <form action="" method="post">
                        <select name="status_filter" id="">
                            <?php
                            if (isset($_GET["status"]))
                                echo '<option value="' . $_GET["status"] . '">' . $_GET["status"] . '</option>';
                            ?>
                            <option disabled>-----</option>
                            <option value="All" <?= $status == 'All' ? 'selected' : ''; ?>>全選択</option>
                            <option value="Free" <?= $status == 'Free' ? 'selected' : ''; ?>>無料プラン</option>
                            <option value="Paid" <?= $status == 'Paid' ? 'selected' : ''; ?>>有料プラン</option>
                            <option value="Stop" <?= $status == 'Stop' ? 'selected' : ''; ?>>利用停止</option>
                        </select>
                        <button class="btn btn-secondary">フィルター変更</button>
                    </form>
                </div>
                <table class="display" id="affiliate_table">
                    <thead>
                        <tr>
                            <th>紹介したメールアドレス</th>
                            <th>ステータス</th>
                            <th>更新日</th>
                            <th>広告表示</th>
                            <!-- <th></th> -->
                            <!-- <th>クリック数</th> -->
                            <!-- <th></th> -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($promotion_data as $row):

                            try {
                                $db = new PDO($dsn, $user, $pass, [
                                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                ]);
                                $query = "SELECT COUNT(*) FROM ad_click_cnt WHERE clicked_cid = :clicked_cid";
                                $stmt = $db->prepare($query);
                                $stmt->bindParam(':clicked_cid', $row['id'], PDO::PARAM_STR);
                                $stmt->execute();
                                $ad_clicks = $stmt->fetchColumn();

                                // 月別クリック数の集計
                                $query = "SELECT YEAR(clicked_at) as year, MONTH(clicked_at) as month, COUNT(*) as cnt FROM ad_click_cnt WHERE clicked_cid = :clicked_cid GROUP BY YEAR(clicked_at), MONTH(clicked_at)";
                                $stmt = $db->prepare($query);
                                $stmt->bindParam(':clicked_cid', $row['id'], PDO::PARAM_STR);
                                $stmt->execute();
                                $monthly_clicks = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            } catch (PDOException $e) {
                                echo '接続失敗' . $e->getMessage();
                                exit();
                            }
                            ?>
                            <tr>
                                <td>
                                    <?= $row['email']; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($row['status'] == 'Free') {
                                        echo '無料プラン';
                                    } elseif ($row['status'] == 'メール認証待ち') {
                                        echo 'メール認証待ち';
                                    } elseif ($row['status'] != '利用停止') {
                                        echo '有料プラン';
                                    } else {
                                        echo '利用停止';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?= substr($row['updated_at'], 0, 10); ?>
                                </td>
                                <td>
                                    <?= $row['ad_display']; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>
                <td class="d-none">
                    <div class="modal fade" id="detail" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">集計</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <!-- modal-body -->
                                <div class="modal-body" id="modal_req">
                                    <form action="back/ch_popup.php" method="post">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>月</th>
                                                    <th>クリック数</th>
                                                </tr>
                                            </thead>
                                            <tbody id="monthly_clicks_table">
                                                <tr>
                                                    <td colspan="2">データがありません</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <br>
                                        <br>
                                </div>
                                <div class="modal-footer">
                                    <input type="hidden" name="id" id="id_d">
                                    <button type="submit" class="btn btn-primary" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                                    </form>
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
                                </div>

                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#detail"
                        onClick='showDetails(`<?= json_encode($monthly_clicks); ?>`);'>
                        詳細
                    </button>
                </td>

                <h2>広告設定</h2>
                <form action="back/ad_setting.php" method="post" enctype="multipart/form-data">
                    <table>
                        <tr>
                            <td>画像挿入</td>
                            <td>
                                <input name="file" id="file" type="file" accept="image/*" style="display:none"
                                    onchange="displayFileName()">
                                <button id="fileSelect" class="btn btn-secondary" type="button" disabled>選択</button>
                                <span id="file-name">
                                    <?php echo isset($ad_setting_data['file_name']) ? $ad_setting_data['file_name'] : 'ファイルが選択されていません'; ?>
                                </span>

                                <script>
                                    const fileSelect = document.getElementById("fileSelect");
                                    const fileElem = document.getElementById("file");

                                    fileSelect.addEventListener("click", (e) => {
                                        if (fileElem) {
                                            fileElem.click();
                                        }
                                    }, false);

                                    function displayFileName() {
                                        var fileInput = document.getElementById('file');
                                        var fileNameDisplay = document.getElementById('file-name');

                                        if (fileInput.files.length > 0) {
                                            fileNameDisplay.textContent = fileInput.files[0].name;
                                        } else {
                                            fileNameDisplay.textContent = '<?php echo isset($ad_setting_data['file_name']) ? $ad_setting_data['file_name'] : 'ファイルが選択されていません'; ?>';
                                        }
                                    }
                                </script>
                            </td>
                            <?php if (isset($ad_setting_data['cid'])): ?>
                                <td><a href="img_ad_setting/<?= $ad_setting_data['cid'] ?>.png" target="_blank">プレビュー</a>
                                </td>
                            <?php endif; ?>

                        </tr>
                        <tr>
                            <td>URL</td>
                            <td><input type="url" name="url" id="url"
                                    value="<?= isset($ad_setting_data['url']) ? $ad_setting_data['url'] : '' ?>"
                                    placeholder="URLを入力してください。" required disabled></td>
                        </tr>
                    </table>
                    <input type="hidden" name="cid" value="<?= $client_id ?>">
                    <button class="btn btn-dark" id="edit_btn" type="button" onclick="enableEditing()" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                    <button class="btn btn-success" id="save_btn" style="display:none">保存</button>
                    <button class="btn btn-secondary" type="button" id="cancel_btn" onclick="cancel()"
                        style="display:none">閉じる</button>
                </form>
            </div>
        </main>
    </div>
    <script>

        let url_input = document.getElementById('url');
        let save_btn = document.getElementById('save_btn');
        let edit_btn = document.getElementById('edit_btn');
        let cancel_btn = document.getElementById('cancel_btn');
        function enableEditing() {

            url_input.disabled = false;
            save_btn.style.display = "inline-block";
            cancel_btn.style.display = "inline-block";
            fileSelect.disabled = false;
            edit_btn.style.display = "none";

        }
        function cancel() {
            location.reload();
        }
        function copy_link() {
            var anyText = "https://in.doc1.jp/signup_1.php?ref=<?= $encryptedId; ?>";
            var textBox = document.createElement("textarea");
            textBox.setAttribute("id", "target");
            textBox.setAttribute("type", "hidden");
            textBox.textContent = anyText;
            document.body.appendChild(textBox);

            textBox.select();
            document.execCommand('copy');
            document.body.removeChild(textBox);
            copy_alert();
        }

        function showDetails(monthlyClicksJson) {
            const monthlyClicks = JSON.parse(monthlyClicksJson).reverse();
            console.log(monthlyClicks);

            // Generate table rows
            let tableRows = '';
            let total = 0; // 合計を初期化
            if (monthlyClicks.length > 0) {
                monthlyClicks.forEach(item => {
                    tableRows += `<tr><td>${item.year}年${item.month}月</td><td>${item.cnt}</td></tr>`;
                    total += item.cnt; // 合計を加算
                });
            } else {
                tableRows = `<tr><td colspan="2">データがありません</td></tr>`;
            }

            // Add total row
            tableRows += `<tr><td>合計</td><td>${total}</td></tr>`;

            // Update the table in the modal
            document.getElementById('monthly_clicks_table').innerHTML = tableRows;
        }

        function copy_alert() {
            $(document).ready(function () {
                toastr.options.timeOut = 1500; // 1.5s
                toastr.success('リンクをコピーしました！');
            });
        }
    </script>
    <script>
        $(document).ready(function () {
            $('#affiliate_table').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/Japanese.json"
                },
                "lengthMenu": false,
                "order": [
                    [2, 'desc']
                ]
            });
            $('#affiliate_table_length').addClass('d-none');
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