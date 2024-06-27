<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (isset($_SESSION['csv_data']))
    unset($_SESSION["csv_data"]);
if (isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);
if (isset($_SESSION['email_data']))
    unset($_SESSION["email_data"]);
require ('common.php');

$limit = 10;
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

$date1_value = !empty($_GET['date1']) ? $_GET['date1'] : '';
$date2_value = !empty($_GET['date2']) ? $_GET['date2'] : '';
$custom_info = isset($_GET['custom_info']) ? $_GET['custom_info'] : "";
$from_point = isset($_GET['from_point']) ? $_GET['from_point'] : 0;
$end_point = isset($_GET['end_point']) ? $_GET['end_point'] : 1000;
$regi_route = isset($_GET['regi_route']) ? $_GET['regi_route'] : [];
$selected_stage = isset($_GET['selected_stage']) ? $_GET['selected_stage'] : [];
$selected_tags = isset($_GET['tag']) ? $_GET['tag'] : [];
$selected_c_users = isset($_GET['c_user']) ? $_GET['c_user'] : [];
$service_detail = isset($_GET['service_detail']) ? $_GET['service_detail'] : "";
$hope_matching = isset($_GET['hope_matching']) ? $_GET['hope_matching'] : "";

$search_params = '';
if (isset($_GET['custom_info'])) {
    $search_params .= "custom_info=" . $custom_info;
}
if (!empty($_GET['date1'])) {
    $search_params .= "&date1=" . $date1_value;
}
if (!empty($_GET['date2'])) {
    $search_params .= "&date2=" . $date2_value;
}
if (isset($_GET['from_point'])) {
    $search_params .= "&from_point=" . $from_point;
}
if (isset($_GET['end_point'])) {
    $search_params .= "&end_point=" . $end_point;
}
if (isset($_GET['service_detail'])) {
    $search_params .= "&service_detail=" . $service_detail;
}
if (isset($_GET['hope_matching'])) {
    $search_params .= "&hope_matching=" . $hope_matching;
}
if (isset($_GET['users_id'])) {
    $search_params .= "&users_id=" . $_GET['users_id'];
}

