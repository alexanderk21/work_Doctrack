<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (isset($_SESSION['csv_data']))
    unset($_SESSION["csv_data"]);
if (isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);

require ('common.php');


try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare("SELECT p.* FROM pdf_versions p
                    INNER JOIN (
                        SELECT pdf_id, MAX(uploaded_at) AS max_uploaded_at
                        FROM pdf_versions
                        WHERE cid=? AND deleted=0
                        GROUP BY pdf_id
                    ) AS subquery ON p.pdf_id = subquery.pdf_id AND p.uploaded_at = subquery.max_uploaded_at
                    WHERE p.cid=? AND p.deleted=0 AND p.page_cnt != 0
                    ORDER BY p.uploaded_at DESC
                ");
    $stmt->execute([$client_id, $client_id]);
    $pdfs = $stmt->fetchAll();

    $pdf_id = $_GET['pdf_id'] ?? $pdfs[0]['pdf_id'];


    $stmt = $db->prepare("SELECT * FROM pdf_versions WHERE cid=? AND pdf_id=? AND deleted = 0");
    $stmt->execute([$client_id, $pdf_id]);
    $pdf_versions_data = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}


?>
<?php require ('header.php'); ?>
<style>
    #chart {
        max-width: 1000px;
        margin: 35px auto;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<body>
    <div class="wrapper">
        <?php require ('menu.php'); ?>
        <main>
            <div class="d-flex justify-content-between">
                <h1>グラフ表示</h1>
                <?php require ('dropdown.php'); ?>
            </div>
            <br>
            <div class="d-flex align-items-center">
                <form action="" method="get">
                    <select name="pdf_id" id="" class="mx-2">
                        <?php foreach ($pdfs as $pdf): ?>
                            <option value="<?= $pdf['pdf_id']; ?>" <?= $pdf_id == $pdf['pdf_id'] ? 'selected' : ''; ?>>
                                <?= $pdf['title']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary">確認</button>
                </form>
                <a href="./pdf.php" class="btn btn-secondary ms-2">戻る</a>
            </div>
            <br>

            <table class="w-50 table table-bordered mx-auto">
                <tr>
                    <th>ページ数</th>
                    <th>アクセス数</th>
                    <th>閲覧時間</th>
                    <th>ページ移動数</th>
                    <th>ＣＴＡクリック数</th>
                    <th>登録日</th>
                </tr>
                <?php

                $row = $pdf_versions_data;
                $total_access_cnt = 0;

                try {
                    $db = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    ]);
                    $pdf_version_id = $row['pdf_version_id'];
                    $cid = $ClientData['id'];
                    $graph2data = [];

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
                            $graph2data[$page_count] = [];
                        }
                    }
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
                        $page = $pdf_version['page'] == 0 ? 1 : $pdf_version['page'];
                        if (isset($graph2data[$page]['page_num'])) {
                            $graph2data[$page]['page_num']++;
                            $graph2data[$page]['duration'] += $pdf_version["duration"];
                        } else {
                            $graph2data[$page]['page_num'] = 1;
                            $graph2data[$page]['duration'] = $pdf_version["duration"];
                        }
                    }
                    // if (isset($graph2data[0])) {
                    //     unset($graph2data[0]);
                    // }
                    foreach ($graph2data as $k => $gd) {
                        $graph2label[] = $k;
                        $graph2page[] = $gd['page_num'] ?? 0;
                        $graph2time[] = $gd['duration'] ?? 0;
                    }

                    $total_duration = secondsToTime($total_duration_seconds);

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
                    <td><?= $page_count ?></td>
                    <td><?= $total_access_cnt ?></td>
                    <td><?= $total_duration ?></td>
                    <td><?= $total_page_moved ?></td>
                    <td><?= $total_cta_clicks ?></td>
                    <td><?= substr($row['uploaded_at'], 0, 10) ?></td>
                </tr>
            </table>
            <div id="chart"></div>
        </main>
    </div>

    <script>
        var options = {
            series: [{
                name: '閲覧数',
                type: 'column',
                data: <?= json_encode($graph2page); ?>
            }, {
                name: '閲覧時間',
                type: 'line',
                data: <?= json_encode($graph2time); ?>
            }],
            chart: {
                height: 350,
                type: 'line',
            },
            stroke: {
                width: [0, 4]
            },
            title: {
                text: ''
            },
            dataLabels: {
                enabled: true,
                enabledOnSeries: [1],
                formatter: function (value) {
                    var hours = Math.floor(value / 3600);
                    var minutes = Math.floor((value % 3600) / 60);
                    var seconds = value % 60;
                    return hours.toString().padStart(2, '0') + ':' + minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
                }
            },
            labels: <?= json_encode($graph2label); ?>,
            xaxis: {
                type: 'text'
            },
            yaxis: [{
                title: {
                    text: '閲覧数',
                },

            }, {
                opposite: true,
                labels: {
                    formatter: function (value) {
                        var hours = Math.floor(value / 3600);
                        var minutes = Math.floor(Math.floor(value % 3600) / 60);
                        var seconds = value % 60;
                        return hours.toString().padStart(2, '0') + ':' + minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
                    }
                },
                type: 'datetime',
                title: {
                    text: '閲覧時間',
                }
            }]
        };

        var chart = new ApexCharts(document.querySelector("#chart"), options);
        chart.render();

    </script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
        crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI"
        crossorigin="anonymous"></script>
</body>

</html>