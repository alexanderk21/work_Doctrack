<?php
session_start();

if (isset($_SESSION['csv_data']))
    unset($_SESSION["csv_data"]);
if (isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$directory = dirname($_SERVER['PHP_SELF']);
$current_directory_url = $protocol . "://" . $host . $directory;

if (isset($_GET['pdf_id'])) {
    require ('common.php');

    $pdf_id = $_GET['pdf_id'];
    $pdf_title = $_GET['pdf_title'];

    try {
        $db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $stmt = $db->prepare("SELECT * FROM pdf_versions WHERE cid=? AND pdf_id=?");
        $stmt->execute([$client_id, $pdf_id]);
        $pdf_versions_data = $stmt->fetchAll();

    } catch (PDOException $e) {
        echo '接続失敗' . $e->getMessage();
        exit();
    }

} else {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}
?>
<?php require ('header.php'); ?>

<body>

    <div class="wrapper">
        <?php require ('menu.php'); ?>
        <main>
            <div class="d-flex justify-content-between">
                <h1>アップロード履歴</h1>
                <?php require ('dropdown.php'); ?>
            </div>
            <br>
            <div class="d-flex align-items-center">
                <div class="pdf-title-back">
                    <p>タイトル: <?= $pdf_title ?></p>
                </div>
                <a href="./upload_graph.php?pdf_id=<?= $pdf_id; ?>&pdf_title=<?= $pdf_title; ?>"
                    class="btn btn-warning ms-2">グラフ表示</a>
                <a href="./pdf.php" class="btn btn-secondary ms-2">戻る</a>
            </div>
            <br>
            <br>
            <br>
            <div class="modal fade" id="detail" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">詳細</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <!-- modal-body -->
                        <div class="modal-body" id="modal_req">
                            <table class="table">
                                <tr>
                                    <th>ページ数</th>
                                    <th>閲覧数</th>
                                    <th>閲覧時間</th>
                                </tr>
                                <tbody id="pageData"></tbody>
                            </table>
                            <br>
                            <br>
                        </div>
                        <div class="modal-footer">
                            <a class="btn btn-primary" href="" id="preview_btn" target="_blank">プレビュー</a>
                            <form action="back/csv_upload_history.php" method="post">
                                <input type="hidden" name="escapedData" id="escapedData">
                                <button class="btn btn-dark mx-2" <?= in_array('CSV出力', $func_limit) ? 'disabled' : ''; ?>>CSV出力</button>
                            </form>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
                        </div>
                    </div>
                </div>
            </div>

            <table class="table">
                <tr>
                    <th>No.</th>
                    <th>ファイル名</th>
                    <th>ページ数</th>
                    <th>アクセス数</th>
                    <th>閲覧時間</th>
                    <th>ページ移動数</th>
                    <th>ＣＴＡクリック数</th>
                    <th>登録日</th>
                    <th></th>
                    <th></th>
                </tr>
                <?php

                foreach (array_reverse($pdf_versions_data, true) as $key => $row):

                    $url_ = $ClientData['subdomain'] . '/' . $ClientData['id'] . '/' . $row['pdf_id'] . '/';
                    $t_url = $current_directory_url . '/show_pdf.php?token=' . $url_;
                    $total_access_cnt = 0;
                    try {
                        $db = new PDO($dsn, $user, $pass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        ]);
                        $pdf_version_id = $row['pdf_version_id'];
                        $cid = $ClientData['id'];

                        //アクセス数の計算
                        $stmt = $db->prepare("SELECT session_id, cid FROM user_pdf_access WHERE pdf_version_id = :pdf_version_id GROUP BY session_id, cid");
                        $stmt->bindParam(':pdf_version_id', $pdf_version_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $access_cnt = count($stmt->fetchAll());
                        $total_access_cnt += $access_cnt;

                        $stmt = $db->prepare("SELECT * FROM user_pdf_access WHERE pdf_version_id=?");
                        $stmt->execute([$pdf_version_id]);
                        $pdf_versions = $stmt->fetchAll();

                        //ページ移動数
                        $total_page_moved = 0;

                        $stmt = $db->prepare("SELECT page FROM user_pdf_access WHERE pdf_version_id = :pdf_version_id ORDER BY accessed_at");
                        $stmt->bindParam(':pdf_version_id', $pdf_version_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $total_page_moved = count($stmt->fetchAll()) - $access_cnt;



                        // ポップアップIDを取得するためのクエリ
                        $popup_query = "SELECT id FROM popups WHERE pdf_id = :pdf_id AND cid = :cid";
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

                        //閲覧時間
                        $total_duration_seconds = 0;

                        foreach ($pdf_versions as $pdf_version) {
                            $total_duration_seconds += $pdf_version["duration"];
                        }

                        $total_duration = secondsToTime($total_duration_seconds);


                        //画像の枚数をカウントし総ページ数を計算
                        $directory = "./pdf_img/";
                        $images = glob($directory . "/" . $pdf_id . "_" . $pdf_version_id . "*.jpg");

                        $last_img_name = "";
                        foreach ($images as $key => $image) {
                            $last_img_name = basename($image);

                        }
                        while ($key < count($images) - 1) {
                            sleep(1);
                        }

                        preg_match('/_(\d+)-/', $last_img_name, $matches);

                        $page_count = 0;
                        foreach ($images as $image) {
                            if (strpos(basename($image), $matches[0]) !== false) {
                                $page_count++;
                            }
                        }

                        // 同じpdf_version_idのレコードをpageごとに集計して閲覧数と閲覧時間の合計を取得
                        $sql = 'SELECT pdf_version_id, page, COUNT(*) AS views, SUM(duration) AS total_duration FROM user_pdf_access GROUP BY pdf_version_id, page';
                        $stmt = $db->query($sql);
                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $filteredResult = array_filter($result, function ($data) use ($pdf_version_id) {
                            return $data['pdf_version_id'] == $pdf_version_id;
                        });

                    } catch (PDOException $e) {
                        echo '接続失敗' . $e->getMessage();
                        exit();
                    }
                    ?>
                    <tr>
                        <td><?= $key + 1 ?></td>
                        <td><?= $row['file_name'] ?></td>
                        <td><?= $page_count ?></td>
                        <td><?= $total_access_cnt ?></td>
                        <td><?= $total_duration ?></td>
                        <td><?= $total_page_moved ?></td>
                        <td><?= $total_cta_clicks ?></td>

                        <td><?= substr($row['uploaded_at'], 0, 10) ?></td>
                        <td>
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#detail"
                                onClick="handleClick('<?php echo htmlspecialchars(json_encode($filteredResult)); ?>', '<?php echo $page_count; ?>', '<?= $row['pdf_id']; ?>', '<?= $row['pdf_version_id']; ?>');">詳細</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </main>
    </div>
    <script>
        function stringToNum(value) {
            if (value == null) {
                return 0;
            } else {
                return parseInt(value);
            }
        }

        function handleClick(escapedData, pageCount, pdf_id, pdf_version_id) {
            let data = JSON.parse(escapedData);
            data = combinePagesZeroAndOne(data);
            console.log(data);

            let tbody = document.getElementById("pageData");
            tbody.innerHTML = "";

            let maxPageNumber = parseInt(pageCount);
            let totalViews = 0;
            let totalDuration = 0;

            for (let i = 1; i <= maxPageNumber; i++) {
                let tr = document.createElement("tr");
                let td1 = document.createElement("td");
                let td2 = document.createElement("td");
                let td3 = document.createElement("td");

                td1.innerText = i;
                td2.innerText = data[i] ? parseInt(data[i]["views"]) : 0;
                td3.innerText = data[i] ? convertSecondsToHMS(stringToNum(data[i]["total_duration"])) : convertSecondsToHMS(0);

                if (data[i]) {
                    totalViews += parseInt(data[i]["views"]);
                    totalDuration += data[i]["total_duration"];
                }

                tr.appendChild(td1);
                tr.appendChild(td2);
                tr.appendChild(td3);

                tbody.appendChild(tr);
            }


            $('#preview_btn').attr('href', './pdf/' + pdf_id + '_' + pdf_version_id + '.pdf');
            document.getElementById("escapedData").value = escapedData;
        }

        function combinePagesZeroAndOne(data) {
            let combinedData = {};

            for (let key in data) {
                let pageIndex = data[key]["page"] === 0 ? 1 : data[key]["page"];

                if (!combinedData[pageIndex]) {
                    combinedData[pageIndex] = {
                        "views": data[key]["views"],
                        "total_duration": data[key]["total_duration"],
                    };
                } else {
                    combinedData[pageIndex]["views"] += data[key]["views"];
                    combinedData[pageIndex]["total_duration"] += data[key]["total_duration"];
                }
            }

            return combinedData;
        }


        function convertSecondsToHMS(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const remainingSeconds = seconds % 60;
            return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
        }


        async function copyToClipboard(obj) {
            const element = obj.previousElementSibling;
            if (navigator.clipboard && !element.value == false) {
                await navigator.clipboard.writeText(element.value);
                alert("コピーしました。");
            } else {
                alert("コピーに失敗しました。");
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