$href['regi_route'] = $regi_route;
if (isset($_GET['regi_route'])) {
    $search_params .= "&" . http_build_query($href);
}
$href['selected_stage'] = $selected_stage;
if (isset($_GET['selected_stage'])) {
    $search_params .= "&" . http_build_query($href);
}
$href['tag'] = $selected_tags;
if (isset($_GET['tag'])) {
    $search_params .= "&" . http_build_query($href);
}
$href['c_user'] = $selected_c_users;
if (isset($_GET['c_user'])) {
    $search_params .= "&" . http_build_query($href);
}

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    //タグ
    $tag2customer = [];
    $tag_all_array = [];
    $stmt = $db->prepare("SELECT * FROM tag_sample WHERE client_id=?");
    $stmt->execute([$client_id]);
    $tag_data = $stmt->fetchAll();
    foreach ($tag_data as $t) {
        $tag_all_array[] = $t['tag_name'];
    }

    if ($ClientUser['role'] == 1) {
        $tag_array = $tag_all_array;
    } else {
        $tag_array = explode(',', $ClientUser['tags']);
    }
    foreach ($tag_all_array as $t) {
        $tag2customer[$t] = 0;
    }

    $stmt = $db->prepare("SELECT * FROM clients WHERE cid=?");
    $stmt->execute([$client_id]);
    $clients = $stmt->fetchAll();
    foreach ($clients as $cu) {
        $client_users[$cu['id']] = $cu;
    }
    //Send Email
    if (is_null($ClientUser['send_email'])) {
        $stmt = $db->prepare("SELECT * FROM froms WHERE email=?");
        $stmt->execute([$ClientUser['email']]);
        $from = $stmt->fetch(PDO::FETCH_ASSOC);
        $send_address = $from['id'] ?? '';
    } else {
        $send_address = $ClientUser['send_email'];
    }
    //Google User
    $stmt = $db->prepare("SELECT * FROM g_users WHERE client_id=:client_id AND status=:status");
    $stmt->execute([':client_id' => $client_id, ':status' => 1]);
    $g_users_data = $stmt->fetch(PDO::FETCH_ASSOC);
    //Business Template
    $stmt = $db->prepare("SELECT * FROM biz_template_setting WHERE cid=?");
    $stmt->execute([$client_id]);
    $biz_template_setting_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($biz_template_setting_data) {
        $b_template_id = $biz_template_setting_data['template_id'];
        $b_sending_time = $biz_template_setting_data['sending_time'];
    } else {
        $b_template_id = null;
        $b_sending_time = null;
    }
    //Business Schedule
    $stmt = $db->prepare("SELECT * FROM business_schedules WHERE cid=?");
    $stmt->execute([$client_id]);
    $scheduled_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $scheduled_num = count($scheduled_data);
    $scheduled_user_id = "";
    if ($scheduled_num > 0) {
        foreach ($scheduled_data as $data) {
            $to_data = json_decode($data['to_data'], true);
            foreach ($to_data as $row) {
                $scheduled_user_id .= ($scheduled_user_id == "") ? $row['user_id'] : "," . $row['user_id'];
            }
        }
    }
    //顧客一覧取得
    if ((isset($_POST['users_id']) && !empty($_POST['users_id'])) || (isset($_GET['users_id']) && !empty($_GET['users_id']))) {
        //ユーザーがすでに決まっている場合
        if (isset($_POST['users_id'])) {
            $string_id = '';
            foreach ($_POST['users_id'] as $chunk_id) {
                $string_id .= $chunk_id;
            }
        } else {
            $string_id = $_GET['users_id'];
        }

        $array_user = explode(",", $string_id);
        $symbols = implode(',', array_fill(0, count($array_user), '?'));

        $sql = "SELECT * FROM users WHERE cid = ? AND user_id IN($symbols)";
        $unique_sql = "";
        if ($custom_info != "") {
            $unique_sql .= " AND (user_id LIKE '%" . $_GET['custom_info'] .
                "%' OR company LIKE '%" . $_GET['custom_info'] .
                "%' OR surename LIKE '%" . $_GET['custom_info'] .
                "%' OR name LIKE '%" . $_GET['custom_info'] .
                "%' OR email LIKE '%" . $_GET['custom_info'] .
                "%' OR url LIKE '%" . $_GET['custom_info'] .
                "%' OR tel LIKE '%" . $_GET['custom_info'] .
                "%' OR address LIKE '%" . $_GET['custom_info'] .
                "%' OR depart_name LIKE '%" . $_GET['custom_info'] .
                "%' OR director LIKE '%" . $_GET['custom_info'] .
                "%')";
        }
        if (!empty($_GET['date1']) && !empty($_GET['date2'])) {
            $unique_sql .= " AND created_at BETWEEN '$date1_value' AND DATE_ADD('$date2_value', INTERVAL 1 DAY)";
        } else if (!empty($_GET['date1'])) {
            $unique_sql .= " AND created_at >= '$date1_value' ";
        } else if (!empty($_GET['date2'])) {
            $unique_sql .= " AND created_at <= '$date2_value' ";
        }
        if ($service_detail != "") {
            $unique_sql .= " AND (service_detail LIKE '%" . $service_detail . "%')";
        }
        if ($hope_matching != "") {
            $unique_sql .= " AND (hope_matching LIKE '%" . $hope_matching . "%')";
        }
        $sql .= $unique_sql . " ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$client_id, ...$array_user]);
        $csv_out_data = $stmt->fetchAll();
    } else {
        //ユーザー選択がない場合
        $csv_sql = "SELECT * FROM users WHERE cid=?";
        $unique_sql = "";

        if (!empty($selected_c_users)) {
            $symbols = implode("','", $selected_c_users);
            $unique_sql .= " AND cuser_id IN ('$symbols')";
        }
        if (!empty($_GET['custom_info'])) {
            $unique_sql .= " AND (user_id LIKE '%" . $_GET['custom_info'] .
                "%' OR company LIKE '%" . $_GET['custom_info'] .
                "%' OR surename LIKE '%" . $_GET['custom_info'] .
                "%' OR name LIKE '%" . $_GET['custom_info'] .
                "%' OR email LIKE '%" . $_GET['custom_info'] .
                "%' OR url LIKE '%" . $_GET['custom_info'] .
                "%' OR tel LIKE '%" . $_GET['custom_info'] .
                "%' OR address LIKE '%" . $_GET['custom_info'] .
                "%' OR depart_name LIKE '%" . $_GET['custom_info'] .
                "%' OR director LIKE '%" . $_GET['custom_info'] .
                "%')";
        }
        if (!empty($_GET['date1']) && !empty($_GET['date2'])) {
            $unique_sql .= " AND created_at BETWEEN '$date1_value' AND DATE_ADD('$date2_value', INTERVAL 1 DAY)";
        } else if (!empty($_GET['date1'])) {
            $unique_sql .= " AND created_at >= '$date1_value' ";
        } else if (!empty($_GET['date2'])) {
            $unique_sql .= " AND created_at <= '$date2_value' ";
        }
        if ($service_detail != "") {
            $unique_sql .= " AND (service_detail LIKE '%" . $service_detail . "%')";
        }
        if ($hope_matching != "") {
            $unique_sql .= " AND (hope_matching LIKE '%" . $hope_matching . "%')";
        }
        $csv_sql .= $unique_sql . " ORDER BY created_at DESC";

        $stmt = $db->prepare($csv_sql);
        $stmt->execute([$client_id]);
        $csv_out_data = $stmt->fetchAll();
    }
    //Link Data
    $sql = "SELECT COUNT(*) FROM g_users WHERE client_id=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $link_data = $stmt->fetchColumn();
    //アクション    
    $action = [];
    $sql = "SELECT * FROM actions WHERE client_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $actions = $stmt->fetchAll();

    foreach ($actions as $val) {
        $action[$val['action_name']] = $val;
    }
    //ステージ
    $stage = [];
    $stage_range = [];
    $stage2customer = [];
    $overlapping_points = [];
    $sql = "SELECT * FROM stages WHERE client_id = ? AND stage_state = 1 ORDER BY stage_point DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $stages = $stmt->fetchAll();

    foreach ($stages as $key => $val) {
        if (in_array($val['stage_name'], $selected_stage)) {
            $stage_range[$val['stage_name']]['start'] = $val['stage_point'];
            $stage_range[$val['stage_name']]['end'] = isset($stages[$key - 1]) ? $stages[$key - 1]['stage_point'] : 1000;
        }
        $stage2customer[$val['stage_name']] = 0;
        $stage[$val['stage_point']] = $val['stage_name'];
    }
    //ポイント
    if (empty($selected_stage)) {
        for ($i = $from_point; $i <= $end_point; $i++) {
            $overlapping_points[] = $i;
        }
    } else {
        foreach ($selected_stage as $stage_name) {
            $start = $stage_range[$stage_name]['start'];
            $end = $stage_range[$stage_name]['end'];

            $overlap_start = max($from_point, $start);
            $overlap_end = min($end_point, $end);

            for ($i = $overlap_start; $i <= $overlap_end; $i++) {
                $overlapping_points[] = $i;
            }
        }
    }
    $overlapping_points = array_values(array_unique($overlapping_points));
    //メール配信によるタグおよびログ
    $sql = "SELECT tg.tags, temp.division, lg.user_id 
    FROM logs lg JOIN tags tg ON tg.table_id = lg.template_id 
    JOIN templates temp ON temp.id = lg.template_id 
    WHERE tg.table_name = 'templates' AND lg.cid = ?;";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $logs = $stmt->fetchAll();
    foreach ($logs as $log) {
        if (!isset($user2tags[$log['user_id']])) {
            $user2tags[$log['user_id']] = [];
        }
        if (!isset($user2temps[$log['user_id']])) {
            $user2temps[$log['user_id']][] = 'メール配信';
        }
        $user2tags[$log['user_id']] = array_merge($user2tags[$log['user_id']], explode(',', $log['tags']));
    }
    //フォーム営業によるタグとログ
    $sql = "SELECT tg.tags, temp.division, lg.user_id FROM form_logs lg 
            JOIN tags tg ON tg.table_id = lg.template_id 
            JOIN templates temp ON temp.id = lg.template_id 
            WHERE tg.table_name = 'templates' AND lg.cid = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $form_logs = $stmt->fetchAll();
    foreach ($form_logs as $log) {
        if (!isset($user2tags[$log['user_id']])) {
            $user2tags[$log['user_id']] = [];
        }
        if (!isset($user2temps[$log['user_id']])) {
            $user2temps[$log['user_id']][] = 'フォーム営業';
        }
        $user2tags[$log['user_id']] = array_merge($user2tags[$log['user_id']], explode(',', $log['tags']));
    }
    //ユーザーPDFアクセス
    $sql = "SELECT upa.*, pv.title FROM user_pdf_access upa
    JOIN pdf_versions pv ON upa.pdf_version_id = pv.pdf_version_id
    WHERE pv.deleted = 0 AND upa.cid=?";
    $sql .= " ORDER BY upa.accessed_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $user_pdf_access_all = $stmt->fetchAll();

    foreach ($user_pdf_access_all as $uuu) {
        $user_pdf_access_s[$uuu['user_id']][] = $uuu;
    }
    //ユーザーリダイレクトアクセス
    $sql = "SELECT ura.*, r.title FROM user_redirects_access ura
    JOIN redirects r ON ura.redirect_id = r.id
    WHERE r.deleted = 0 AND ura.cid=?";
    $sql .= " ORDER BY ura.accessed_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $user_redl_access_all = $stmt->fetchAll();

    foreach ($user_redl_access_all as $uuu) {
        $user_redl_access_s[$uuu['user_id']][] = $uuu;
    }
    //ユーザーリダイレクトアクセス
    $sql = "SELECT uca.*, c.title, c.tag FROM user_cms_access uca
    JOIN cmss c ON uca.cms_id = c.cms_id
    WHERE uca.cid=?";
    $sql .= " ORDER BY uca.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $user_cms_access_all = $stmt->fetchAll();

    foreach ($user_cms_access_all as $uuu) {
        $user_cms_access_s[$uuu['user_id']][] = $uuu;
    }
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
//結果取得
$results = [];
$email_data = [];
$total_point = 0;
foreach ($csv_out_data as $row):
    if (isset($_GET['regi_route']) && (is_null($row['route']) || !in_array($row['route'], $_GET['regi_route']))) {
        continue;
    }
    $point = 0;
    $getStage = '';

    $user_pdf_access_count = 0;
    $total_page_moved = 0;
    $access_pdfs = [];
    //該当ユーザーのタグ、PDFとリダイレクトアクセス
    $user2tags[$row['user_id']] = $user2tags[$row['user_id']] ?? [];
    $user_pdf_access = $user_pdf_access_s[$row['user_id']] ?? [];
    $user_redirects_access = $user_redl_access_s[$row['user_id']] ?? [];
    $user_cms_access = $user_cms_access_s[$row['user_id']] ?? [];

    foreach ($user_pdf_access as $access) {
        $sql = "SELECT * FROM tags WHERE table_id = ?  AND table_name = 'pdf_versions'";
        $stmt = $db->prepare($sql);
        $stmt->execute([$access['pdf_version_id']]);
        $pdf2tag = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($pdf2tag)) {
            $user2tags[$row['user_id']] = array_merge($user2tags[$row['user_id']], explode(',', $pdf2tag['tags']));
        }
        if (!in_array($access['session_id'], $access_pdfs)) {
            $user_pdf_access_count++;
            $access_pdfs[] = $access['session_id'];
        }
        if ($access['page'] > 0 && $access['duration'] > 0) {
            $total_page_moved++;
        }
    }

    foreach ($user_redirects_access as $access) {
        $sql = "SELECT * FROM tags WHERE table_id = ? AND table_name = 'redirects'";
        $stmt = $db->prepare($sql);
        $stmt->execute([$access['redirect_id']]);
        $red2tag = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($red2tag)) {
            $user2tags[$row['user_id']] = array_merge($user2tags[$row['user_id']], explode(',', $red2tag['tags']));
        }
    }

    foreach($user_cms_access as $access){
        $user2tags[$row['user_id']] = array_merge($user2tags[$row['user_id']], json_decode($access['tag'], true));
    }

    // 合計アクセス数を計算する
    $totalAccessCount = $user_pdf_access_count + count($user_redirects_access)+ count($user_cms_access);
    if (isset($action['アクセス数']) && $action['アクセス数']['action_state'] == 1) {
        $point += (int) ($totalAccessCount / $action['アクセス数']['action_value']) * $action['アクセス数']['action_point'];
    }
    // 合計閲覧時間を計算する
    $totalDuration = array_reduce($user_pdf_access, function ($carry, $item) {
        return $carry + ($item['duration'] !== null ? $item['duration'] : 0);
    }, 0);
    if (isset($action['閲覧時間']) && $action['閲覧時間']['action_state'] == 1) {
        $point += (int) ($totalDuration / $action['閲覧時間']['action_value']) * $action['閲覧時間']['action_point'];
    }
    //ページ移動数
    if (isset($action['ページ移動数']) && $action['ページ移動数']['action_state'] == 1) {
        $point += (int) ($total_page_moved / $action['ページ移動数']['action_value']) * $action['ページ移動数']['action_point'];
    }
    // CTAクリック数の合計を計算する
    $query = "SELECT COUNT(*) FROM popup_access WHERE user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $row['user_id'], PDO::PARAM_STR);
    $stmt->execute();
    $totalCtaClicks = $stmt->fetchColumn();
    if (isset($action['CTAクリック数']) && $action['CTAクリック数']['action_state'] == 1) {
        $point += (int) ($totalCtaClicks / $action['CTAクリック数']['action_value']) * $action['CTAクリック数']['action_point'];
    }
    //最終アクセス期間
    $final_pdf = (!empty($user_pdf_access)) ? date('Y-m-d', strtotime($user_pdf_access[0]['accessed_at'])) : date('1970-1-1');
    $final_redirect = (!empty($user_redirects_access)) ? date('Y-m-d', strtotime($user_redirects_access[0]['accessed_at'])) : date('1970-1-1');
    $final_cms = (!empty($user_cms_access)) ? date('Y-m-d', strtotime($user_cms_access[0]['created_at'])) : date('1970-1-1');
    
    $final_access = max($final_pdf, $final_redirect, $final_cms);
    
    $today = new DateTime();
    $interval = $today->diff(new DateTime($final_access));
    $not_access_range = $interval->days;
    
    if (isset($action['未アクセス期間']) && $action['未アクセス期間']['action_state'] == 1) {
        $point += (int) ($not_access_range / $action['未アクセス期間']['action_value']) * $action['未アクセス期間']['action_point'];
    }
    if ($point < 0) {
        $point = 0;
    }

    if ($final_access == date('1970-1-1')) {
        $not_access_range = '-';
    }
    //ポイントからステージ取得
    if (!empty($stage)) {
        foreach ($stage as $key => $val) {
            if ($point >= $key) {
                $getStage = $val;
                break;
            }
        }
    }
    //ユーザータグの整理
    $user2tags[$row['user_id']] = isset($user2tags[$row['user_id']]) ? array_unique($user2tags[$row['user_id']]) : [];
    $user2temps[$row['user_id']] = isset($user2temps[$row['user_id']]) ? array_unique($user2temps[$row['user_id']]) : [];

    if (in_array($point, $overlapping_points)) {
        if ($ClientData['form_display'] == 'OFF' && $row['route'] == 'フォーム営業' && $totalAccessCount == 0) {
            continue;
        }
        if (empty($selected_tags) || (!empty($selected_tags) && !empty($result = array_intersect($selected_tags, $user2tags[$row['user_id']])))) {
            if ($row['cuser_id'] == '') {
                $row['cuser_id'] = $client_id;
            }
            $results[] = [
                '顧客ID' => $row['user_id'],
                'client_user' => $client_users[$row['cuser_id']]['last_name'] . $client_users[$row['cuser_id']]['first_name'],
                '企業名' => $row['company'],
                '姓' => $row['surename'],
                '名' => $row['name'],
                'メールアドレス' => $row['email'],
                '企業URL' => $row['url'],
                '電話番号' => $row['tel'],
                '部署名' => $row['depart_name'],
                '役職' => $row['director'],
                '住所' => $row['address'],
                '登録日' => $row['created_at'],
                'ポイント' => $point,
                'ステージ' => $getStage,
                'アクセス数' => $totalAccessCount,
                '閲覧時間' => secondsToTime($totalDuration),
                'ページ移動数' => $total_page_moved,
                'CTAクリック数' => $totalCtaClicks,
                '未アクセス期間' => $not_access_range,
                'service_detail' => $row['service_detail'],
                'hope_matching' => $row['hope_matching'],
                'profile_image' => $row['profile_image'],
                'route' => $row['route'],
                'form_result' => $row['form_result'],
                '滞在秒' => $totalDuration,
            ];
            $total_point += $point;
        }
    }
    $stage2customer[$getStage]++;
    foreach ($user2tags[$row['user_id']] as $t) {
        if(!isset($tag2customer[$t])){
            $tag2customer[$t] = 0;
        }
        $tag2customer[$t]++;
    }
