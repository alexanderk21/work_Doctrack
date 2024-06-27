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

$id = $_GET['id'];

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $stmt = $db->prepare("SELECT * FROM cmss WHERE id=? ");
    $stmt->execute([$id]);
    $cms_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $title = $cms_data['title'];
    $content = is_null($cms_data['content']) ? [] : json_decode($cms_data['content'], true);

    $exist_ids = [
        'pdf' => [],
        'redl' => []
    ];
    foreach ($content as $c) {
        if ($c['type'] == 'pdf') {
            $exist_ids['pdf'][] = $c['template'];
        } else {
            $exist_ids['redl'][] = $c['template'];
        }
    }
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

    $select_tag = $_GET['tag'] ?? '';
    $segment = $_GET['segment'] ?? '';

    $sql = "SELECT * FROM redirects WHERE cid = ? AND deleted = 0";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $redl_data = $stmt->fetchAll();
    $id2redl = [];
    foreach ($redl_data as $each) {
        $id2redl[$each['id']] = $each;
    }

    $sql = "SELECT * FROM pdf_versions WHERE cid = ? AND deleted = 0";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $pdf_data = $stmt->fetchAll();
    $id2pdf = [];
    foreach ($pdf_data as $each) {
        $id2pdf[$each['pdf_id']] = $each;
    }

    if ($select_tag == '未選択') {
        $sql = "SELECT r.* FROM redirects r LEFT JOIN tags t ON t.table_id = r.id 
        WHERE t.tags IS NULL 
        AND r.cid = ? 
        AND r.deleted = 0";
        $stmt = $db->prepare($sql);
        $stmt->execute([$client_id]);
        $redl_data = $stmt->fetchAll();

        $sql = "SELECT p.* FROM pdf_versions p LEFT JOIN tags t ON t.table_id = p.pdf_version_id 
        WHERE t.tags IS NULL 
        AND p.cid = ? 
        AND p.deleted = 0";
        $stmt = $db->prepare($sql);
        $stmt->execute([$client_id]);
        $pdf_data = $stmt->fetchAll();

    } elseif ($select_tag != '') {
        $tags = "'" . $select_tag . "'";

        $sql = "SELECT r.* FROM tags t JOIN redirects r ON t.table_id = r.id WHERE";
        $sql .= " t.tags IN ($tags)";
        $sql .= " AND r.cid = ? AND t.table_name = 'redirects' AND r.deleted = 0";
        $stmt = $db->prepare($sql);
        $stmt->execute([$client_id]);
        $redl_data = $stmt->fetchAll();
        $redl_data = array_map("unserialize", array_unique(array_map("serialize", $redl_data)));

        $sql = "SELECT p.* FROM tags t JOIN pdf_versions p ON t.table_id = p.pdf_version_id WHERE";
        $sql .= " t.tags IN ($tags)";
        $sql .= " AND p.cid = ? AND t.table_name = 'pdf_versions' AND p.deleted = 0";
        $stmt = $db->prepare($sql);
        $stmt->execute([$client_id]);
        $pdf_data = $stmt->fetchAll();
        $pdf_data = array_map("unserialize", array_unique(array_map("serialize", $pdf_data)));
    }

    $status = $_GET['status'] ?? '';

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
</style>

