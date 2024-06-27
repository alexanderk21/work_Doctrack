<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if the token is set in the URL
if (!isset($_GET['t'])) {
    echo 'トークンが不正です。'; // Invalid token message
    exit();
}

// Unset previous session data
if (isset($_SESSION['csv_data'])) {
    unset($_SESSION["csv_data"]);
}
if (isset($_SESSION['csv_data_form'])) {
    unset($_SESSION["csv_data_form"]);
}

// Include database configuration
include_once 'config/database.php';
date_default_timezone_set('Asia/Tokyo');

// Extract protocol and URL directory
$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$url_dir = $protocol . $_SERVER['HTTP_HOST'];
$request_uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);
$url_dir .= dirname($request_uri_parts[0]);

if (substr($url_dir, -1) !== '/') {
    $url_dir .= '/';
}

// Extract data from token
$token = $_GET['t'];
$token_parts = explode('/', $token);
if (count($token_parts) < 1) {
    echo 'トークンが不正です。'; // Invalid token message
    exit();
}

$host = $_SERVER['HTTP_HOST'];
$parts = explode('.', $host);

if (count($parts) > 2) {
    $subdomain = $parts[0];
} else {
    $subdomain = null;
}

// Extract necessary information from the token
$cms_id = $token_parts[0];
$user_id = isset($token_parts[1]) ? $token_parts[1] : null;
$token = isset($token_parts[2]) ? $token_parts[2] : '';

function isMobileDevice()
{
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}
$isMobile = isMobileDevice();

