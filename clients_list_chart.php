<?php
session_start();
require ('common.php');
//ステージ
$sql = "SELECT * FROM stages WHERE client_id = ? AND stage_state = 1 ORDER BY stage_point DESC";
$stmt = $db->prepare($sql);
$stmt->execute([$client_id]);
$stages = $stmt->fetchAll();
//タグ
// $tags = [];
// $stmt = $db->prepare("SELECT * FROM tag_sample WHERE client_id=?");
// $stmt->execute([$client_id]);
// $tag_data = $stmt->fetchAll();

// foreach ($tag_data as $t) {
//     $tags[] = $t['tag_name'];
// }
$tags = explode(',', $ClientUser['tags']);

$tag2customer = json_decode($_POST['tag2customer'], true);
$stage2customer = json_decode($_POST['stage2customer'], true);
?>
<style>
    .container {
        width: 80%;
        margin: auto;
    }
</style>
<?php require ('header.php'); ?>

<body>
    <div class="wrapper">
        <?php require ('menu.php'); ?>
        <main class="d-flex">
            <div class="w-50">
                <h1>グラフ表示</h1>
                <div class="d-flex">
                    <button id="stage_btn" onclick="changeState('stage')"
                        class="state_btn btn btn-secondary me-2">ステージ</button>
                    <button id="tag_btn" onclick="changeState('tag')" class="state_btn btn btn-primary">タグ</button>
                </div>
                <input type="hidden" id="state" value="stage">
                <table class="data_table table table-bordered mt-2" id="stage_table">
                    <tr>
                        <th class="text-center">ステージ</th>
                        <th class="text-center">顧客数</th>
                    </tr>
                    <?php foreach ($stages as $key => $stage): ?>
                        <tr>
                            <td>
                                <input type="checkbox" id="stage<?= $key; ?>" value="<?= $stage['stage_name']; ?>"
                                    <?= ($stage['stage_name'] == '0ポイント') ? '' : 'checked'; ?>>
                                <label for="stage<?= $key; ?>">
                                    <?= $stage['stage_name']; ?>
                                </label>
                            </td>
                            <td class="value">
                                <?= $stage2customer[$stage['stage_name']]; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <table class="data_table table table-bordered mt-2 d-none" id="tag_table">
                    <tr>
                        <th class="text-center">タグ</th>
                        <th class="text-center">顧客数</th>
                    </tr>
                    <?php foreach ($tags as $key => $tag): ?>
                        <tr>
                            <td>
                                <input type="checkbox" id="tag<?= $key; ?>" value="<?= $tag; ?>" checked>
                                <label for="tag<?= $key; ?>">
                                    <?= $tag; ?>
                                </label>
                            </td>
                            <td class="value">
                                <?= $tag2customer[$tag]; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <div class="mt-2">
                    <button class="btn btn-primary me-2" onclick="drawChart()">確認</button>
                    <a class="btn btn-dark" href="./clients_list.php">戻る</a>
                </div>
            </div>
            <div class="w-50 container">
                <div id="chart_div">
                    <canvas id="myChart"></canvas>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.2.2/Chart.min.js"></script>
    <script>
        drawChart();
        function changeState(state) {
            $('.state_btn').removeClass('btn-secondary');
            $('.state_btn').addClass('btn-primary');
            $('#' + state + '_btn').removeClass('btn-primary');
            $('#' + state + '_btn').addClass('btn-secondary');
            $('#state').val(state);
            $('.data_table').addClass('d-none');
            $('#' + state + '_table').removeClass('d-none');
            drawChart();
        }
        function drawChart() {
            $('iframe').remove();
            $('canvas').remove();
            $('#chart_div').html('<canvas id="myChart"></canvas>');
            var state = $('#state').val();
            var stageTable = document.getElementById("stage_table");
            var tagTable = document.getElementById("tag_table");
            var ctx = document.getElementById("myChart").getContext('2d');
            chartLabels = [];
            chartBackgroundColor = [];
            chartValue = [];
            if (state == 'stage') {
                var checkboxes = stageTable.querySelectorAll('input[type="checkbox"]');
            } else {
                var checkboxes = tagTable.querySelectorAll('input[type="checkbox"]');
            }
            checkboxes.forEach(function (checkbox) {
                if (checkbox.checked) {
                    var parentRow = checkbox.closest('tr');
                    var stageOrTag = parentRow.querySelector('.value').innerText;
                    var value = parseInt(stageOrTag);

                    chartLabels.push(parentRow.querySelector('label').innerText);
                    chartValue.push(value);
                    chartBackgroundColor.push(getRandomColor());
                }
            });
            var myChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        backgroundColor: chartBackgroundColor,
                        data: chartValue
                    }]
                }
            });
        }
        function getRandomColor() {
            var r = Math.floor(Math.random() * 156) + 70;
            var g = Math.floor(Math.random() * 156) + 70;
            var b = Math.floor(Math.random() * 156) + 70;
            return 'rgb(' + r + ',' + g + ',' + b + ')';
        }

    </script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
        crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI"
        crossorigin="anonymous"></script>
</body>