endforeach;
$total_items = count($results);
$total_pages = ceil($total_items / $limit);
?>

<?php
$stmt = $db->prepare("SELECT * FROM users WHERE cid=? AND created_at >= NOW() - INTERVAL 30 SECOND");
$stmt->execute([$client_id]);
$new_users = $stmt->fetchAll();
$new_data = [];
foreach ($new_users as $new) {
    $new_data[] = [
        '0' => $new['email'],
        '1' => $new['url'],
        '2' => $new['surename'],
        '3' => $new['name'],
        '4' => $new['company'],
        '5' => $new['tel'],
        '6' => $new['depart_name'],
        '7' => $new['director'],
        '8' => $new['address'],
    ];
}
$stmt = $db->prepare("SELECT * FROM templates WHERE id=?");
$stmt->execute([$b_template_id]);
$template_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (empty($template_data)) {
    $from_data = [];
} else {
    $stmt = $db->prepare("SELECT * FROM froms WHERE id=?");
    $stmt->execute([$template_data['from_id']]);
    $from_data = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<?php if (isset($_SESSION['read_contact']) && $_SESSION['read_contact'] == 'on'):
    unset($_SESSION['csv_data']);
    $_SESSION['csv_data'] = $new_data;
    $new_user = count($new_users);
    ?>
    <div id="NewUserModal" class="w-100 h-100 d-flex justify-content-center align-items-center position-absolute"
        style="background-color: rgba(0,0,0,0.5);z-index: 10000;">
        <div class="bg-white w-25 pt-3 pb-2">
            <p class="text-center">
                <?= $new_user; ?>件の新しい名刺データが登録されました。
            </p>
            <div class="d-flex justify-content-center">
                <?php if (empty($new_users)): ?>
                    <button class="btn btn-light m-1">今すぐ配信</button>
                    <button class="btn btn-light m-1">予約する</button>
                <?php else: ?>
                    <form action="back/send_email.php" method="post">
                        <input type="hidden" name="cid" value="<?= $client_id ?>">
                        <input type="hidden" name="from_id" value="<?= $send_address ?>">
                        <input type="hidden" name="template_id" value="<?= $b_template_id ?>">
                        <input type="hidden" name="content" value="<?= $template_data['content'] ?>">
                        <button class="btn btn-primary m-1">今すぐ配信</button>
                    </form>
                    <div class="modal fade" id="detail" role="dialog" data-bs-backdrop="static" data-bs-keyboard="false"
                        tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">予約日時</h5>
                                    <button type="button" id="close" class="btn-close" data-bs-dismiss="modal"
                                        aria-bs-label="Close">

                                    </button>
                                </div>
                                <!-- modal-body -->
                                <form action="back/set_schedule.php" method="post">
                                    <div class="modal-body" id="modal_req">
                                        <table>
                                            <tr>
                                                <td><input type="date" name="date" id="date"></td>
                                                <td>
                                                    <select name="hour" id="hour"></select>
                                                    <select name="minute" id="minute"></select>
                                                </td>
                                            </tr>
                                        </table>
                                        <br>
                                        <br>
                                    </div>
                                    <div class="modal-footer">
                                        <input type="hidden" name="cid" value="<?= $client_id ?>">
                                        <input type="hidden" name="from_id" value="<?= $template_data['from_id'] ?>">
                                        <input type="hidden" name="template_id" value="<?= $b_template_id ?>">
                                        <input type="hidden" name="subject" value="<?= $template_data['subject'] ?>">
                                        <input type="hidden" name="email" value="<?= $from_data['email'] ?>">
                                        <input type="hidden" name="from_name" value="<?= $from_data['from_name'] ?>">
                                        <input type="hidden" name="content" value="<?= $template_data['content'] ?>">
                                        <button type="submit" class="btn btn-primary" disabled>予約する</button>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="timepicker()" class="btn btn-warning m-1" data-bs-toggle="modal"
                        data-bs-target="#detail">
                        予約する</button>
                <?php endif; ?>
                <button class="btn btn-dark m-1" onclick="closeNewUser()">閉じる</button>
            </div>
        </div>
    </div>
    <?php
endif; ?>
<?php require ('header.php'); ?>

<body>

    <div class="wrapper">
        <?php require ('menu.php'); ?>
        <main>
            <div class="d-flex justify-content-between">
                <div class="header-with-help">
                    <h1>顧客一覧</h1>
                    <!-- <a href="https://doctrack.jp/usersupport/manual/customer_list/" target="_blank" class="help-icon">
                        <i class="fa fa-question-circle"></i>
                        <span class="tooltip-text">マニュアルページへ</span>
                    </a> -->
                </div>
                <?php require ('dropdown.php'); ?>
            </div>
            <div class="client-header">
                <table class="search-tools">
                    <td><button type="button" class="btn btn-primary" id="modal_button" data-bs-toggle="modal"
                            data-bs-target="#new">新規登録</button></td>
                    <?php $search_input = !empty($_GET['custom_info']) ? $_GET['custom_info'] : "" ?>
                    <form action="" id="searchForm" method="get">
                        <td class="search-input">
                            <input type="hidden" name="search" id="search-input" placeholder="半角英数字またはアンダーバー　登録後変更不可"
                                value="<?= trim($search_input) ?>">
                            <span class="search-after">X</span>
                        </td>
                        <td><button class="btn btn-success" type="button" data-bs-toggle="modal"
                                data-bs-target="#searchModal">検索</button></td>
                    </form>
                    <input type="hidden" id="total_address" value="<?= $total_items ?>">
                    <td>
                        <form id="emailForm" action="email_distribution.php" method="post">
                            <button type="button" onclick="sendForm()" class="btn btn-primary">配信作成</button>
                        </form>
                    </td>
                    <td><button type="button" id="bus_link_btn" target="_blank" class="btn btn-success">名刺データ読込</button>
                        <input type="hidden" name="user_id" id="c_user_id" value="<?= $client_id ?>">
                        <input type="hidden" name="user_id" id="cuser_id" value="<?= $ClientUser['id'] ?>">
                        <?php if ($g_users_data): ?>
                            <form action="back/unlink.php" method="post" id="unlink_form">
                                <input type="hidden" name="cid"
                                    value="<?= htmlspecialchars($client_id, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="email"
                                    value="<?= htmlspecialchars($g_users_data['email'], ENT_QUOTES, 'UTF-8') ?>">
                            </form>
                        <?php endif; ?>
                        <input type="hidden" name="hour" id="c_hour" value="<?= $b_sending_time ?? 9; ?>">
                        <input type="hidden" name="id" id="template-select" value="<?= $b_template_id ?? 1; ?>">
                        <input type="hidden" name="cid" value="<?= $client_id ?>">
                    </td>
                    <td>
                        <form action="./clients_list_chart.php" method="post">
                            <input type="hidden" name="tag2customer"
                                value="<?= htmlspecialchars(json_encode($tag2customer)); ?>">
                            <input type="hidden" name="stage2customer"
                                value="<?= htmlspecialchars(json_encode($stage2customer)); ?>">
                            <button class="btn btn-warning">グラフ表示</button>
                        </form>
                    </td>
                    <form id="csvForm" action="back/csv_client_list.php" method="post">
                        <td><button type="button" class="btn btn-secondary" onclick="submitForm()" <?= in_array('CSV出力', $func_limit) ? 'disabled' : ''; ?>>CSV出力</button></td>
                    </form>
                </table>
                <br>
            </div>
            <br>
            <table class="w-50 table table-bordered">
                <tr>
                    <th>表示数</th>
                    <th>ポイント数の合計</th>
                </tr>
                <tr>
                    <td>
                        <?= $total_items; ?>
                    </td>
                    <td>
                        <?= $total_point; ?>
                    </td>
                </tr>
            </table>
            <div class="modal fade" id="searchModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 600px;">
                    <div class="modal-content">
                        <form action="" method="get">
                            <div class="modal-header">
                                <h5 class="modal-title">検索</h5>
                                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <!-- modal-body -->
                            <div class="modal-body" id="">
                                <table>
                                    <tr>
                                        <td style="min-width: 130px;">登録日</td>
                                        <td class="p-1">
                                            <input type="date" name="date1" value="<?php echo $date1_value; ?>">
                                            ～
                                            <input type="date" name="date2" value="<?php echo $date2_value; ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>顧客詳細</td>
                                        <td class="p-1">
                                            <input class="w-100" type="text" name="custom_info" id="search-input"
                                                value="<?= $custom_info; ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>登録経路</td>
                                        <td class="p-1">
                                            <input type="checkbox" name="regi_route[]" value="メール配信" id="route_mail"
                                                <?= (isset($_GET['regi_route']) && in_array('メール配信', $_GET['regi_route'])) ? 'checked' : ''; ?>>
                                            <label for="route_mail">メール配信</label>
                                            <input type="checkbox" name="regi_route[]" value="フォーム営業" id="route_form"
                                                <?= (isset($_GET['regi_route']) && in_array('フォーム営業', $_GET['regi_route'])) ? 'checked' : ''; ?>>
                                            <label for="route_form">フォーム営業</label>
                                            <input type="checkbox" name="regi_route[]" value="手動" id="route_manual"
                                                <?= (isset($_GET['regi_route']) && in_array('手動', $_GET['regi_route'])) ? 'checked' : ''; ?>>
                                            <label for="route_manual">手動</label>
                                            <input type="checkbox" name="regi_route[]" value="名刺データ" id="route_contacts"
                                                <?= (isset($_GET['regi_route']) && in_array('名刺データ', $_GET['regi_route'])) ? 'checked' : ''; ?>>
                                            <label for="route_contacts">名刺データ</label>
                                            <input type="checkbox" name="regi_route[]" value="問い合わせ" id="route_inqury"
                                                <?= (isset($_GET['regi_route']) && in_array('問い合わせ', $_GET['regi_route'])) ? 'checked' : ''; ?>>
                                            <label for="route_inqury">問い合わせ</label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>ポイント</td>
                                        <td class="p-1 d-flex justify-content-between align-items-center">
                                            <input style="width: 40%;" type="number" min="0" name="from_point" id=""
                                                value="<?= $from_point; ?>">~
                                            <input style="width: 40%;" type="number" min="0" name="end_point" id=""
                                                value="<?= $end_point; ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>ステージ</td>
                                        <td class="p-1">
                                            <?php foreach ($stage as $key => $each): ?>
                                                <input type="checkbox" name="selected_stage[<?= $key; ?>]" id=""
                                                    value="<?= $each; ?>" <?= (in_array($each, $selected_stage)) ? 'checked' : ''; ?> />
                                                <label for="tag[<?= $key; ?>]">
                                                    <?= $each; ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>サービス詳細</td>
                                        <td class="p-1">
                                            <input class="w-100" type="text" name="service_detail" id=""
                                                value="<?= $service_detail; ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>マッチングしたい人</td>
                                        <td class="p-1">
                                            <input class="w-100" type="text" name="hope_matching" id=""
                                                value="<?= $hope_matching; ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>タグ</td>
                                        <td class="p-1">
                                            <?php foreach ($tag_array as $key => $tag): ?>
                                                <input type="checkbox" value="<?= $tag; ?>" id="tag<?= $key; ?>"
                                                    name="tag[<?= $key; ?>]" <?= (in_array($tag, $selected_tags)) ? 'checked' : ''; ?>>
                                                <label for="tag<?= $key; ?>">
                                                    <?= $tag; ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>ユーザータグ</td>
                                        <td class="p-1">
                                            <?php foreach ($client_users as $key => $cuser): ?>
                                                <input type="checkbox" value="<?= $key; ?>" id="c_user<?= $key; ?>"
                                                    name="c_user[<?= $key; ?>]" <?= (in_array($key, $selected_c_users)) ? 'checked' : ''; ?>>
                                                <label for="c_user<?= $key; ?>">
                                                    <?= $cuser['last_name'] . $cuser['first_name']; ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                </table>
                                <br>
                                <br>
                            </div>
                            <div class="modal-footer d-flex justify-content-between">
                                <div class="d-flex align-items-center">
                                    アクセス無しフォーム営業データ
                                    <!-- <label class="switch">
                                        <input type="checkbox" <?php echo $ClientData['ad_display'] === 'ON' ? 'checked' : ''; ?>
                                            id="adSwitch_<?php echo $ClientData['id']; ?>">
                                        <span class="slider round adsetting"
                                            data-content="<?php echo $ClientData['ad_display'] === 'ON' ? '表示' : '非表示'; ?>"></span>
                                    </label> -->
                                    <label class="switch ms-2">
                                        <input type="checkbox" id="formSwitch" name="formSwich"
                                            <?= $ClientData['form_display'] == 'ON' ? 'checked' : ''; ?> value="on">
                                        <span class="slider round" id="formSlider"
                                            data-content="<?= $ClientData['form_display'] == 'ON' ? '表示' : '非表示'; ?>"></span>
                                    </label>
                                </div>
                                <div class="d-flex">
                                    <button class="btn btn-primary me-2">検索</button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="new" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                aria-labelledby="staticBackdropLabel" aria-hidden="true">

                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">新規登録</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                                onclick="cancelEditing()"></button>
                        </div>
                        <!-- modal-body -->
                        <form action="./back/new_users.php" id="new_users_form" method="post">
                            <div class="modal-body" id="modal_req_new">
                                <table id="new_user_register">
                                    <tr>
                                        <td class="p-1">企業名</td>
                                        <td class="p-1">
                                            <input type="text" name="company" id="company">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="p-1">姓</td>
                                        <td class="p-1">
                                            <input type="text" name="surname" id="surname" required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="p-1">名</td>
                                        <td class="p-1">
                                            <input type="text" name="firstname" id="firstname" required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="p-1">メールアドレス</td>
                                        <td class="p-1">
                                            <input type="email" name="email" id="email" required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="p-1">企業URL</td>
                                        <td class="p-1">
                                            <input type="text" name="company_url" id="company_url">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="p-1">電話番号</td>
                                        <td class="p-1">
                                            <input type="text" name="new_tel" id="new_tel">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="p-1">部署名</td>
                                        <td class="p-1">
                                            <input type="text" name="new_depart_name" id="new_depart_name">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="p-1">役職</td>
                                        <td class="p-1">
                                            <input type="text" name="new_director" id="new_director">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="p-1">住所</td>
                                        <td class="p-1">
                                            <input type="text" name="new_address" id="new_address">
                                        </td>
                                    </tr>
                                </table>
                                <br>
                                <br>
                            </div>
                            <div class="modal-footer">
                                <input type="hidden" name="cid" value="<?= $client_id ?>">
                                <input type="hidden" name="cuser_id" value="<?= $ClientUser['id'] ?>">
                                <button type="button" class="btn btn-primary" onclick="insert_confirm()">新規登録</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="detail" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                aria-labelledby="staticBackdropLabel" aria-hidden="true" style="width: ;">
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
                                            <input name="user_id" id="user_id" disabled />
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
                                        <td>企業URL <a id="url_link" href="" target="_blank"><i class='fa fa-link'
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
                            <input type="hidden" name="user_id" id="url_user_id">
                            <input type="hidden" name="company" id="url_company">
                            <input type="hidden" name="name" id="url_name">
                        </form>
                        <form action="./clients_list_template.php" method="get" id="tempForm">
                            <input type="hidden" name="user_id" id="temp_user_id">
                            <input type="hidden" name="company" id="temp_company">
                            <input type="hidden" name="name" id="temp_name">
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
                                    <td>タグ:</td>
                                    <td id="tagContainer"></td>
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

            <!-- ページネーションリンクを表示 -->
            <?php
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

            <div class="pagination">
                <ul class="pagination">
                    <?= $pagination_links ?>
                </ul>
            </div>

            <table class="table">
                <tr>
                    <th>企業名</th>
                    <th>名前</th>
                    <th>電話番号</th>
                    <th>ポイント</th>
                    <th>ステージ</th>
                    <th>アクセス数</th>
                    <th>閲覧時間</th>
                    <th></th>
                    <th></th>
                </tr>
                <?php for ($key = ($current_page - 1) * 10; $key < $current_page * 10; $key++):
                    if (isset($results[$key])):
                        $row = $results[$key];
                        $point = 0;
                        if (isset($action['アクセス数']) && $action['アクセス数']['action_state'] == 1) {
                            $point += (int) ($row['アクセス数'] / $action['アクセス数']['action_value']) * $action['アクセス数']['action_point'];
                        }
                        if (isset($action['閲覧時間']) && $action['閲覧時間']['action_state'] == 1) {
                            $point += (int) ($row['滞在秒'] / $action['閲覧時間']['action_value']) * $action['閲覧時間']['action_point'];
                        }
                        if (isset($action['ページ移動数']) && $action['ページ移動数']['action_state'] == 1) {
                            $point += (int) ($row['ページ移動数'] / $action['ページ移動数']['action_value']) * $action['ページ移動数']['action_point'];
                        }
                        if (isset($action['CTAクリック数']) && $action['CTAクリック数']['action_state'] == 1) {
                            $point += (int) ($row['CTAクリック数'] / $action['CTAクリック数']['action_value']) * $action['CTAクリック数']['action_point'];
                        }
                        ?>
                        <?php
                        $email_data[] = [
                            $row['顧客ID'],
                            $row['メールアドレス'],
                            $row['企業URL'],
                            $row['姓'],
                            $row['名'],
                            $row['企業名']
                        ];
                        ?>

                        <?php if (!is_null($row['form_result'])): ?>
                            <div class="modal fade" id="form-card_<?= $row['顧客ID']; ?>" data-bs-backdrop="static"
                                data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content" id="modal_content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">フォーム</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                        </div>
                                        <!-- modal-body -->

                                        <div class="modal-body" id="modal_req_<?= $row['顧客ID']; ?>">
                                            <div>
                                                <p><?= nl2br($row['form_result']) ?></p>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <!-- Add a data attribute to store the modal ID -->
                                            <button type="button" class="btn btn-primary copyButton"
                                                data-modal-id="<?= $row['顧客ID'] ?>">コピー</button>
                                            <button type=" button" class="btn btn-secondary" onclick="cancelEditing()"
                                                data-bs-dismiss="modal">閉じる</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- JavaScript code to handle copying -->

                        <tr>
                            <td>
                                <?= $row['企業名']; ?>
                            </td>
                            <td>
                                <?= $row['姓'] . $row['名']; ?>
                            </td>
                            <td>
                                <?= $row['電話番号']; ?>
                            </td>
                            <td>
                                <?= $row['ポイント']; ?>
                            </td>
                            <td>
                                <?= $row['ステージ']; ?>
                            </td>
                            <td>
                                <?= $row['アクセス数']; ?>
                            </td>
                            <td>
                                <?= $row['閲覧時間']; ?>
                            </td>
                            <td>
                                <button type="button" data-bs-toggle="modal" data-bs-target="#detail" class="btn btn-primary"
                                    onclick="handleClick(`<?= $row['顧客ID']; ?>`,`<?= $row['企業名']; ?>`,`<?= $row['姓']; ?>`,`<?= $row['名']; ?>`,`<?= $row['メールアドレス']; ?>`,`<?= $row['企業URL']; ?>`,`<?= $row['電話番号']; ?>`,`<?= $row['部署名']; ?>`,`<?= $row['役職']; ?>`,`<?= $row['住所']; ?>`,`<?= $row['hope_matching']; ?>`,`<?= $row['service_detail']; ?>`,`<?= $link_data ?>`,`<?= $row['ポイント'] ?>`,`<?= $row['ステージ'] ?>`,`<?= $row['アクセス数'] ?>`,`<?= $row['閲覧時間'] ?>`,`<?= $row['ページ移動数'] ?>`,`<?= $row['CTAクリック数'] ?>`,`<?= $row['未アクセス期間'] ?>`,`<?= $row['profile_image']; ?>`,`<?= substr($row['登録日'], 0, 16) ?>`,`<?= implode(',', $user2tags[$row['顧客ID']]) ?>`,`<?= $row['route'] ?>`,`<?= $row['client_user'] ?>`);">詳細</button>
                            </td>
                            <td>
                                <form action="access_analysis.php" method="get">
                                    <input type="hidden" name="user_id" value="<?= $row['顧客ID'] ?>">
                                    <button type="submit" class="btn btn-primary">解析</button>
                                </form>
                            </td>
                        </tr>
                    <?php endif; endfor; ?>
            </table>
        </main>
    </div>
    <script>
        var results = <?= json_encode($results) ?>;
        function closeNewUser() {
            $('#NewUserModal').removeClass('d-flex');
            $('#NewUserModal').addClass('d-none');
            $.ajax({
                type: 'POST',
                url: './back/read_contact_state.php',
                data: { unsetSession: true },
                success: function (response) {
                    window.location.reload()
                    console.log(response);
                },
                error: function (error) {
                    console.error(error);
                }
            });
        }
        // Xボタン
        let search_input = document.getElementById('search-input');
        search_input.addEventListener('mouseenter', function () {

            search_input.addEventListener('paste', function (e) {
                e.preventDefault();
                const text = (e.clipboardData || window.clipboardData).getData('text').trim();
                search_input.value = text;
            });

            if (search_input.value != "") {
                document.querySelector('table.search-tools span.search-after').style.display = "block";
                let searchCancelButton = document.querySelector('table.search-tools span.search-after');
                searchCancelButton.addEventListener('click', function () {
                    location.href = "./clients_list.php";
                });
            }
        });

        // 検索の空白をすべて削除
        document.getElementById('search-input').addEventListener('input', function () {
            this.value = this.value.replace(/\s+/g, '');
        });

        let inputs = document.querySelectorAll('#modal_req input:not(#user_id), #modal_req textarea');
        let editButton = document.getElementById('edit_button');
        let saveButton = document.getElementById('save_button');
        let memo_editButton = document.getElementById('memo_edit_button');
        let memo_saveButton = document.getElementById('memo_save_button');
        let closeBtn = document.getElementById('close');
        function enableEditing() {
            inputs.forEach(input => {
                input.disabled = false;
            });

            editButton.style.display = 'none';
            saveButton.style.display = 'inline-block';
            memo_editButton.style.display = 'none';
            memo_saveButton.style.display = 'inline-block';
        }

        function cancelEditing() {
            inputs.forEach(input => {
                input.disabled = true;
            });
            saveButton.style.display = "none";
            editButton.style.display = "block";
            memo_saveButton.style.display = "none";
            memo_editButton.style.display = "block";
        }
        function handleClick(user_id, company, surename, name, email, url, tel, depart_name, director, address, hope_matching, service_detail, link_data, point, getStage, totalAccessCount, totalDuration, total_page_moved, totalCtaClicks, non_access, profile_image, created_at, tags, route, client_user) {
            let link_div = document.getElementById("link_data");
            let detail_div = document.getElementById("detail_div");
            document.getElementById("user_id").value = user_id;
            document.getElementById("url_user_id").value = user_id;
            document.getElementById("temp_user_id").value = user_id;
            document.getElementById("userid").value = user_id;
            document.getElementById("link_company").value = company;
            document.getElementById("url_company").value = company;
            document.getElementById("temp_company").value = company;
            document.getElementById("link_surename").value = surename;
            document.getElementById("link_name").value = name;
            document.getElementById("url_name").value = surename + name;
            document.getElementById("temp_name").value = surename + name;
            document.getElementById("link_email").value = email;
            document.getElementById("tracking-user").innerText = client_user;

            document.getElementById("url").value = url;
            if (!url.startsWith("http") && !url.startsWith("https")) {
                url = "https://" + url;
            }
            document.getElementById("url_link").href = url;
            document.getElementById("tel").value = tel;
            document.getElementById("depart_name").value = depart_name;
            document.getElementById("director").value = director;
            document.getElementById("address").value = address;
            document.getElementById("created_at").innerText = created_at

            link_div.innerHTML = '<button type="button" id="edit_button" class="btn btn-danger me-2" onclick="deleteUser(`' + user_id + '`)" <?= in_array('削除', $func_limit) ? 'disabled' : ''; ?>>削除</button>'
            if (link_data > 0 && email != "" && profile_image != "") {
                link_div.innerHTML += '<button type="button" data-bs-toggle="modal"  data-bs-target="#business-card" onclick="businessCardHandle(`' + email + '`,`' + surename + '`,`' + name + '`,`' + company + '`,`' + user_id + '`,`' + tel + '`,`' + depart_name + '`,`' + address + '`,`' + profile_image + '`)" data-bs-dismiss="modal" class="btn btn-primary">名刺</button>';
            } else if (route == '問い合わせ') {
                link_div.innerHTML += '<button type="button" data-bs-toggle="modal"  data-bs-target="#form-card_' + user_id + '" data-bs-dismiss="modal" class="btn btn-primary">フォーム</button>';
            } else {
                link_div.innerHTML += '<button id="contacts" class="btn btn-secondary" disabled>連携なし</button>';
            }
            detail_div.innerHTML = '<button type="button" data-bs-toggle="modal"  data-bs-target="#tracking_detail" onclick="trackingHandle(`' + point + '`,`' + getStage + '`,`' + totalAccessCount + '`,`' + totalDuration + '`,`' + total_page_moved + '`,`' + totalCtaClicks + '`,`' + non_access + '`,`' + tags + '`,`' + route + '`)" data-bs-dismiss="modal" class="btn btn-success me-2">トラッキング詳細</button>';
            detail_div.innerHTML += '<button type="button" onclick="tempFormSubmit()" class="btn btn-primary me-2">テンプレート</button>';
            detail_div.innerHTML += '<button type="button" onclick="urlFormSubmit()" class="btn btn-primary me-2">URL</button>';
            detail_div.innerHTML += '<button type="button" data-bs-toggle="modal"  data-bs-target="#memo_detail" onclick="memoHandle(`' + user_id + '`,`' + service_detail + '`,`' + hope_matching + '`)" data-bs-dismiss="modal" class="btn btn-warning">メモ</button>';
        }
        function trackingHandle(point, stage, accessNum, duringTime, pageMove, ctaClick, non_access, tags, route) {
            let tag_div = document.getElementById("tagContainer");
            tag_div.innerHTML = '';

            document.getElementById("tracking-point").innerText = point;
            document.getElementById("tracking-stage").innerText = stage;
            document.getElementById("tracking-accessNum").innerText = accessNum;
            document.getElementById("tracking-duringTime").innerText = duringTime;
            document.getElementById("tracking-pageMove").innerText = pageMove;
            document.getElementById("tracking-ctaClick").innerText = ctaClick;
            document.getElementById("tracking-non_access").innerText = non_access;
            document.getElementById("tracking-route").innerText = route;
            var tagsArray = tags.split(',');
            console.log(tagsArray);
            tagsArray.forEach(function (tag) {
                tag_div.innerHTML += '<input type="checkbox" value="' + tag + '" disabled checked /><label for="">' + tag + '</label>';
            })
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
        function submitForm() {
            // hidden inputを作成してフォームに追加
            var input = document.createElement("input");
            input.setAttribute("type", "hidden");
            input.setAttribute("name", "csv_data");
            input.setAttribute("value", JSON.stringify(results));
            document.getElementById("csvForm").appendChild(input);

            // フォームを送信
            document.getElementById("csvForm").submit();
        }
        function urlFormSubmit() {
            document.getElementById("urlForm").submit();
        }
        function tempFormSubmit() {
            document.getElementById("tempForm").submit();
        }
        function deleteUser(id) {
            var confirmed = confirm("顧客データを本当に削除しますか？");
            if (confirmed) {
                var cid = document.getElementById('c_user_id').value;
                location.href = "./back/del_user.php?id=" + id + "&cid=" + cid;
            }
        }
        function sendForm() {
            // JSON形式で取得
            var email_cnt = <?= count($results); ?>;
            if (email_cnt > 200) {
                alert('顧客数が200を超えました。');
                return;
            } else {
                // hidden inputを作成してフォームに追加
                var input = document.createElement("input");
                input.setAttribute("type", "hidden");
                input.setAttribute("name", "email_data");
                input.setAttribute("value", JSON.stringify(results));
                document.getElementById("emailForm").appendChild(input);

                // フォームを送信
                document.getElementById("emailForm").submit();
            }
        }
        function businessCardHandle(email, surename, name, company, user_id, tel, depart_name, address, profile_image) {

            var fullName = surename + " " + name;
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

        document.addEventListener('DOMContentLoaded', () => {
            const linkBtn = document.getElementById('bus_link_btn');
            const userId = document.getElementById('cuser_id').value;
            const templateSelect = document.getElementById('template-select');

            let date = new Date();
            let year = date.getFullYear();
            let month = date.getMonth() + 1;
            let day = date.getDate();

            let hour = date.getHours();

            let hourSelect = document.getElementById("c_hour");

            let current_date = year + '-' + month + '-' + day;
            let current_hour = parseInt(hour);
            let scheduled_date = current_date;
            let scheduled_hour = hourSelect.value;

            let googleScreen;

            // 名刺連携開始ボタンのイベントハンドラ
            linkBtn?.addEventListener('click', () => {
                <?php if (empty($template_data)): ?>
                    alert("テンプレートが設定されていません。");
                    window.location.href = './settings.php?tab=link'
                    return;
                <?php endif; ?>
                <?php if ($ClientData['temp_display'] === 'OFF'): ?>
                    alert("テンプレートが無効状態です。");
                    return;
                <?php endif; ?>
                $.ajax({
                    type: 'POST',
                    url: './back/read_contact_state.php',
                    data: { setSession: true },
                    success: function (response) {
                        console.log(response);
                    },
                    error: function (error) {
                        console.error(error);
                    }
                });

                let url = `https://in.doc1.jp/google-api-php-client/api/index.php?client_id=${userId}&temp_id=${templateSelect.value}&date=${scheduled_date}&hour=${scheduled_hour}`;
                let width = 500;
                let height = 600;
                let left = window.screenX + (window.outerWidth - width) / 2;
                let top = window.screenY + (window.outerHeight - height) / 2;
                googleScreen = window.open(url, "_blank", "toolbar=yes,resizable=yes,top=" + top + ",left=" + left + ",width=" + width + ",height=" + height + "");
                const checkGoogleScreenClosed = () => {
                    if (googleScreen && googleScreen.closed) {
                        window.location.reload()
                        window.removeEventListener('focus', checkGoogleScreenClosed);
                    }
                };

                window.addEventListener('focus', checkGoogleScreenClosed);
            });
        });
    </script>
    <script>
        var copyButtons = document.querySelectorAll('.copyButton');

        copyButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var modalId = button.getAttribute('data-modal-id');
                var modalContent = document.getElementById('modal_req_' + modalId);

                if (!modalContent) {
                    console.error('Modal content not found for ID: ' + modalId);
                    alert('Modal content not found.');
                    return;
                }

                var textToCopy = modalContent.textContent;

                navigator.clipboard.writeText(textToCopy)
                    .then(function () {
                        console.log('Text copied to clipboard');
                        alert('コピーしました。');
                    })
                    .catch(function (err) {
                        console.error('Error copying text to clipboard:', err);
                        alert('コピーに失敗しました。');
                    });
            });
        });
    </script>
    <script>
        var formSwitch = document.getElementById('formSwitch');
        var formSlider = document.getElementById('formSlider');
        // Event listener for tempSwitch
        formSwitch.addEventListener('change', function () {
            const onOff = this.checked ? 'ON' : 'OFF';
            formSlider.setAttribute('data-content', this.checked ? '表示' : '非表示');
            var xhr = new XMLHttpRequest();
            xhr.open('POST', './back/ch_switch_form_display.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('switch=' + onOff + '&id=<?php echo $ClientData['id']; ?>');
        });
    </script>

    <script src="./assets/js/client/client_list.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
        crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI"
        crossorigin="anonymous"></script>
</body>

</html>