try {
    // Create PDO instance
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $client_id = $subdomain;
    $sql = "SELECT * FROM clients WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$client_id]);
    $ClientData = $stmt->fetch(PDO::FETCH_ASSOC);
    function encrypt($data, $key)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    $encryptionKey = "your_secret_key";

    $encryptedId = encrypt($client_id, $encryptionKey);
    if (!is_null($user_id)) {
        $sql = "INSERT INTO user_cms_access (user_id ,cms_id, cid, token) VALUES (:user_id ,:cms_id, :cid, :token)";
        $stmt = $db->prepare($sql);
        $params = array(':user_id' => $user_id, ':cms_id' => $cms_id, ':cid' => $subdomain, ':token' => $token);
        $stmt->execute($params);

        $action = [];
        $stage = [];
        $point = 0;

        $sql = "SELECT * FROM users WHERE user_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);


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

        if (!empty($ClientData) && isset($ClientData['chatwork_id']) && !empty($action) && !empty($stage)) {
            $room = $ClientData['chatwork_id'];
            $reach_stage = explode(',', $ClientData['reach_stage']);
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
    }

    $sql = "SELECT * FROM redirects WHERE cid = ? AND deleted = 0";
    $stmt = $db->prepare($sql);
    $stmt->execute([$subdomain]);
    $redl_data = $stmt->fetchAll();
    $id2redl = [];
    foreach ($redl_data as $each) {
        $id2redl[$each['id']] = $each;
    }

    $sql = "SELECT * FROM pdf_versions WHERE cid = ? AND deleted = 0";
    $stmt = $db->prepare($sql);
    $stmt->execute([$subdomain]);
    $pdf_data = $stmt->fetchAll();
    $id2pdf = [];
    foreach ($pdf_data as $each) {
        $id2pdf[$each['pdf_id']] = $each;
    }

    // Fetch CMS data based on cms_id
    $stmt = $db->prepare("SELECT * FROM cmss WHERE cms_id = ?");
    $stmt->execute([$cms_id]);
    $cms_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if CMS data exists
    if (!$cms_data) {
        echo 'CMSデータが見つかりません。'; // CMS data not found message
        exit();
    }

    // Decode content JSON if available
    $content = is_null($cms_data['content']) ? [] : json_decode($cms_data['content'], true);

    $stmt = $db->prepare("SELECT * FROM favi_logos WHERE client_id = ?");
    $stmt->execute([$subdomain]);
    $favi_logo = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage(); // Connection failure message
    exit();
}
?>

<!DOCTYPE HTML>
<html>

<head>
    <title><?= $cms_data['title']; ?></title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
    <link rel="stylesheet" href="assets/css/cms_view/main.css" />
    <link rel="icon" type="image/png"
        href="<?= isset($favi_logo['favi_img']) ? './favi_logos/' . $favi_logo['favi_img'] : ''; ?>">
    <noscript>
        <link rel="stylesheet" href="assets/css/cms_view/noscript.css" />
    </noscript>
</head>

<body class="is-preload">
    <!-- Wrapper -->
    <div id="wrapper">
        <!-- Header -->
        <header id="header">
            <div class="inner">
                <!-- Logo -->
                <div class="logo">
                    <span class="symbol"><img
                            src="<?= isset($cms_data['logo_img']) ? './favi_logos/' . $cms_data['logo_img'] . '.png' : ''; ?>"
                            alt="" />
                </div>
            </div>
        </header>
        <!-- Main -->
        <div id="main">
            <div class="inner">
                <header>
                    <h1><?= $cms_data['title']; ?></h1>
                    <p><?= $cms_data['memo']; ?></p>
                </header>
                <section class="tiles">
                    <?php foreach ($content as $key => $row): ?>
                        <?php
                        if ($row['type'] == 'pdf') {
                            $url_ = $id2pdf[$row['template']]['pdf_id'] . '/' . $user_id;
                            $t_url = $url_dir . 's?t=' . $url_;

                            $ogImage = $url_dir . 'pdf_img/' . $id2pdf[$row['template']]['pdf_id'] . '_' . $id2pdf[$row['template']]['pdf_version_id'] . '-0.jpg';
                            list($width, $height) = getimagesize($ogImage);
                        } else {
                            $url_ = $id2redl[$row['template']]['id'] . '/' . $user_id;
                            $t_url = $url_dir . 'r?t=' . $url_;
                            if (!is_null($id2redl[$row['template']]['redl_img']) && $id2redl[$row['template']]['redl_img'] != '') {
                                $ogImage = $url_dir . 'redl_img/' . $id2redl[$row['template']]['redl_img'];
                                list($width, $height) = getimagesize($ogImage);
                            } else {
                                $html = file_get_contents($id2redl[$row['template']]['url']);

                                $dom = new DOMDocument();
                                @$dom->loadHTML($html);

                                $metaTags = $dom->getElementsByTagName('meta');
                                $ogImage = '';
                                $width = 1;
                                $height = 1;
                                foreach ($metaTags as $tag) {
                                    if ($tag->hasAttribute('property') && $tag->getAttribute('property') === 'og:image') {
                                        $ogImage = $tag->getAttribute('content');
                                        break;
                                    }
                                }

                                if (!empty($ogImage) && filter_var($ogImage, FILTER_VALIDATE_URL)) {
                                    try {
                                        $ch = curl_init($ogImage);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
                                        $response = curl_exec($ch);
                                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                        curl_close($ch);

                                        if ($httpCode == 200) {
                                            $imageInfo = getimagesizefromstring($response);
                                            $width = $imageInfo[0];
                                            $height = $imageInfo[1];
                                        } else {
                                            $width = 1;
                                            $height = 1;
                                        }
                                    } catch (Exception $e) {
                                        $width = 1;
                                        $height = 1;
                                    }
                                }

                            }
                        }

                        $ratio = $height / $width;
                        ?>
                        <article class="style">
                            <span class="image" style="align-items: <?= $ratio > 0.58 ? 'baseline' : 'center'; ?>;">
                                <img src="<?= $ogImage; ?>" alt="" />
                            </span>
                            <a href="<?= $t_url; ?>" target="_blank">
                                <h2><?= $row['type'] == 'pdf' ? $id2pdf[$row['template']]['title'] : $id2redl[$row['template']]['title']; ?>
                                </h2>
                                <div class="content">
                                    <p><?= $row['type'] == 'pdf' ? $id2pdf[$row['template']]['memo'] : $id2redl[$row['template']]['memo']; ?>
                                    </p>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </section>
            </div>
        </div>
    </div>
    <?php if ($ClientData['license_display'] == "ON"): ?>
        <div id="license_msg">
            <a href="https://in.doc1.jp/signup_1.php?ref='<?= $encryptedId; ?>'" target="_blank" style="display: block; width: 100%;">
                <p style="text-align: center; color: #0d6efd;">
                    この資料閲覧機能は無料ツールで作成されてます。
                    <span class="cta">無料作成</span>
                </p>
            </a>
        </div>
    <?php endif; ?>
    <script src="assets/js/cms_view/jquery.min.js"></script>
    <script src="assets/js/cms_view/browser.min.js"></script>
    <script src="assets/js/cms_view/breakpoints.min.js"></script>
    <script src="assets/js/cms_view/util.js"></script>
    <script src="assets/js/cms_view/main.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
        crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI"
        crossorigin="anonymous"></script>
</body>

</html>