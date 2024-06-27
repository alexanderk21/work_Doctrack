<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
$limit = 10; // 1ページあたりの表示アイテム数

// コンテンツタイプの判別（デフォルトはPDF）
$content_type = isset($_GET['content_type']) ? $_GET['content_type'] : 'pdf';

// 現在のページ番号の取得
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($current_page - 1) * $limit;

// セッションデータのクリア
if (isset($_SESSION['csv_data']))
    unset($_SESSION["csv_data"]);
if (isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);

// ユーザーIDの取得
if (isset($_POST['user_id']) || isset($_GET['user_id'])) {
    $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : $_GET['user_id'];
    $company = isset($_POST['company']) ? $_POST['company'] : $_GET['company'];
    $name = isset($_POST['name']) ? $_POST['name'] : $_GET['name'];

    require ('common.php');

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

        if ($content_type == 'pdf') {
            if (isset($_GET['tag']) && $_GET['tag'] != '') {
                $tag = $_GET['tag'];
                $tag_ids = [];
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

            $sql = "SELECT COUNT(*) FROM pdf_versions WHERE deleted = 0 AND cid = ?";
            if (!empty($tag_ids)) {
                $placeholders = implode(',', array_fill(0, count($tag_ids), '?'));
                $sql .= " AND pdf_version_id IN ($placeholders)";
            }
            $stmt = $db->prepare($sql);

            $params = [$client_id];
            if (!empty($tag_ids)) {
                $params = array_merge($params, $tag_ids);
            }

            foreach ($params as $key => $value) {
                $stmt->bindValue($key + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }

            $stmt->execute();
            $total_records = $stmt->fetchColumn();

            $sql = "
            SELECT p.pdf_id, p.title, p.pdf_version_id, pv.latest_updated_at
            FROM (
                SELECT pdf_id, title, MAX(pdf_version_id) AS pdf_version_id
                FROM pdf_versions
                WHERE deleted = 0 AND cid = ?
                GROUP BY pdf_id, title
            ) p
            JOIN (
                SELECT pdf_id, MAX(updated_at) AS latest_updated_at
                FROM pdf_versions
                WHERE deleted = 0 AND cid = ?
                GROUP BY pdf_id
            ) pv ON p.pdf_id = pv.pdf_id
            WHERE 1 = 1";

            if (!empty($tag_ids)) {
                $placeholders = implode(',', array_fill(0, count($tag_ids), '?'));
                $sql .= " AND p.pdf_version_id IN ($placeholders)";
            }

            $sql .= " ORDER BY pv.latest_updated_at DESC LIMIT ? OFFSET ?";
            $stmt = $db->prepare($sql);

            $params = [$client_id, $client_id];
            if (!empty($tag_ids)) {
                $params = array_merge($params, $tag_ids);
            }
            $params = array_merge($params, [$limit, $offset]);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }

            $stmt->execute();
            $pdf_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (isset($tag) && empty($tag_ids)) {
                $pdf_data = [];
                $total_records = 0;
            }
            $display_data = $pdf_data;

            // リダイレクトデータの取得
        } elseif ($content_type == 'redirect') {
            if (isset($_GET['tag']) && $_GET['tag'] != '') {
                $tag = $_GET['tag'];
                $tag_ids = [];
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
            $sql = "SELECT COUNT(*) FROM redirects WHERE deleted = 0 AND cid = ?";
            if (!empty($tag_ids)) {
                $placeholders = implode(',', array_fill(0, count($tag_ids), '?'));
                $sql .= " AND id IN ($placeholders)";
            }
            $stmt = $db->prepare($sql);

            $params = [$client_id];
            if (!empty($tag_ids)) {
                $params = array_merge($params, $tag_ids);
            }

            foreach ($params as $key => $value) {
                $stmt->bindValue($key + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }

            $stmt->execute();
            $total_records = $stmt->fetchColumn();

            $sql = "SELECT id, title, url FROM redirects WHERE deleted = 0 AND cid = ?";
            if (!empty($tag_ids)) {
                $placeholders = implode(',', array_fill(0, count($tag_ids), '?'));
                $sql .= " AND id IN ($placeholders)";
            }
            $sql .= " ORDER BY updated_at DESC LIMIT ? OFFSET ?";
            $stmt = $db->prepare($sql);

            $params = [$client_id];
            if (!empty($tag_ids)) {
                $params = array_merge($params, $tag_ids);
            }

            $params = array_merge($params, [$limit, $offset]);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }

            $stmt->execute();
            $redirect_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (isset($tag) && empty($tag_ids)) {
                $redirect_data = [];
                $total_records = 0;
            }
            $display_data = $redirect_data;
        } elseif ($content_type == 'cms') {
            $sql = "SELECT count(*) FROM cmss WHERE client_id = ?";
            if (isset($_GET['tag']) && $_GET['tag'] != '') {
                $tag = $_GET['tag'];
                $sql .= " AND tag LIKE '%$tag%'";
            }
            $stmt = $db->prepare($sql);
            $stmt->execute([$client_id]);
            $total_records = $stmt->fetchColumn();

            $sql = "SELECT * FROM cmss WHERE client_id = ?";
            if (isset($_GET['tag']) && $_GET['tag'] != '') {
                $tag = $_GET['tag'];
                $sql .= " AND tag LIKE '%$tag%'";
            }
            $sql .= " ORDER BY updated_at DESC LIMIT $limit OFFSET $offset";
            $stmt = $db->prepare($sql);
            $stmt->execute([$client_id]);
            $cms_data = $stmt->fetchAll();

            $display_data = $cms_data;
        }

        // 総ページ数の計算
        $total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;

    } catch (PDOException $e) {
        echo '接続失敗: ' . $e->getMessage();
        exit();
    }
} else {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}
?>

<?php require ('header.php'); ?>

<body>
    <div id="pageMessages">

    </div>
    <div class="wrapper">
        <?php require ('menu.php'); ?>
        <main>
            <div class="d-flex justify-content-between">
                <div class="client-header-title">
                    <div class="d-flex align-items-center">
                        <h1>顧客URL</h1>
                        <p class="px-4">
                            <?= htmlspecialchars($company) ?>
                            <?= htmlspecialchars($name) ?>
                        </p>
                    </div>

                    <div class="d-flex justify-content-start align-items-center">
                        <!-- PDFファイル表示用のフォーム -->
                        <form action="" method="get">
                            <input type="hidden" name="content_type" value="pdf">
                            <input type="hidden" name="user_id" value="<?= $user_id ?>">
                            <input type="hidden" name="company" value="<?= $company ?>">
                            <input type="hidden" name="name" value="<?= $name ?>">
                            <input type="hidden" name="page" value="1">
                            <button type="submit"
                                class="btn btn-<?php echo ($content_type == 'pdf' ? 'secondary' : 'primary'); ?>">PDFファイル</button>
                        </form>
                        <!-- リダイレクト表示用のフォーム -->
                        <form action="" method="get" class="mx-2">
                            <input type="hidden" name="content_type" value="redirect">
                            <input type="hidden" name="user_id" value="<?= $user_id ?>">
                            <input type="hidden" name="company" value="<?= $company ?>">
                            <input type="hidden" name="name" value="<?= $name ?>">
                            <input type="hidden" name="page" value="1">
                            <button type="submit"
                                class="btn btn-<?php echo ($content_type == 'redirect' ? 'secondary' : 'primary'); ?>">リダイレクト</button>
                        </form>
                        <!-- CMS表示用のフォーム -->
                        <form action="" method="get">
                            <input type="hidden" name="content_type" value="cms">
                            <input type="hidden" name="user_id" value="<?= $user_id ?>">
                            <input type="hidden" name="company" value="<?= $company ?>">
                            <input type="hidden" name="name" value="<?= $name ?>">
                            <input type="hidden" name="page" value="1">
                            <button type="submit"
                                class="btn btn-<?php echo ($content_type == 'cms' ? 'secondary' : 'primary'); ?>">CMS</button>
                        </form>

                        <form action="" method="get">
                            <input type="hidden" name="content_type" value="<?= $content_type; ?>" style="margin:0 5px">
                            <input type="hidden" name="user_id" value="<?= $user_id ?>">
                            <input type="hidden" name="company" value="<?= $company ?>">
                            <input type="hidden" name="name" value="<?= $name ?>">
                            <input type="hidden" name="page" value="1">
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
                        <a href="<?= $_SERVER['HTTP_REFERER']; ?>" class="btn btn-secondary ms-2">戻る</a>
                    </div>


                </div>
                <?php require ('dropdown.php'); ?>
            </div>
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $current_page): ?>
                                <li class="page-item active">
                                    <a class="page-link" href="#">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item">
                                    <a class="page-link"
                                        href="?content_type=<?= $content_type ?>&page=<?= $i ?>&user_id=<?= $user_id ?>&company=<?= $company ?>&name=<?= $name ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </ul>
                </div>

            <?php endif; ?>

            <br>
            <table class="table">
                <tr>
                    <th>NO.</th>
                    <th>タイトル</th>
                    <th>トラッキングURL</th>
                    <th></th>
                    <th></th>
                </tr>
                <?php

                $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $url_dir = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                $url_dir = str_replace(basename($url_dir), '', $url_dir);

                if (!empty($display_data)) {
                    $no = ($current_page - 1) * 10 + 1;
                    if (!empty($pdf_data)) {
                        foreach ($pdf_data as $pdf) {
                            $title = $pdf['title'];
                            $id = $pdf['pdf_id'];
                            $url_ = $id . '/' . $user_id;
                            $t_url = $url_dir . 's?t=' . $url_;
                            echo '<tr>';
                            echo '<td>' . $no++ . '</td>';
                            echo '<td>' . $title . '</td>';
                            echo '<td>' . $t_url . '</td>';
                            echo '
                            <td>
                            <input type="hidden" value="' . $t_url . '" />
                            <button onclick="copyToClipboard(this)" class="btn btn-primary">
                            コピー
                            </button>
                            </td>';
                            echo '
                            <td>
                            
                            <button onclick="pdf_link(`' . $id . '`,`' . $pdf["pdf_version_id"] . '`)" class="btn btn-primary">
                            プレビュー
                            </button>
                            </td>';
                            echo '</tr>';
                        }
                    }
                    
                    if (!empty($redirect_data)) {
                        foreach ($redirect_data as $redirect) {
                            $title = $redirect['title'];
                            $id = $redirect['id'];
                            $url_ = $id . '/' . $user_id;
                            $t_url = $url_dir . 'r?t=' . $url_;
                            
                            echo '<tr>';
                            echo '<td>' . $no++ . '</td>';
                            echo '<td>' . $title . '</td>';
                            echo '<td>' . $t_url . '</td>';
                            echo '
                            <td>
                            <input type="hidden" value="' . $t_url . '" />
                            <button onclick="copyToClipboard(this)" class="btn btn-primary">
                            コピー
                            </button>
                            </td>';
                            echo '
                            <td>
                            <form action="' . $redirect['url'] . '" target="_blank">
                            <button class="btn btn-primary">
                            プレビュー
                            </button>
                            </form>
                            </td>';
                            echo '</tr>';
                        }
                    }

                    if (!empty($cms_data)) {
                        foreach ($cms_data as $cms) {
                            $title = $cms['title'];
                            $cms_id = $cms['cms_id'];
                            $url_ = $cms_id . '/' . $user_id;
                            $t_url = $url_dir . 'c?t=' . $url_;
                            
                            echo '<tr>';
                            echo '<td>' . $no++ . '</td>';
                            echo '<td>' . $title . '</td>';
                            echo '<td>' . $t_url . '</td>';
                            echo '
                        <td>
                            <input type="hidden" value="' . $t_url . '" />
                            <button onclick="copyToClipboard(this)" class="btn btn-primary">
                            コピー
                            </button>
                        </td>';
                            echo '
                        <td>
                            <a href="' . $url_dir . 'c?t=' . $cms_id . '" class="btn btn-primary" target="_blank">
                            プレビュー
                            </a>
                        </td>';
                            echo '</tr>';
                        }
                    }
                }
                ?>
            </table>
        </main>
    </div>
    <script src="./assets/js/alert.js"></script>

    <script>
        async function copyToClipboard(obj) {
            const element = obj.previousElementSibling;
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(element.value);
                copy_alert();
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = element.value;
                textarea.style.position = 'absolute';
                textarea.style.left = '-99999999px';
                document.body.prepend(textarea);
                textarea.focus();
                textarea.select();
                try {
                    document.execCommand('copy');
                    copy_alert();
                } catch (err) {
                    alert('コピーに失敗しました。')
                } finally {
                    textarea.remove();
                }
            }
        }

        function copy_alert() {
            $(document).ready(function () {
                toastr.options.timeOut = 1500; // 1.5s
                toastr.success('コピーしました。');
            });
        }

        function pdf_link(tracking_id, pdf_version_id) {
            window.open('./pdf/' + tracking_id + '_' + pdf_version_id + '.pdf', '_blank');
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.0.1/css/toastr.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.0.1/js/toastr.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"
        integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous">
        </script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous">
        </script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous">
        </script>

</body>

</html>