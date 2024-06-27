<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'common.php';
if (isset($_SESSION['csv_data'])) {
    unset($_SESSION["csv_data"]);
}

if (isset($_SESSION['csv_data_form'])) {
    unset($_SESSION["csv_data_form"]);
}

if (isset($_SESSION['email_data'])) {
    unset($_SESSION['email_data']);
}

$search_params = http_build_query([
    'search_pdf_red_null' => $_GET['search_pdf_red_null'] ?? null,
    'date1' => $_GET['date1'] ?? null,
    'date2' => $_GET['date2'] ?? null,
    'search' => $_GET['search'] ?? null,
    'search_title' => $_GET['search_title'] ?? null,
    'segment' => $_GET['segment'] ?? null,
    'tag' => $_GET['tag'] ?? null,
    'search_pdf_id' => $_GET['search_pdf_id'] ?? null,
    'search_redirect_id' => $_GET['search_redirect_id'] ?? null,
    'user_id' => $_GET['user_id'] ?? null,
]);

$_SESSION['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] . $directory;

$_SESSION['SERVER_URL'] = $current_directory_url;

$search_value = "";
$title_value = "";
$userid_value = "";
$date1_value = !empty($_GET['date1']) ? $_GET['date1'] : '';
$date2_value = !empty($_GET['date2']) ? $_GET['date2'] : '';
try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $stmt = $db->prepare("SELECT * FROM clients WHERE cid=?");
    $stmt->execute([$client_id]);
    $clients = $stmt->fetchAll();

    foreach ($clients as $client) {
        $cusers[$client['id']] = $client['last_name'] . $client['first_name'];
    }


    $selected_tags = [];

    if ($ClientUser['role'] == 1) {
        $tag_array = [];
        $stmt = $db->prepare("SELECT * FROM tag_sample WHERE client_id=?");
        $stmt->execute([$client_id]);
        $tag_data = $stmt->fetchAll();
        foreach ($tag_data as $t) {
            $tag_array[] = $t['tag_name'];
        }
    } else {
        $tag_array = explode(',', $ClientUser['tags']);
    }

    $segment = [];
    if (isset($_GET['segment'])) {
        $segment = $_GET['segment'];
    } else {
        $segment = [
            'pdf' => 'pdf',
            'redl' => 'redl',
            'cms' => 'cms',
        ];
    }
    if (isset($segment['pdf'])) {
        if (isset($_GET['tag']) && $_GET['tag'] != '') {
            $selected_tags = $_GET['tag'];
            $tag_ids = [];
            $tag = reset($selected_tags);

            $sql = "SELECT table_id FROM tags WHERE table_name = 'pdf_versions' AND";
            $sql .= " (tags LIKE '%$tag%'";
            for ($i = 1; $i < count($selected_tags); $i++) {
                $tag = $selected_tags[$i];
                $sql .= " OR tags LIKE '%$tag%'";
            }
            $sql .= ")";

            $stmt = $db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll();

            if (!empty($result)) {
                foreach ($result as $row) {
                    $tag_ids[] = $row['table_id'];
                }
            }
        }
        // 閲覧時間が0のレコードをuser_pdf_accessから削除
        $sql = "DELETE FROM user_pdf_access WHERE duration = 0";
        $stmt = $db->prepare($sql);
        $stmt->execute();

        // PDFのアクセス情報を取得
        $sql = "SELECT upa.*, pv.title, pv.pdf_id, usr.* FROM user_pdf_access upa 
        JOIN pdf_versions pv ON upa.pdf_version_id = pv.pdf_version_id 
        JOIN users usr ON upa.user_id = usr.user_id 
        WHERE upa.cid=?";
        if (isset($_GET['search']) && ($_GET['search'] != '')) {
            $sql .= " AND (upa.user_id LIKE '%" . $_GET['search'] . "%' OR usr.company LIKE '%" . $_GET['search'] . "%' OR usr.email LIKE '%" . $_GET['search'] . "%' OR usr.url LIKE '%" . $_GET['search'] . "%' OR usr.surename LIKE '%" . $_GET['search'] . "%' OR usr.name LIKE '%" . $_GET['search'] . "%' OR usr.tel LIKE '%" . $_GET['search'] . "%' OR usr.depart_name LIKE '%" . $_GET['search'] . "%' OR usr.director LIKE '%" . $_GET['search'] . "%' OR usr.address LIKE '%" . $_GET['search'] . "%' OR usr.free_memo LIKE '%" . $_GET['search'] . "%')";
            $search_value = $_GET['search'];
        }
        if (isset($_GET['search_title']) && ($_GET['search_title'] != '')) {
            $sql .= " AND (pv.title LIKE '%" . $_GET['search_title'] . "%')";
            $title_value = $_GET['search_title'];
        }

        if ((isset($_GET['tag'])) && $_GET['tag'] != "") {
            $sql .= " AND pv.pdf_version_id IN (" . implode(",", array_fill(0, count($tag_ids), "?")) . ")";
        }

        if (!empty($_GET['date1']) && !empty($_GET['date2'])) {
            $sql .= " AND upa.accessed_at BETWEEN '$date1_value' AND DATE_ADD('$date2_value', INTERVAL 1 DAY)";
        } else if (!empty($_GET['date1'])) {
            $sql .= " AND upa.accessed_at >= '$date1_value' ";
        } else if (!empty($_GET['date2'])) {
            $sql .= " AND upa.accessed_at <= '$date2_value' ";
        }
        if (!empty($_GET['date1']) && !empty($_GET['date2'])) {
            $sql .= " AND upa.accessed_at BETWEEN '" . $_GET['date1'] . "' AND '" . $_GET['date2'] . "'";
        }

        $origin_sql = $sql . ' ORDER BY session_id';
        $stmt = $db->prepare($sql);
        if ((isset($_GET['tag'])) && $_GET['tag'] != '') {
            $stmt->execute(array_merge([$client_id], $tag_ids));
        } else {
            $stmt->execute([$client_id]);
        }
        $user_pdf_access = $stmt->fetchAll();
        if (isset($_GET['search_pdf_red_null'])) {
            $sql .= " AND 1=0";
            $stmt = $db->prepare($sql);
            $stmt->execute([$client_id]);
            $user_pdf_access = $stmt->fetchAll();
        } else {
            if (isset($_GET['search_redirect_id']) && !isset($_GET['search_pdf_id']) && !isset($_GET['search_cms_id'])) {
                $user_pdf_access = [];
                $user_cms_access = [];
            } else {
                // 送信一覧・配信一覧　PDF検索
                if (isset($_GET['search_pdf_id'])) {
                    $user_pdf_access = [];
                    $search_pdf_id = str_replace("'", "", $_GET['search_pdf_id']);
                    $pdf_ids = explode(',', $search_pdf_id);
                    foreach ($pdf_ids as $pdf_access_id) {
                        $pdf_sql = $sql;
                        $pdf_sql .= " AND upa.access_id = '$pdf_access_id'";
                        $stmt = $db->prepare($pdf_sql);
                        $stmt->execute([$client_id]);
                        $access2pdf = $stmt->fetch(PDO::FETCH_ASSOC);
                        $user_pdf_access[] = $access2pdf;
                    }
                }
            }
        }

        $user_pdf_access_count = 0;

    } else {
        $user_pdf_access = [];
    }
    $user_pdf_access = array_filter($user_pdf_access, function ($subArray) {
        return !empty($subArray);
    });
    foreach ($user_pdf_access as $access) {
        if (!isset($access['page'])) {
        }
        if ($access['page'] > 0) {
            $user_pdf_access_count++;
        }
    }
    // elseif (isset($_GET['segment']) && $_GET['segment'] == 'redl'):

    // リダイレクトのアクセス情報を取得
    // GETメソッドでuser_idがGETされたときには、user_idでデータを絞る
    if (isset($segment['redl'])) {
        if (isset($_GET['tag']) && $_GET['tag'] != '') {
            $selected_tags = $_GET['tag'];
            $tag_ids = [];
            $tag = reset($selected_tags);

            $sql = "SELECT table_id FROM tags WHERE table_name = 'redirects' AND";
            $sql .= " (tags LIKE '%$tag%'";
            for ($i = 1; $i < count($selected_tags); $i++) {
                $tag = $selected_tags[$i];
                $sql .= " OR tags LIKE '%$tag%'";
            }
            $sql .= ")";

            $stmt = $db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll();
            if (!empty($result)) {
                foreach ($result as $row) {
                    $tag_ids[] = $row['table_id'];
                }
            }
        }

        $sql = "SELECT ura.*, r.title, r.url, usr.company FROM user_redirects_access ura 
        JOIN redirects r ON ura.redirect_id = r.id 
        JOIN users usr ON ura.user_id = usr.user_id 
        WHERE ura.cid=?";

        if (isset($_GET['user_id'])) {
            $sql .= " AND ura.user_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$client_id, $_GET['user_id']]);
        } else {

            // タイトルでの検索
            if (isset($_GET['search']) && ($_GET['search'] != '')) {
                $sql .= " AND (ura.user_id LIKE '%" . $_GET['search'] . "%' OR usr.company LIKE '%" . $_GET['search'] . "%' OR usr.email LIKE '%" . $_GET['search'] . "%' OR usr.url LIKE '%" . $_GET['search'] . "%' OR usr.surename LIKE '%" . $_GET['search'] . "%' OR usr.name LIKE '%" . $_GET['search'] . "%' OR usr.tel LIKE '%" . $_GET['search'] . "%' OR usr.depart_name LIKE '%" . $_GET['search'] . "%' OR usr.director LIKE '%" . $_GET['search'] . "%' OR usr.address LIKE '%" . $_GET['search'] . "%' OR usr.free_memo LIKE '%" . $_GET['search'] . "')";
            }
            if (isset($_GET['search_title']) && ($_GET['search_title'] != '')) {
                $sql .= " AND (r.title LIKE '%" . $_GET['search_title'] . "%')";
            }
            // アクセス日時での検索
            if (!empty($_GET['date1']) && !empty($_GET['date2'])) {
                $sql .= " AND ura.accessed_at BETWEEN '$date1_value' AND DATE_ADD('$date2_value', INTERVAL 1 DAY)";
            } else if (!empty($_GET['date1'])) {
                $sql .= " AND ura.accessed_at >= '$date1_value' ";
            } else if (!empty($_GET['date2'])) {
                $sql .= " AND ura.accessed_at <= '$date2_value' ";
            }

            if ((isset($_GET['tag'])) && $_GET['tag'] != '') {
                $sql .= " AND r.id IN (" . implode(",", array_fill(0, count($tag_ids), "?")) . ")";
            }

            $stmt = $db->prepare($sql);
            if ((isset($_GET['tag'])) && $_GET['tag'] != '') {
                $stmt->execute(array_merge([$client_id], $tag_ids));
            } else {
                $stmt->execute([$client_id]);
            }
        }
        $user_redirects_access = $stmt->fetchAll();
        if (isset($_GET['search_pdf_red_null'])) {
            $user_redirects_access = [];
        } else {
            if (!isset($_GET['search_redirect_id']) && !isset($_GET['search_cms_id']) && isset($_GET['search_pdf_id'])) {
                $user_redirects_access = [];
                $user_cms_access = [];
            } else {
                // 送信一覧・配信一覧　リダイレクト検索
                if (isset($_GET['search_redirect_id'])) {
                    $user_redirects_access = [];
                    $search_redirect_id = str_replace("'", "", $_GET['search_redirect_id']);
                    $search_redirect_ids = explode(',', $search_redirect_id);
                    foreach ($search_redirect_ids as $search_redirect_id) {
                        $red_sql = $sql;
                        $red_sql .= " AND ura.access_id = '$search_redirect_id'";
                        $stmt = $db->prepare($red_sql);
                        $stmt->execute([$client_id]);
                        $access2red = $stmt->fetch(PDO::FETCH_ASSOC);
                        $user_redirects_access[] = $access2red;
                    }
                }
            }
        }

    } else {
        $user_redirects_access = [];
    }
    $user_redirects_access = array_filter($user_redirects_access, function ($subArray) {
        return !empty($subArray);
    });
    // CMSのアクセス情報を取得
    if (isset($segment['cms'])) {
        $sql = "SELECT uca.*, c.title, usr.company FROM user_cms_access uca 
        JOIN cmss c ON uca.cms_id = c.cms_id 
        JOIN users usr ON uca.user_id = usr.user_id 
        WHERE uca.cid = ?";

        $params = [$client_id];

        if (isset($_GET['user_id'])) {
            $sql .= " AND uca.user_id = ?";
            $params[] = $_GET['user_id'];
        } else {
            // Search by user details or title
            if (isset($_GET['search']) && ($_GET['search'] != '')) {
                $search = "%" . $_GET['search'] . "%";
                $sql .= " AND (uca.user_id LIKE ? OR usr.company LIKE ? OR usr.email LIKE ? OR usr.url LIKE ? OR usr.surename LIKE ? OR usr.name LIKE ? OR usr.tel LIKE ? OR usr.depart_name LIKE ? OR usr.director LIKE ? OR usr.address LIKE ? OR usr.free_memo LIKE ?)";
                $params = array_merge($params, array_fill(0, 11, $search));
            }
            if (isset($_GET['search_title']) && ($_GET['search_title'] != '')) {
                $search_title = "%" . $_GET['search_title'] . "%";
                $sql .= " AND c.title LIKE ?";
                $params[] = $search_title;
            }
            // Search by access date
            if (!empty($_GET['date1']) && !empty($_GET['date2'])) {
                $sql .= " AND uca.created_at BETWEEN '$date1_value' AND DATE_ADD('$date2_value', INTERVAL 1 DAY)";
            } else if (!empty($_GET['date1'])) {
                $sql .= " AND uca.created_at >= '$date1_value' ";
            } else if (!empty($_GET['date2'])) {
                $sql .= " AND uca.created_at <= '$date2_value' ";
            }
            // Search by tags
            if (isset($_GET['tag']) && !empty($_GET['tag'])) {
                foreach ($_GET['tag'] as $tag) {
                    $sql .= " AND JSON_CONTAINS(c.tag, ?)";
                    $params[] = json_encode($tag);
                }
            }
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $user_cms_access = $stmt->fetchAll();


        if (isset($_GET['search_pdf_red_null'])) {
            $user_cms_access = [];
        } elseif (!isset($_GET['search_redirect_id']) && !isset($_GET['search_pdf_id']) && isset($_GET['search_cms_id'])) {
            $user_redirects_access = [];
            $user_pdf_access = [];
        } else {
            if (isset($_GET['search_cms_id'])) {
                $user_cms_access = [];
                $search_cms_id = str_replace("'", "", $_GET['search_cms_id']);
                $search_cms_ids = explode(',', $search_cms_id);
                foreach ($search_cms_ids as $search_cms_id) {
                    $red_sql = $sql;
                    $red_sql .= " AND ura.cms_id = '$search_cms_id'";
                    $stmt = $db->prepare($red_sql);
                    $stmt->execute([$client_id]);
                    $access2red = $stmt->fetch(PDO::FETCH_ASSOC);
                    $user_cms_access[] = $access2red;
                }
            }
        }

    } else {
        $user_cms_access = [];
    }
    $user_cms_access = array_filter($user_cms_access, function ($subArray) {
        return !empty($subArray);
    });
    // endif;

    $sql = "SELECT * FROM actions WHERE client_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $actions = $stmt->fetchAll();
    $action = [];
    foreach ($actions as $val) {
        $action[$val['action_name']] = $val;
    }

    $sql = "SELECT * FROM stages WHERE client_id = ? AND stage_state = 1 ORDER BY stage_point DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $stages = $stmt->fetchAll();
    $stage = [];
    foreach ($stages as $key => $val) {
        $stage[$val['stage_point']] = $val['stage_name'];
    }
    $db = null;


} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
?>

<?php require ('header.php'); ?>
<style>
    .btn-simple_text {
        border: none;
        color: blue;
        background-color: white;
    }
</style>

<body>
    <div class="modal fade" id="detail" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">詳細</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <!-- modal-body -->
                <div class="modal-body" id="modal_req">
                    <div class="d-flex" id="detail_btns">
                        <button type="button" class="btn btn-warning me-2" data-bs-toggle="modal"
                            data-bs-target="#detail_c" data-bs-dismiss="modal">顧客データ</button>
                        <a href="" class="btn btn-primary me-2" id="detail_preview" target="_blank">プレビュー</a>
                        <form action="back/csv_upload_history.php" method="post">
                            <input type="hidden" name="escapedData" id="escapedData">
                            <button class="btn btn-dark" <?= in_array('CSV出力', $func_limit) ? 'disabled' : ''; ?>>CSV出力</button>
                        </form>
                    </div>
                    <table class="table">
                        <tr>
                            <th>ページ数</th>
                            <th style="text-align: left;">閲覧数</th>
                            <th>閲覧時間</th>
                        </tr>
                        <tbody id="pageData"></tbody>
                    </table>
                    <br>
                    <br>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="r_detail" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">詳細</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <div>
                        <button type="button" class="btn btn-warning me-2" data-bs-toggle="modal"
                            data-bs-target="#detail_c" data-bs-dismiss="modal">顧客データ</button>
                        <a href="" class="btn btn-primary me-2" id="red_preview" target="_blank">プレビュー</a>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="searchModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 600px;">
            <div class="modal-content">
                <form action="" method="get">
                    <div class="modal-header">
                        <h5 class="modal-title">検索</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <!-- modal-body -->
                    <div class="modal-body" id="modal_req">
                        <table>
                            <tr style="width: 100%;">
                                <td>アクセス日時</td>
                                <td class="p-1">
                                    <input type="date" name="date1" value="<?php echo $date1_value; ?>">～
                                    <input type="date" name="date2" value="<?php echo $date2_value; ?>">
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td>顧客詳細</td>
                                <td class="p-1">
                                    <input class="w-100" type="text" name="search" id="search-input"
                                        value="<?= $search_value; ?>">
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td>タイトル</td>
                                <td class="p-1">
                                    <input class="w-100" type="text" name="search_title" id="search-input"
                                        value="<?= $title_value; ?>">
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td>タグ</td>
                                <td class="p-1">
                                    <?php foreach ($tag_array as $key => $each): ?>
                                        <input type="checkbox" name="tag[<?= $key; ?>]" id="" value="<?= $each; ?>"
                                            <?= (in_array($each, $selected_tags)) ? 'checked' : ''; ?>>
                                        <label for="tag[<?= $key; ?>]">
                                            <?= $each; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <tr style="width: 100%;">
                                <td>区分</td>
                                <td class="p-1">
                                    <input type="checkbox" name="segment[pdf]" id="" value="pdf"
                                        <?= isset($segment['pdf']) ? 'checked' : ''; ?>>
                                    <label for="segment[pdf]">PDFファイル</label>
                                    <input type="checkbox" name="segment[redl]" id="" value="redl"
                                        <?= isset($segment['redl']) ? 'checked' : ''; ?>>
                                    <label for="segment[redl]">
                                        リダイレク
                                    </label>
                                    <input type="checkbox" name="segment[cms]" id="" value="cms"
                                        <?= isset($segment['cms']) ? 'checked' : ''; ?>>
                                    <label for="segment[cms]">
                                        CMS
                                    </label>
                                </td>
                            </tr>
                        </table>
                        <br>
                        <br>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary">検索</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="wrapper">
        <?php require ('menu.php'); ?>
        <main>
            <div class="d-flex justify-content-between">
                <div class="header-with-help">
                    <h1>アクセス解析</h1>
                </div>
                <?php require ('dropdown.php'); ?>
            </div>
            <br>
            <table class="search-tools">
                <td><button class="btn btn-success" type="button" data-toggle="modal"
                        data-target="#searchModal">検索</button></td>

                <form method="get" action="back/access_client_list.php">
                    <input type="hidden" name="user_id"
                        value="<?= isset($_GET['user_id']) ? $_GET['user_id'] : null ?>">
                    <input type="hidden" name="filter_customers" id="filter_customers" value="">
                    <td><button class="btn btn-primary">顧客絞り込み</button></td>
                </form>

                <form id="myForm" action="back/csv_access_analysis.php" method="post">
                    <td><button type="button" class="btn btn-secondary" onclick="submitForm()" <?= in_array('CSV出力', $func_limit) ? 'disabled' : ''; ?>>CSV出力</button></td>
                </form>
            </table>
            <br>
            <table class="table table-bordered">
                <tr>
                    <th>顧客数</th>
                    <th>アクセス数</th>
                    <th>合計閲覧時間</th>
                    <th>ページ移動数の合計</th>
                    <th>CTAクリック数の合計</th>
                </tr>
                <tr>
                    <td><span id="filter-customers-count"></span></td>
                    <td><span id="total-access-count"></span></td>
                    <td><span id="total-duration"></span></td>
                    <td><span id="total-page-moved"></span></td>
                    <td><span id="total-cta-clicks"></td>
                </tr>

            </table>
            <div class="modal fade" id="detail_c" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                aria-labelledby="staticBackdropLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content" id="modal_content">
                        <div class="modal-header">
                            <h5 class="modal-title">詳細</h5>

                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                                onclick="cancelEditing()"></button>
                        </div>
                        <!-- modal-body -->
                        <form action="./back/ch_client_list.php" method="POST">
                            <div class="modal-body" id="modal_req">

                                <table class="table table-bordered">
                                    <tr>
                                        <td>顧客ID</td>
                                        <td>
                                            <input name="user_id" id="user_id" class="user_id" disabled />
                                            <input type="hidden" name="userid" id="userid" value="" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>企業名</td>
                                        <td>
                                            <input name="link_company" id="link_company" disabled />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>姓</td>
                                        <td><input name="link_surename" id="link_surename" disabled /></td>
                                    </tr>
                                    <tr>
                                        <td>名</td>
                                        <td><input name="link_name" id="link_name" disabled /></td>
                                    </tr>
                                    <tr>
                                        <td>メールアドレス</td>
                                        <td><input class="w-100" name="link_email" id="link_email" disabled /></td>
                                    </tr>
                                    <tr>
                                        <td>企業URL<a id="url_link" href="" target="_blank"><i class='fa fa-link'
                                                    style="font-size: small;"></i></a></td>
                                        <td><input name="url" id="url" disabled /></td>
                                    </tr>
                                    <tr>
                                        <td>電話番号</td>
                                        <td><input name="tel" id="tel" disabled /></td>
                                    </tr>
                                    <tr>
                                        <td>部署名</td>
                                        <td><input name="depart_name" id="depart_name" disabled /></td>
                                    </tr>
                                    <tr>
                                        <td>役職</td>
                                        <td><input name="director" id="director" disabled /></td>
                                    </tr>
                                    <tr>
                                        <td>住所</td>
                                        <td><input class="w-100" name="address" id="address" disabled /></td>
                                    </tr>
                                    <tr>
                                        <td>登録日時</td>
                                        <td>
                                            <span name="created_at" id="created_at"></span>
                                        </td>
                                    </tr>
                                </table>
                                <br>
                                <div id="detail_div"></div>
                                <br>
                            </div>
                            <div class="modal-footer d-flex justify-content-between">
                                <div id="link_data"></div>
                                <div class="d-flex">
                                    <input type="hidden" name="client_id" value="<?= $client_id ?>">
                                    <button type="button" id="edit_button" class="btn btn-dark"
                                        onclick="enableEditing()" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                                    <button type="submit" id="save_button" class="btn btn-success"
                                        style="display:none">保存</button>
                                    <button type="button" class="btn btn-secondary ms-2" onclick="cancelEditing()"
                                        data-bs-dismiss="modal">閉じる</button>
                                </div>
                            </div>
                        </form>
                        <form action="./clients_list_url.php" method="post" id="urlForm">
                            <input type="hidden" name="user_id" id="url_user_id" class="user_id">
                            <input type="hidden" name="company" id="url_company" class="company">
                            <input type="hidden" name="name" id="url_name" class="d_name">
                        </form>
                        <form action="./clients_list_template.php" method="get" id="tempForm">
                            <input type="hidden" name="user_id" id="temp_user_id" class="user_id">
                            <input type="hidden" name="company" id="temp_company" class="company">
                            <input type="hidden" name="name" id="temp_name" class="d_name">
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="tracking_detail" data-bs-backdrop="static" data-bs-keyboard="false"
                tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content" id="modal_content">
                        <div class="modal-header">
                            <h5 class="modal-title">トラッキング詳細</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <!-- modal-body -->

                        <div class="modal-body" id="modal_req">
                            <table class="table">
                                <tr>
                                    <td>ポイント:</td>
                                    <td id="tracking-point"></td>
                                </tr>
                                <tr>
                                    <td>ステージ:</td>
                                    <td id="tracking-stage"></td>
                                </tr>
                                <tr>
                                    <td>アクセス数:</td>
                                    <td id="tracking-accessNum"></td>
                                </tr>
                                <tr>
                                    <td>閲覧時間:</td>
                                    <td id="tracking-duringTime"></td>
                                </tr>
                                <tr>
                                    <td>ページ移動数:</td>
                                    <td id="tracking-pageMove"></td>
                                </tr>
                                <tr>
                                    <td>CTAクリック数:</td>
                                    <td id="tracking-ctaClick"></td>
                                </tr>
                                <tr>
                                    <td>未アクセス期間:</td>
                                    <td id="tracking-non_access"></td>
                                </tr>
                                <tr>
                                    <td>登録経路:</td>
                                    <td id="tracking-route"></td>
                                </tr>
                                <tr>
                                    <td>ユーザータグ:</td>
                                    <td id="tracking-user"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="memo_detail" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                aria-labelledby="staticBackdropLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content" id="modal_content">
                        <div class="modal-header">
                            <h5 class="modal-title">メモ</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                                onclick="cancelEditing()"></button>
                        </div>
                        <!-- modal-body -->
                        <form action="./back/ch_memo.php" method="post">
                            <div class="modal-body" id="modal_req">
                                <table class="table">
                                    <tr>
                                        <td>サービス詳細</td>
                                        <td>
                                            <textarea name="service_detail" id="service_detail" cols="30" rows="5"
                                                disabled></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>マッチングしたい人</td>
                                        <td>
                                            <textarea name="hope_matching" id="hope_matching" cols="30" rows="5"
                                                disabled></textarea>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="modal-footer">
                                <input type="hidden" name="user_id" id="memo-user_id">
                                <button type="button" id="memo_copy_button" class="btn btn-primary"
                                    onclick="copyMemo()">コピー</button>
                                <button type="button" id="memo_edit_button" class="btn btn-dark"
                                    onclick="enableEditing()" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                                <button type="submit" id="memo_save_button" class="btn btn-success"
                                    style="display:none">保存</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEditing()"
                                    data-bs-dismiss="modal">閉じる</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="business-card" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                aria-labelledby="staticBackdropLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content" id="modal_content">
                        <div class="modal-header">
                            <h5 class="modal-title">名刺</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <!-- modal-body -->

                        <div class="modal-body" id="modal_req">
                            <div class="card">
                                <div class="img">
                                    <img id="business-img">
                                </div>
                                <div class="infos">
                                    <div class="name">
                                        <h2 id="business-full-name"></h2>
                                        <h3>顧客ID:<span id="business-user-id"></span></h3>
                                    </div>
                                    <p class="text" id='business-email'>
                                        メールアドレス:<span id='business-email-span'></span>
                                    </p>
                                    <p class="text" id='business-company'>
                                        企業名:<span id='business-company-span'></span>
                                    </p>
                                    <p class="text" id='business-depart'>
                                        部署名:<span id='business-depart-span'></span>
                                    </p>
                                    <p class="text" id='business-tel'>
                                        電話番号:<span id='business-tel-span'></span>
                                    </p>
                                    <p class="text" id='business-address'>
                                        住所:<span id='business-address-span'></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="cancelEditing()"
                                data-bs-dismiss="modal">閉じる</button>
                        </div>
                    </div>
                </div>
            </div>
            <table class="table">
                <tr>
                    <th>アクセス日時</th>
                    <th>企業名</th>
                    <th>名前</th>
                    <th>区分</th>
                    <th>タイトル</th>
                    <th>閲覧時間</th>
                    <th>ページ移動数</th>
                    <th>CTAクリック数</th>
                    <th></th>
                </tr>
                <?php
                // GETメソッドでuser_idがGETされたときには、user_idでデータを絞る
                if (isset($_GET['user_id'])) {
                    $user_id = $_GET['user_id'];
                    try {
                        $db = new PDO($dsn, $user, $pass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        ]);
                        $stmt = $db->prepare("SELECT company FROM users where user_id=?");
                        $stmt->execute([$user_id]);
                        $company = $stmt->fetchColumn();

                    } catch (PDOException $e) {
                        echo '接続失敗' . $e->getMessage();
                        exit();
                    }
                    $user_pdf_access = array_filter($user_pdf_access, function ($row) use ($user_id) {
                        return $row['user_id'] === $user_id;
                    });
                }
                $results = [];

                // PDF
                $current_user_id = null;
                $company = "";
                $duration_current = 0;
                $cta_clicks_current = 0;
                $page_moved_current = 0;
                $accessed_at_current = null;
                $pdf_versions_current = null;
                $current_session_id = null;
                $current_pdf_id = null;
                foreach ($user_pdf_access as $row) {
                    $pdf_version_id = $row['pdf_version_id'];
                    $user_id = $row['user_id'];
                    $accessed_at = $row['accessed_at'];
                    $pdf_id = $row['pdf_id'];
                    $session_id = $row['session_id'];

                    try {
                        $db = new PDO($dsn, $user, $pass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        ]);
                        $stmt = $db->prepare("SELECT * FROM pdf_versions WHERE pdf_version_id=?");
                        $stmt->execute([$pdf_version_id]);
                        $pdf_versions = $stmt->fetch(PDO::FETCH_ASSOC);

                        // ポップアップIDを取得するためのクエリ
                        $popup_query = "SELECT id FROM popups WHERE pdf_id = :pdf_id AND cid = :cid";
                        $popup_stmt = $db->prepare($popup_query);
                        $popup_stmt->bindParam(':pdf_id', $pdf_versions['pdf_id'], PDO::PARAM_STR);
                        $popup_stmt->bindParam(':cid', $client_id, PDO::PARAM_STR);
                        $popup_stmt->execute();
                        $popup_ids = $popup_stmt->fetchAll(PDO::FETCH_COLUMN);

                        // ポップアップIDを使用してクリック数を取得するためのクエリ
                        if (!empty($popup_ids)) {
                            $query = "SELECT COUNT(*) FROM popup_access WHERE access_id = :access_id";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':access_id', $row['access_id'], PDO::PARAM_INT);
                            $stmt->execute();
                            $total_cta_clicks = $stmt->fetchColumn();
                        } else {
                            $total_cta_clicks = 0;
                        }
                        if (isset($_GET['popup_id'])) {
                            if ($_GET['popup_id'] == $pdf_versions['pdf_id']) {
                                $query = "SELECT COUNT(*) FROM popup_access WHERE access_id = :access_id";
                                $stmt = $db->prepare($query);
                                $stmt->bindParam(':access_id', $row['access_id'], PDO::PARAM_INT);
                                $stmt->execute();
                                $total_cta_clicks = $stmt->fetchColumn();
                            } else {
                                $total_cta_clicks = 0;
                            }
                        }
                    } catch (PDOException $e) {
                        echo '接続失敗' . $e->getMessage();
                        exit();
                    }

                    if ($current_session_id === null || $current_session_id !== $session_id || ($pdf_id !== $current_pdf_id && $current_pdf_id != null)) {
                        if ($current_session_id !== null) {
                            try {
                                $db = new PDO($dsn, $user, $pass, [
                                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                ]);
                                $stmt = $db->prepare("SELECT company FROM users where user_id=?");
                                $stmt->execute([$current_user_id]);
                                $company = $stmt->fetchColumn();

                            } catch (PDOException $e) {
                                echo '接続失敗' . $e->getMessage();
                                exit();
                            }
                            // $duration_current += $row['duration'];
                            // $cta_clicks_current += $total_cta_clicks;
                            // $page_moved_current++;
                            $results[] = [
                                'accessed_at' => $accessed_at_current,
                                'user_id' => $current_user_id,
                                'company' => $company,
                                'division' => 'PDFファイル',
                                'title' => './pdf/' . $pdf_versions_current['pdf_id'] . '_' . $pdf_versions_current['pdf_version_id'] . '.pdf',
                                'csv_title' => $pdf_versions_current['title'],
                                'duration' => $duration_current,
                                'page_moved' => $page_moved_current,
                                'cta_clicks' => $cta_clicks_current,
                                'pdf_version_id' => $pdf_versions_current['pdf_version_id'],
                                'pdf_id' => $pdf_versions_current['pdf_id']
                            ];

                            // 集計変数をリセット
                            $duration_current = 0;
                            $cta_clicks_current = 0;
                            $page_moved_current = 0;
                        }
                        $current_session_id = $session_id;  // 現在のセッションIDを更新
                        $current_user_id = $user_id;        // 現在のユーザーIDを更新
                        $accessed_at_current = $accessed_at;    // 現在のアクセス日時を更新
                        $pdf_versions_current = $pdf_versions;  // 現在のPDFバージョン情報を更新
                        $current_pdf_id = $pdf_versions_current['pdf_id'];  // 現在のセッションIDを更新
                        // 1ページ目の閲覧時間を加算
                        $duration_current = $row['duration'];
                    } else {
                        // ここで集計ロジックを続ける
                        $duration_current += $row['duration'];
                        $cta_clicks_current += $total_cta_clicks;
                        $page_moved_current++;
                    }
                }

                if (!empty($user_pdf_access) && $current_session_id !== null) {
                    try {
                        $db = new PDO($dsn, $user, $pass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        ]);
                        $stmt = $db->prepare("SELECT company FROM users where user_id=?");
                        $stmt->execute([$current_user_id]);
                        $company = $stmt->fetchColumn();

                    } catch (PDOException $e) {
                        echo '接続失敗' . $e->getMessage();
                        exit();
                    }

                    $results[] = [
                        'accessed_at' => $accessed_at_current,
                        'user_id' => $current_user_id,
                        'company' => $company,
                        'division' => 'PDFファイル',
                        'title' => './pdf/' . $pdf_versions_current['pdf_id'] . '_' . $pdf_versions_current['pdf_version_id'] . '.pdf',
                        'csv_title' => $pdf_versions_current['title'],
                        'duration' => $duration_current,
                        'page_moved' => $page_moved_current,
                        'cta_clicks' => $cta_clicks_current,
                        'pdf_version_id' => $pdf_versions_current['pdf_version_id'],
                        'pdf_id' => $pdf_versions_current['pdf_id']
                    ];
                }
                //リダイレクト
                foreach ($user_redirects_access as $row) {
                    $results[] = [
                        'accessed_at' => $row['accessed_at'],
                        'user_id' => $row['user_id'],
                        'company' => $row['company'],
                        'division' => 'リダイレクト',
                        'title' => $row['url'],
                        'csv_title' => $row['title'],
                        'duration' => null,
                        'page_moved' => null,
                        'cta_clicks' => null,
                        'pdf_version_id' => '',
                        'pdf_id' => ''
                    ];
                }

                foreach ($user_cms_access as $row) {
                    $results[] = [
                        'accessed_at' => $row['created_at'],
                        'user_id' => $row['user_id'],
                        'company' => $row['company'],
                        'division' => 'CMS',
                        'title' => './cms_view.php?t=' . $row['cms_id'],
                        'csv_title' => $row['title'],
                        'duration' => null,
                        'page_moved' => null,
                        'cta_clicks' => null,
                        'pdf_version_id' => '',
                        'pdf_id' => ''
                    ];
                }

                usort($results, function ($a, $b) {
                    return strtotime($b['accessed_at']) - strtotime($a['accessed_at']);
                });
                $tracking = [];
                $filter_customers = [];
                $final_access = [];
                foreach ($results as $k => $r) {
                    if (!in_array($r['user_id'], $filter_customers)) {
                        $filter_customers[] = $r['user_id'];
                        $final_access[$r['user_id']] = $r['accessed_at'];
                    }
                    if (isset($tracking[$r['user_id']])) {
                        $tracking[$r['user_id']]['access_num']++;
                        if ($r['duration'] !== null) {
                            $tracking[$r['user_id']]['duration'] += $r['duration'];
                        }
                        if ($r['page_moved'] !== null) {
                            $tracking[$r['user_id']]['page_move'] += $r['page_moved'];
                        }
                        if ($r['cta_clicks'] !== null) {
                            $tracking[$r['user_id']]['cta_clicks'] += $r['cta_clicks'];
                        }
                    } else {
                        $tracking[$r['user_id']]['access_num'] = 1;
                        if ($r['duration'] !== null) {
                            $tracking[$r['user_id']]['duration'] = $r['duration'];
                        } else {
                            $tracking[$r['user_id']]['duration'] = 0;
                        }
                        if ($r['page_moved'] !== null) {
                            $tracking[$r['user_id']]['page_move'] = $r['page_moved'];
                        } else {
                            $tracking[$r['user_id']]['page_move'] = 0;
                        }
                        if ($r['cta_clicks'] !== null) {
                            $tracking[$r['user_id']]['cta_clicks'] = $r['cta_clicks'];
                        } else {
                            $tracking[$r['user_id']]['cta_clicks'] = 0;
                        }
                    }
                    if (isset($_GET['popup_id'])) {
                        if ($r['cta_clicks'] < 1 || $r['cta_clicks'] == NULL) {
                            unset($results[$k]);
                        }
                    }
                }
                // 1ページあたりに表示するアイテム数
                $items_per_page = 10;

                // ページ数を計算
                $total_items = count($results);
                $total_pages = ceil($total_items / $items_per_page);

                // 現在のページ数を取得
                $current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;

                // 表示するアイテムを決定
                $start_index = ($current_page - 1) * $items_per_page;
                $end_index = min($start_index + $items_per_page, $total_items);
                $displayed_items = array_slice($results, $start_index, $end_index - $start_index);

                // ページネーションのリンクを作成
                $pagination_links = '';
                $maxVisable = 5;
                $start_page = (ceil($current_page / $maxVisable) - 1) * $maxVisable + 1;
                $end_page = min($start_page + $maxVisable - 1, $total_pages);
                $next = $end_page < $total_pages ? $end_page + 1 : $total_pages;
                $prev = $current_page <= 5 ? max($current_page - 1, 1) : $start_page - 5;
                $prev_disabled = $current_page == 1 ? "page-link inactive-link" : "page-link";
                $next_disabled = ($current_page == $total_pages || $total_pages == 0) ? "page-link inactive-link" : "page-link";

                $pagination_links .= '<li class="page-item"><a class="' . $prev_disabled . '" href="?' . $search_params . '&page=' . $prev . '">«前</a></li>';
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $active_class = ($i == $current_page) ? 'active' : '';
                    $pagination_links .= '<li class="page-item ' . $active_class . '"><a class="page-link" href="?' . $search_params . '&page=' . $i . '">' . $i . '</a></li>';
                }
                $pagination_links .= '<li class="page-item"><a class="' . $next_disabled . '" href="?' . $search_params . '&page=' . $next . '"   >次»</a></li>';
                ?>

                <!-- ページネーションを表示 -->
                <ul class="pagination">
                    <?= $pagination_links ?>
                </ul>

                <?php
                $sql = "SELECT COUNT(*) FROM g_users WHERE client_id=?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$client_id]);
                $link_data = $stmt->fetchColumn();

                // Output the results
                foreach ($displayed_items as $row) {
                    if ($row['division'] == 'PDFファイル') {
                        $row_pdf_version_id = $row['pdf_version_id'];
                        $pdf_id = $row['pdf_id'];
                        $directory = "./pdf_img";
                        $images = glob($directory . "/" . $pdf_id . "_" . $row_pdf_version_id . "*.jpg");
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

                        $accessed_at = new DateTime($row["accessed_at"]);

                        $end_time = clone $accessed_at;
                        $end_time->add(new DateInterval('PT' . $row["duration"] . 'S'));

                        $sql = 'SELECT `pdf_version_id`, `page`, COUNT(`access_id`) AS views, SUM(`duration`) AS total_duration 
                                    FROM `user_pdf_access` 
                                    WHERE `user_id` = :user_id 
                                    AND `pdf_version_id` = :pdf_version_id 
                                    AND `accessed_at` BETWEEN :start_time AND :end_time 
                                    GROUP BY `page`, `pdf_version_id` 
                                    ORDER BY `page`;';

                        // print_r($duration_current->format('Y-m-d H:i:s'));
                        // return;
                        $stmt = $db->prepare($sql);

                        $stmt->bindValue(':user_id', $row["user_id"], PDO::PARAM_STR);
                        $stmt->bindValue(':pdf_version_id', $row_pdf_version_id, PDO::PARAM_INT);
                        $stmt->bindValue(':start_time', $accessed_at->format('Y-m-d H:i:s'), PDO::PARAM_STR);
                        $stmt->bindValue(':end_time', $end_time->format('Y-m-d H:i:s'), PDO::PARAM_STR);

                        $stmt->execute();

                        $result = $stmt->fetchAll();
                        $filteredResult = array_filter($result, function ($data) use ($row_pdf_version_id) {
                            return $data['pdf_version_id'] == $row_pdf_version_id;
                        });
                    }
                    echo '<tr>';
                    echo '<td>' . substr($row['accessed_at'], 0, 16) . '</td>';
                    $stmt = $db->prepare("SELECT * FROM users WHERE user_id=?");
                    $stmt->execute([$row['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    $point = 0;
                    if (isset($action['アクセス数']) && $action['アクセス数']['action_state'] == 1) {
                        $point += (int) ($tracking[$row['user_id']]['access_num'] / $action['アクセス数']['action_value']) * $action['アクセス数']['action_point'];
                    }
                    if (isset($action['閲覧時間']) && $action['閲覧時間']['action_state'] == 1) {
                        $point += (int) ($tracking[$row['user_id']]['duration'] / $action['閲覧時間']['action_value']) * $action['閲覧時間']['action_point'];
                    }
                    if (isset($action['ページ移動数']) && $action['ページ移動数']['action_state'] == 1) {
                        $point += (int) ($tracking[$row['user_id']]['page_move'] / $action['ページ移動数']['action_value']) * $action['ページ移動数']['action_point'];
                    }
                    if (isset($action['CTAクリック数']) && $action['CTAクリック数']['action_state'] == 1) {
                        $point += (int) ($tracking[$row['user_id']]['cta_clicks'] / $action['CTAクリック数']['action_value']) * $action['CTAクリック数']['action_point'];
                    }
                    $today = new DateTime();
                    $interval = $today->diff(new DateTime($final_access[$row['user_id']]));
                    $final_access_period = $interval->days;
                    if (isset($action['未アクセス期間']) && $action['未アクセス期間']['action_state'] == 1) {
                        $point += (int) ($final_access_period / $action['未アクセス期間']['action_value']) * $action['未アクセス期間']['action_point'];
                    }
                    if ($point < 0) {
                        $point = 0;
                    }
                    $getStage = '';

                    if (!empty($stage)) {
                        foreach ($stage as $key => $val) {
                            if ($point >= $key) {
                                $getStage = $val;
                                break;
                            }
                        }
                    }
                    echo '<td>' . $user['company'] . '</td>';
                    echo '<td>' . $user['surename'] . $user['name'] . '</td>';
                    echo '<td>' . $row['division'] . '</td>';
                    echo '<td>' . $row['csv_title'] . '</td>';
                    if ($row['duration'] !== null) {
                        echo '<td>' . secondsToTime($row['duration']) . '</td>';
                    } else {
                        echo '<td>-</td>';
                    }
                    if ($row['page_moved'] !== null) {
                        echo '<td>' . $row['page_moved'] . '</td>';
                    } else {
                        echo '<td>-</td>';
                    }
                    if ($row['cta_clicks'] !== null) {
                        echo '<td>' . $row['cta_clicks'] . '</td>';
                    } else {
                        echo '<td>-</td>';
                    }
                    if ($row['division'] == 'PDFファイル') {
                        echo '<td>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#detail"
                                onclick="_c(`' . htmlspecialchars(json_encode($filteredResult)) . '`,`' . $page_count . '`,`' . $row['user_id'] . '`,`' . $row['company'] . '`,`' . $user['surename'] . '`,`' . $user['name'] . '`,`' . $user['email'] . '`,`' . $user['url'] . '`,`' . $user['tel'] . '`,`' . $user['depart_name'] . '`,`' . $user['director'] . '`,`' . $user['address'] . '`,`' . $user['hope_matching'] . '`,`' . $user['service_detail'] . '`,`' . $link_data . '`,`' . $point . '`,`' . $getStage . '`,`' . $tracking[$user['user_id']]['access_num'] . '`,`' . secondsToTime($tracking[$user['user_id']]['duration']) . '`,`' . $tracking[$user['user_id']]['page_move'] . '`,`' . $tracking[$user['user_id']]['cta_clicks'] . '`,`' . $final_access_period . '`,`' . $user['profile_image'] . '`,`' . substr($user['created_at'], 0, 16) . '`,`' . $row['title'] . '`,`' . $user['route'] . '`,`' . $cusers[$user['cuser_id']] . '`)" data-bs-dismiss="modal">詳細</button>
                        </td>';
                    } else {
                        echo '<td>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#r_detail"
                                onclick="_r(`' . $row['user_id'] . '`,`' . $row['company'] . '`,`' . $user['surename'] . '`,`' . $user['name'] . '`,`' . $user['email'] . '`,`' . $user['url'] . '`,`' . $user['tel'] . '`,`' . $user['depart_name'] . '`,`' . $user['director'] . '`,`' . $user['address'] . '`,`' . $user['hope_matching'] . '`,`' . $user['service_detail'] . '`,`' . $link_data . '`,`' . $point . '`,`' . $getStage . '`,`' . $tracking[$user['user_id']]['access_num'] . '`,`' . secondsToTime($tracking[$user['user_id']]['duration']) . '`,`' . $tracking[$user['user_id']]['page_move'] . '`,`' . $tracking[$user['user_id']]['cta_clicks'] . '`,`' . $final_access_period . '`,`' . $user['profile_image'] . '`,`' . substr($user['created_at'], 0, 16) . '`,`' . $row['title'] . '`,`' . $user['route'] . '`,`' . $cusers[$user['cuser_id']] . '`)" data-bs-dismiss="modal">詳細</button>
                        </td>';
                    }

                    echo '</tr>';

                }
                ?>
            </table>

            <?php
            // 合計アクセス数を計算する
            $totalAccessCount = count($results);

            // 合計閲覧時間を計算する
            $totalDuration = array_reduce($results, function ($carry, $item) {
                return $carry + ($item['duration'] !== null ? $item['duration'] : 0);
            }, 0);

            // ページ移動数の合計を計算する
            $totalPageMoved = array_reduce($results, function ($carry, $item) {
                return $carry + ($item['page_moved'] !== null ? $item['page_moved'] : 0);
            }, 0);

            // CTAクリック数の合計を計算する
            $totalCtaClicks = array_reduce($results, function ($carry, $item) {
                return $carry + ($item['cta_clicks'] !== null ? $item['cta_clicks'] : 0);
            }, 0);

            // 閲覧時間の秒数を時間:分:秒の形式に変換する
            foreach ($results as $r) {
                $r['duration'] = secondsToTime($r['duration']);
            }
            ?>

            <script>
                function secondsToTime(seconds) {
                    var hours = Math.floor(seconds / 3600);
                    var minutes = Math.floor((seconds / 60) % 60);
                    var seconds = seconds % 60;

                    return ('0' + hours).slice(-2) + ':' + ('0' + minutes).slice(-2) + ':' + ('0' + seconds).slice(-2);
                }

                // 合計値をJavaScriptに渡す
                var filterCustomersCount = <?= count($filter_customers) ?>;
                var totalAccessCount = <?= $totalAccessCount ?>;
                var totalDuration = <?php echo $totalDuration; ?>;
                var totalPageMoved = <?php echo $totalPageMoved; ?>;
                var totalCtaClicks = <?php echo $totalCtaClicks; ?>;
                var filter_customers = "<?= http_build_query($filter_customers); ?>"

                // HTMLに表示する
                document.getElementById('filter-customers-count').innerHTML = filterCustomersCount;
                document.getElementById('total-access-count').innerHTML = totalAccessCount;
                document.getElementById('total-duration').innerHTML = secondsToTime(totalDuration);
                document.getElementById('total-page-moved').innerHTML = totalPageMoved;
                document.getElementById('total-cta-clicks').innerHTML = totalCtaClicks;
                document.getElementById('filter_customers').value = filter_customers;
            </script>
        </main>
    </div>

    <script>
        const inputs = document.querySelectorAll('#modal_req input, #modal_req textarea, #modal_req select');
        const editButton = document.getElementById('edit_button');
        const saveButton = document.getElementById('save_button');
        const closeBtn = document.getElementById('close');

        const enableEditing = () => {
            inputs.forEach(input => input.disabled = false);
            editButton.style.display = 'none';
            saveButton.style.display = 'inline-block';
        }

        const cancelEditing = () => {
            inputs.forEach(input => input.disabled = true);
            saveButton.style.display = "none";
            editButton.style.display = "block";
        }

        function trackingHandle(point, stage, accessNum, duringTime, pageMove, ctaClick, non_access) {
            document.getElementById("tracking-point").innerText = point;
            document.getElementById("tracking-stage").innerText = stage;
            document.getElementById("tracking-accessNum").innerText = accessNum;
            document.getElementById("tracking-duringTime").innerText = duringTime;
            document.getElementById("tracking-pageMove").innerText = pageMove;
            document.getElementById("tracking-ctaClick").innerText = ctaClick;
            document.getElementById("tracking-non_access").innerText = non_access;
        }
        function memoHandle(user_id, service_detail, hope_matching) {
            document.getElementById("memo-user_id").value = user_id;
            document.getElementById("service_detail").value = service_detail;
            document.getElementById("hope_matching").value = hope_matching;
        }
        async function copyMemo() {
            var serviceDetail = $("#service_detail").val();
            var hopeMatching = $("#hope_matching").val();

            var combinedText = "【サービス詳細】\n" + serviceDetail + "\n【マッチングしたい人】\n" + hopeMatching;
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(combinedText);
                copy_alert();
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = combinedText;
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

        function stringToNum(value) {
            if (value == null) {
                return 0;
            } else {
                return parseInt(value);
            }
        }

        function convertSecondsToHMS(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const remainingSeconds = seconds % 60;
            return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
        }

        function _c(escapedData, pageCount, d_user_id, d_company, d_surename, d_name, d_email, d_url, d_tel, d_depart_name, d_director, d_address, d_hope_matching, d_service_detail, d_link_data, d_point, d_getStage, d_totalAccessCount, d_totalDuration, d_total_page_moved, d_totalCtaClicks, d_non_access, d_profile_image, d_created_at, url, route, cuser) {
            document.getElementById('detail_preview').setAttribute('href', url);
            let data = JSON.parse(escapedData);
            console.log(data);

            let tbody = document.getElementById("pageData");
            let link_div = document.getElementById("link_data");
            let detail_div = document.getElementById("detail_div");
            tbody.innerHTML = "";

            let maxPageNumber = parseInt(pageCount);
            let totalViews = 0;
            let totalDuration = 0;



            for (let i = 0; i < maxPageNumber; i++) {
                let tr = document.createElement("tr");
                let td1 = document.createElement("td");
                let td2 = document.createElement("td");
                let td3 = document.createElement("td");

                td1.innerText = i + 1;
                td2.innerText = data[i] ? parseInt(data[i]["views"]) : 0;
                td3.innerText = data[i] ? convertSecondsToHMS(stringToNum(data[i]["total_duration"])) : convertSecondsToHMS(
                    0);

                if (data[i]) {
                    totalViews += parseInt(data[i]["views"]);
                    totalDuration += data[i]["total_duration"];
                }

                tr.appendChild(td1);
                tr.appendChild(td2);
                tr.appendChild(td3);

                tbody.appendChild(tr);
            }


            // document.getElementById("user_id").value = d_user_id;
            $('.user_id').val(d_user_id);
            $('.company').val(d_company);
            $('.d_name').val(d_surename + d_name);
            document.getElementById("userid").value = d_user_id;
            document.getElementById("link_company").value = d_company;
            document.getElementById("link_surename").value = d_surename;
            document.getElementById("link_name").value = d_name;
            document.getElementById("link_email").value = d_email;
            document.getElementById("url").value = d_url;
            document.getElementById("tel").value = d_tel;
            document.getElementById("depart_name").value = d_depart_name;
            document.getElementById("director").value = d_director;
            document.getElementById("address").value = d_address;
            document.getElementById("created_at").innerText = d_created_at
            document.getElementById("tracking-route").innerText = route
            document.getElementById("tracking-user").innerText = cuser
            document.getElementById('url_link').setAttribute('href', d_url);

            // if (!d_url.startsWith("http") && !d_url.startsWith("https")) {
            //     d_url = "https://" + d_url;
            // }

            link_div.innerHTML = '<button type="button" id="edit_button" class="btn btn-danger me-2" onclick="deleteUser(`' + d_user_id + '`)" <?= in_array('削除', $func_limit) ? 'disabled' : ''; ?>>削除</button>'
            if (link_data > 0 && email != "" && profile_image != "") {
                link_div.innerHTML += '<button type="button" data-bs-toggle="modal"  data-bs-target="#business-card" onclick="businessCardHandle(`' + d_email + '`,`' + d_surename + '`,`' + d_name + '`,`' + d_company + '`,`' + d_user_id + '`,`' + d_tel + '`,`' + d_depart_name + '`,`' + d_address + '`,`' + d_profile_image + '`)" data-bs-dismiss="modal" class="btn btn-primary">名刺</button>';
            } else {
                link_div.innerHTML += '<button id="contacts" class="btn btn-secondary" disabled>連携なし</button>';
            }
            detail_div.innerHTML = '<button type="button" data-bs-toggle="modal"  data-bs-target="#tracking_detail" onclick="trackingHandle(`' + d_point + '`,`' + d_getStage + '`,`' + d_totalAccessCount + '`,`' + d_totalDuration + '`,`' + d_total_page_moved + '`,`' + d_totalCtaClicks + '`,`' + d_non_access + '`)" data-bs-dismiss="modal" class="btn btn-success me-2">トラッキング詳細</button>';
            detail_div.innerHTML += '<button type="button" onclick="tempFormSubmit()" class="btn btn-primary me-2">テンプレート</button>';
            detail_div.innerHTML += '<button type="button" onclick="urlFormSubmit()" class="btn btn-primary me-2">URL</button>';
            detail_div.innerHTML += '<button type="button" data-bs-toggle="modal"  data-bs-target="#memo_detail" onclick="memoHandle(`' + d_user_id + '`,`' + d_service_detail + '`,`' + d_hope_matching + '`)" data-bs-dismiss="modal" class="btn btn-warning">メモ</button>';

            document.getElementById("escapedData").value = escapedData;
        }
        function _r(d_user_id, d_company, d_surename, d_name, d_email, d_url, d_tel, d_depart_name, d_director, d_address, d_hope_matching, d_service_detail, d_link_data, d_point, d_getStage, d_totalAccessCount, d_totalDuration, d_total_page_moved, d_totalCtaClicks, d_non_access, d_profile_image, d_created_at, url, route, cuser) {
            document.getElementById('red_preview').setAttribute('href', url);

            let tbody = document.getElementById("pageData");
            let link_div = document.getElementById("link_data");
            let detail_div = document.getElementById("detail_div");
            tbody.innerHTML = "";

            // document.getElementById("user_id").value = d_user_id;
            $('.user_id').val(d_user_id);
            $('.company').val(d_company);
            $('.d_name').val(d_surename + d_name);
            document.getElementById("userid").value = d_user_id;
            document.getElementById("link_company").value = d_company;
            document.getElementById("link_surename").value = d_surename;
            document.getElementById("link_name").value = d_name;
            document.getElementById("link_email").value = d_email;
            document.getElementById("url").value = d_url;
            document.getElementById('url_link').setAttribute('href', d_url);
            // document.getElementById("url_link").href = d_url;
            document.getElementById("tel").value = d_tel;
            document.getElementById("depart_name").value = d_depart_name;
            document.getElementById("director").value = d_director;
            document.getElementById("address").value = d_address;
            document.getElementById("created_at").innerText = d_created_at
            document.getElementById("tracking-route").innerText = route
            document.getElementById("tracking-user").innerText = cuser

            link_div.innerHTML = '<button type="button" id="edit_button" class="btn btn-danger me-2" onclick="deleteUser(`' + d_user_id + '`)" <?= in_array('削除', $func_limit) ? 'disabled' : ''; ?>>削除</button>'
            if (link_data > 0 && email != "" && profile_image != "") {
                link_div.innerHTML += '<button type="button" data-bs-toggle="modal"  data-bs-target="#business-card" onclick="businessCardHandle(`' + d_email + '`,`' + d_surename + '`,`' + d_name + '`,`' + d_company + '`,`' + d_user_id + '`,`' + d_tel + '`,`' + d_depart_name + '`,`' + d_address + '`,`' + d_profile_image + '`)" data-bs-dismiss="modal" class="btn btn-primary">名刺</button>';
            } else {
                link_div.innerHTML += '<button id="contacts" class="btn btn-secondary" disabled>連携なし</button>';
            }
            detail_div.innerHTML = '<button type="button" data-bs-toggle="modal"  data-bs-target="#tracking_detail" onclick="trackingHandle(`' + d_point + '`,`' + d_getStage + '`,`' + d_totalAccessCount + '`,`' + d_totalDuration + '`,`' + d_total_page_moved + '`,`' + d_totalCtaClicks + '`,`' + d_non_access + '`)" data-bs-dismiss="modal" class="btn btn-success me-2">トラッキング詳細</button>';
            detail_div.innerHTML += '<button type="button" onclick="tempFormSubmit()" class="btn btn-primary me-2">テンプレート</button>';
            detail_div.innerHTML += '<button type="button" onclick="urlFormSubmit()" class="btn btn-primary me-2">URL</button>';
            detail_div.innerHTML += '<button type="button" data-bs-toggle="modal"  data-bs-target="#memo_detail" onclick="memoHandle(`' + d_user_id + '`,`' + d_service_detail + '`,`' + d_hope_matching + '`)" data-bs-dismiss="modal" class="btn btn-warning">メモ</button>';
        }

        function urlFormSubmit() {
            document.getElementById("urlForm").submit();
        }

        function tempFormSubmit() {
            document.getElementById("tempForm").submit();
        }

        function submitForm() {
            // JSON形式で取得
            var results = <?= json_encode($results) ?>;
            // hidden inputを作成してフォームに追加
            var input = document.createElement("input");
            input.setAttribute("type", "hidden");
            input.setAttribute("name", "csv_data");
            input.setAttribute("value", JSON.stringify(results));
            document.getElementById("myForm").appendChild(input);
            // フォームを送信
            document.getElementById("myForm").submit();
        }
        function deleteUser(id) {
            var confirmed = confirm("顧客データを本当に削除しますか？");
            if (confirmed) {
                var cid = "<?= $client_id; ?>";
                location.href = "./back/del_user.php?id=" + id + "&cid=" + cid;
            }
        }

        const businessCardHandle = (email, surename, name, company, user_id, tel, depart_name, address, profile_image) => {

            const fullName = surename + " " + name;
            document.getElementById('business-img').setAttribute('src', profile_image);
            document.getElementById('business-full-name').innerText = fullName;
            document.getElementById('business-user-id').innerText = user_id;
            if (company !== "") {
                document.getElementById('business-company').style.display = "block";
                document.getElementById('business-company-span').innerText = company;
            } else {
                document.getElementById('business-company').style.display = "none";
            }
            if (email == "") {
                document.getElementById('business-email').style.display = "none";
            } else {
                document.getElementById('business-email').style.display = "block";
                document.getElementById('business-email-span').innerText = email;
            }

            if (tel == "") {
                document.getElementById('business-tel').style.display = "none";
            } else {
                document.getElementById('business-tel').style.display = "block";
                document.getElementById('business-tel-span').innerText = tel;
            }

            if (depart_name == "") {
                document.getElementById('business-depart').style.display = "none";
            } else {
                document.getElementById('business-depart').style.display = "block";
                document.getElementById('business-depart-span').innerText = depart_name;
            }
            if (address == "") {
                document.getElementById('business-address').style.display = "none";
            } else {
                document.getElementById('business-address').style.display = "block";
                document.getElementById('business-address-span').innerText = address;
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