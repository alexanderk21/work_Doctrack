<?php
if (isset($_POST['timeSpent']) && isset($_POST['inserted_id'])) {
    $access_id = $_POST['inserted_id'];
    $timeSpent = $_POST['timeSpent'];

    include_once '../config/database.php';

    try {
        $db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $stmt = $db->prepare('UPDATE user_pdf_access SET duration=:duration WHERE access_id = :access_id');
        $stmt->execute(array(':access_id' => $access_id, ':duration' => $timeSpent));

        $db = null;
    } catch (PDOException $e) {
        echo '接続失敗' . $e->getMessage();
        exit();
    }
}




try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = "SELECT * FROM user_pdf_access WHERE  access_id = :access_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':access_id' => $access_id]);
    $current_access = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $client_id = $current_access['cid'];
    $user_id = $current_access['user_id'];
    $action = [];
    $stage = [];
    
    $sql = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $sql = "SELECT chatwork_id, reach_stage FROM clients WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $chatwork = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $sql = "SELECT * FROM actions WHERE client_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $actions = $stmt->fetchAll();
    foreach ($actions as $val) {
        $action[$val['action_name']] = $val;
    }
    
    $sql = "SELECT * FROM stages WHERE client_id = ? AND stage_state = 1 ORDER BY stage_point DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $stages = $stmt->fetchAll();
    foreach ($stages as $key => $val) {
        $stage[$val['stage_point']] = [
            'stage_name' => $val['stage_name'],
            'stage_memo' => $val['stage_memo'],
        ];
    }
    
    if (!empty($chatwork) && isset($chatwork['chatwork_id']) && !empty($action) && !empty($stage)) {
        $room = $chatwork['chatwork_id'];
        $reach_stage = explode(',', $chatwork['reach_stage']);
        $start_point = array_keys($stage)[count($stage) - 2];
        //PDF
        $sql = "SELECT upa.*, pv.title FROM user_pdf_access upa
        JOIN pdf_versions pv ON upa.pdf_version_id = pv.pdf_version_id
        WHERE pv.deleted = 0 AND upa.cid=?";
        $sql .= " AND upa.user_id LIKE '%" . $user_id . "%'";
        $sql .= " ORDER BY upa.accessed_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$client_id]);
        $user_pdf_access = $stmt->fetchAll();
        $user_pdf_access_count = 0;
        $access_pdfs = [];

        foreach ($user_pdf_access as $access) {
            if (!in_array($access['session_id'], $access_pdfs)) {
                $user_pdf_access_count++;
                $access_pdfs[] = $access['session_id'];
            }
        }
        //リダイレクト
        $sql = "SELECT ura.*, r.title FROM user_redirects_access ura
        JOIN redirects r ON ura.redirect_id = r.id
        WHERE r.deleted = 0 AND ura.cid=?";
        $sql .= " AND ura.user_id = ?";
        $sql .= " ORDER BY ura.accessed_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$client_id, $user_id]);
        $user_redirects_access = $stmt->fetchAll();

        // 合計アクセス数を計算する
        $totalAccessCount = $user_pdf_access_count + count($user_redirects_access);
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
        $total_page_moved = 0;
        foreach ($user_pdf_access as $access) {
            if ($access['page'] > 0 && $access['duration'] > 0) {
                $total_page_moved++;
            }
        }
        if (isset($action['ページ移動数']) && $action['ページ移動数']['action_state'] == 1) {
            $point += (int) ($total_page_moved / $action['ページ移動数']['action_value']) * $action['ページ移動数']['action_point'];
        }
        // CTAクリック数の合計を計算する
        $query = "SELECT COUNT(*) FROM popup_access WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
        $stmt->execute();
        $totalCtaClicks = $stmt->fetchColumn();
        if (isset($action['CTAクリック数']) && $action['CTAクリック数']['action_state'] == 1) {
            $point += (int) ($totalCtaClicks / $action['CTAクリック数']['action_value']) * $action['CTAクリック数']['action_point'];
        }
        //最終アクセス期間
        $final_pdf = (!empty($user_pdf_access)) ? date('Y-m-d', strtotime($user_pdf_access[0]['accessed_at'])) : date('1970-1-1');
        $final_redirect = (!empty($user_redirects_access)) ? date('Y-m-d', strtotime($user_redirects_access[0]['accessed_at'])) : date('1970-1-1');
        if ($final_pdf > $final_redirect) {
            $final_access = $final_pdf;
        } else {
            $final_access = $final_redirect;
        }
        $today = new DateTime();
        $interval = $today->diff(new DateTime($final_access));
        $not_access_range = $interval->days;
        if (isset($action['未アクセス期間']) && $action['未アクセス期間']['action_state'] == 1) {
            $point += (int) ($not_access_range / $action['未アクセス期間']['action_value']) * $action['未アクセス期間']['action_point'];
        }
        if ($point > $row['notice_point']) {
            $getStage = '';
            if (!empty($stage)) {
                foreach ($stage as $key => $val) {
                    if ($point >= $key) {
                        $getStage = $val['stage_name'];
                        $getStageMemo = $val['stage_memo'];
                        break;
                    }
                }
            }
            if (in_array($getStage, $reach_stage) && ($getStage != $row['notice_stage'])) {
                $i++;
                $message = "企業名：" . $row['company'] . "\n";
                $message .= "名前：" . $row['surename'] . $row['name'] . "\n";
                $message .= "電話番号：" . $row['tel'] . "\n";
                $message .= "メールアドレス：" . $row['email'] . "\n";
                $message .= "ステージ：" . $getStage . "\n";
                $message .= $getStageMemo;
                $token = '29a5c3f4be07e1f71981f4afd1c9925c';
                $result = chatworkSender($message, $token, $room);
                $return = json_decode($result, true);
                $noti_data .= $row['user_id'] . '-' . $getStage . ', ';
                if (isset($return['message_id'])) {
                    $stmt = $db->prepare('UPDATE users SET notice_point=:notice_point, notice_stage=:notice_stage  WHERE user_id = :user_id');
                    $stmt->execute(array(':user_id' => $row['user_id'], ':notice_point' => $point, ':notice_stage' => $getStage));
                }
            }
        }
    }

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
function chatworkSender($message, $token, $room)
{
    $msg = array(
        'body' => $message
    );

    $ch = curl_init();
    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array('X-ChatWorkToken: ' . $token)
    );
    curl_setopt($ch, CURLOPT_URL, "https://api.chatwork.com/v2/rooms/" . $room . "/messages");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($msg, '', '&'));
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}