<body>
    <div id="pageMessages">
    </div>
    <div class="wrapper">
        <?php require ('menu.php'); ?>
        <main>
            <div class="d-flex justify-content-between">
                <div class="header-with-help">
                    <h1>CMS管理</h1><span><?= $title; ?></span>
                </div>
                <?php require ('dropdown.php'); ?>
            </div>
            <input type="hidden" id="status" value="<?= $status; ?>">
            <div class="mb-2 d-flex align-items-center">
                <form action="" method="get" id="tagForm">
                    <input type="hidden" name="status" value="edit">
                    <input type="hidden" name="id" value="<?= $id; ?>">
                    <label for="segment">区分</label>
                    <select name="segment" id="segment" class="m-2">
                        <option value="pdf">PDF</option>
                        <option value="redl" <?= ($segment == 'redl') ? 'selected' : ''; ?>>リダイレクト</option>
                    </select>
                    <label for="tag">タグ</label>
                    <select name="tag" id="tag" class="m-2">
                        <option value="">全選択</option>
                        <?php foreach ($tag_array as $t): ?>
                            <option value="<?= $t; ?>" <?= $t == $select_tag ? 'selected' : ''; ?>>
                                <?= $t; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <form action="./back/add_cms.php" method="post">
                    <input type="hidden" name="id" value="<?= $id; ?>">
                    <input type="hidden" name="type" value="<?= ($segment != 'redl') ? 'pdf' : 'redl'; ?>">
                    <label for="template"><?= ($segment != 'redl') ? 'PDF' : 'リダイレクト'; ?></label>
                    <select name="template" id="template" required>
                        <?php if ($segment == 'redl'): ?>
                            <?php foreach ($redl_data as $r):
                                if (!in_array($r['id'], $exist_ids['redl'])): ?>
                                    <option value="<?= $r['id']; ?>"><?= $r['title']; ?></option>
                                <?php endif;
                            endforeach;
                            ?>
                        <?php else: ?>
                            <?php foreach ($pdf_data as $p):
                                if (!in_array($p['pdf_id'], $exist_ids['pdf'])): ?>
                                    <option value="<?= $p['pdf_id']; ?>"><?= $p['title']; ?></option>
                                <?php endif;
                            endforeach;
                            ?>
                        <?php endif; ?>
                    </select>
                    <button class="btn btn-warning m-2">追加</button>
                </form>
            </div>
            <form action="./back/ch_cms_mana.php" method="post">
                <input type="hidden" name="id" value="<?= $id; ?>">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="max-width: 50px;">No.</th>
                            <th>区分</th>
                            <th>タイトル</th>
                            <th>表示順</th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1;
                        foreach ($content as $row): ?>
                            <tr>
                                <input type="hidden" class="row_order" name="row[<?= $i; ?>]" value="<?= $i; ?>">
                                <td class="row_no"><?= $i++; ?></td>
                                <td><?= $row['type'] == 'pdf' ? 'PDF' : 'リダイレクト'; ?></td>
                                <td><?= $row['type'] == 'pdf' ? $id2pdf[$row['template']]['title'] : $id2redl[$row['template']]['title'] ?>
                                </td>
                                <td>
                                    <button type="button" id="row1Up" class="rowUp btn btn-success p-1">
                                        <i style="font-size: 25px;" class="fa fa-arrow-circle-up"></i>
                                    </button>
                                    <button type="button" id="row1Down" class="rowDown btn btn-success p-1">
                                        <i style="font-size: 25px;" class="fa fa-arrow-circle-down"></i>
                                    </button>
                                </td>
                                <td>
                                    <button type="button" onclick="del_row(`<?= $i - 2; ?>`)"
                                        class="btn btn-danger" <?= in_array('削除', $func_limit) ? 'disabled' : ''; ?>>削除</button>
                                </td>
                                <td>
                                    <?php
                                    if ($row['type'] == 'pdf') {
                                        $url_ = $id2pdf[$row['template']]['pdf_id'] . '/';
                                        $t_url = $url_dir . 's?t=' . $url_;
                                    } else {
                                        $url_ = $id2redl[$row['template']]['id'] . '/';
                                        $t_url = $url_dir . 'r?t=' . $url_;
                                    }
                                    ?>
                                    <a href="<?= $t_url; ?>" target="_blank" class="btn btn-primary">プレビュー</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="d-flex justify-content-center align-items-center">
                    <a href="<?= $url_dir . 'c?t=' . $cms_data['cms_id']; ?>" target="_blank" class="btn btn-primary"
                        aria-disabled="true">プレビュー</a>
                    <button class="btn btn-success m-3" id="save_button">保存</button>
                    <button type="button" id="edit_button" class="btn btn-dark m-3" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                    <a href="./cms.php" class="btn btn-secondary">戻る</a>
                </div>
            </form>
            <form action="./back/del_cms_mana.php" method="post" id="delRowForm">
                <input type="hidden" name="id" value="<?= $id; ?>">
                <input type="hidden" name="row_no" id="del_id">
            </form>
        </main>
    </div>

    <script>
        var status = $('#status').val();

        $(document).ready(function () {
            if (status != 'edit') {
                $('select').prop('disabled', true);
                $('button').prop('disabled', true);
                $('#save_button').hide();
                <?php if(!in_array('変更', $func_limit)): ?>
                $('#edit_button').prop('disabled', false);
                <?php endif;?>
                $('.dropdown-toggle').prop('disabled', false);
            } else {
                $('#edit_button').hide();
            }
        });

        $('#edit_button').click(function () {
            $('#status').val('edit')
            $('#save_button').show();
            $('#edit_button').hide();
            $('select').prop('disabled', false);
            $('button').prop('disabled', false);

        })

        $('#tag').change(function () {
            $('#tagForm').submit();
        });
        $('#segment').change(function () {
            $('#tagForm').submit();
        });
        function del_row(id) {
            $('#del_id').val(id);
            $('#delRowForm').submit()
        }
    </script>
    <script>
        $(".rowUp").click(function () {
            var row = $(this).closest("tr");
            var previous = row.prev();
            if (previous.is("tr")) {
                row.detach();
                previous.before(row);

                row.fadeOut();
                row.fadeIn();
            }
            updateRowNumbers();
        });

        $(".rowDown").click(function () {
            var row = $(this).closest("tr");
            var next = row.next();
            if (next.is("tr")) {
                row.detach();
                next.after(row);

                row.fadeOut();
                row.fadeIn();
            }
            updateRowNumbers();
        });
        function updateRowNumbers() {
            $(".row_no").each(function (index) {
                $(this).text(index + 1);
                $(this).closest('tr').find('.row_order').val(index + 1);
            });
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