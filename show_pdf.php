<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (isset($_GET['t'])) {
    include_once 'config/database.php';

    date_default_timezone_set('Asia/Tokyo');
    $updated_at = date('Y-m-d H:i:s');

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $directory = dirname($_SERVER['PHP_SELF']);

    $current_directory_url = $protocol . "://" . $host . $directory;

    $meta_url = stripslashes($current_directory_url);
    $user_id = null;
    $access_count = 0;
    $page = (empty($_GET['page'])) ? 0 : $_GET['page'];

    //subdomain
    $host = $_SERVER['HTTP_HOST'];
    $parts = explode('.', $host);

    if (count($parts) > 2) {
        $subdomain = $parts[0];
    } else {
        $subdomain = null;
    }

    if (substr_count($_GET['t'], '/') >= 1) {
        $decode_data = $_GET['t'];
        $client_id = $subdomain;
        $t = explode('/', $decode_data);
        $pdf_id = $t[0];
        if (isset($t[1])) {
            $user_id = $t[1];
        }
        if (isset($t[2])) {
            $token = $t[2];
        } else {
            $token = '';
        }
    } else {
        exit();
    }

    try {
        $db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $stmt = $db->prepare("SELECT * FROM pdf_versions WHERE pdf_id=? ORDER BY uploaded_at DESC LIMIT 1");
        $stmt->execute([$pdf_id]);
        $pdf_versions_data = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT * FROM clients WHERE id=?");
        $stmt->execute([$client_id]);
        $ClientData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT * FROM favi_logos WHERE client_id = ?");
        $stmt->execute([$subdomain]);
        $favi_logo = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo '接続失敗' . $e->getMessage();
        exit();
    }

    // POPUPを読み込む
    try {
        $db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $stmt = $db->prepare("SELECT * FROM popups WHERE pdf_id=?");
        $stmt->execute([$pdf_id]);
        $popup_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        echo '接続失敗' . $e->getMessage();
        exit();
    }
    
    $popups = array();
    foreach ($popup_data as $popup) {
        $popup_id = $popup['id'];
        $popups[$popup_id]['switch'] = $popup['switch'];
        $popups[$popup_id]['trigger_parameter'] = $popup['trigger_parameter'];
        $popups[$popup_id]['trigger_parameter2'] = $popup['trigger_parameter2'];
        $popups[$popup_id]['popup_trigger'] = $popup['popup_trigger'];
        $popups[$popup_id]['url'] = $popup['url'];
    }
    
    // 顧客IDが存在する場合（実データの場合）
    $inserted_id = -1;

    //アクセス履歴に追加
    if (empty($_GET['r'])) {
        try {
            if (isset($_GET['page'])) {
                $r_page = $page + 1;
            } else {
                $r_page = 1;
            }

            // $_GET['page']がセットされていない場合、新しいセッションIDを生成
            if (!isset($_GET['page'])) {
                $session_pdf_access = md5(uniqid(rand(), true));
                $_SESSION['session_pdf_access'] = $session_pdf_access;
            } else {
                $session_pdf_access = $_SESSION['session_pdf_access'];
            }

            if (empty($user_id)) {
                $stmt = $db->prepare("INSERT INTO user_pdf_access (pdf_version_id, cid, page, session_id, token) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$pdf_versions_data['pdf_version_id'], $client_id, $r_page, $session_pdf_access, $token]);
            } else {
                $stmt = $db->prepare("INSERT INTO user_pdf_access (pdf_version_id, user_id, cid, page, session_id, token) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$pdf_versions_data['pdf_version_id'], $user_id, $client_id, $r_page, $session_pdf_access, $token]);
            }

            $inserted_id = $db->lastInsertId();
        } catch (PDOException $e) {
            echo '接続失敗' . $e->getMessage();
            exit();
        }
    }

    //アクセス数のカウント
    try {
        $db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $stmt = $db->prepare("SELECT * FROM user_pdf_access WHERE user_id=? AND pdf_version_id=? AND page=0");
        $stmt->execute([$user_id, $pdf_versions_data['pdf_version_id']]);
        $access_count = count($stmt->fetchAll());
    } catch (PDOException $e) {
        echo '接続失敗' . $e->getMessage();
        exit();
    }

    $directory = "./pdf_img/";
    $image_type = ".jpeg";
    $images = glob($directory . "/" . $pdf_id . "_*.jpeg");
    if (empty($images)) {
        $image_type = ".jpg";
        $images = glob($directory . "/" . $pdf_id . "_*.jpg");
        if (empty($images)) {
            echo 'PDFが存在しません。';
            exit();
        }
    }
    foreach ($images as $image) {
        $last_img_name = basename($image);
    }
    preg_match('/_(\d+)-/', $last_img_name, $matches);

    $pdf_path = "./pdf_img/" . $pdf_id . "_" . $matches[1] . "-" . $page . $image_type;


    $first_pdf = "/pdf_img/" . $pdf_id . "_" . $matches[1] . "-" . "0" . $image_type;

    //画像の枚数をカウントし総ページ数を計算
    $page_count = 0;
    foreach ($images as $image) {
        if (strpos(basename($image), $matches[0]) !== false) {
            $page_count++;
        }
    }

    $db = null;
} else {
    echo "URLが不正です。";
    exit();
}


?>
<!DOCTYPE html>
<html lang="ja" class>

<head prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# article: http://ogp.me/ns/article#">

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel=stylesheet href="style.css" type="text/css">
    <link rel="icon" type="image/png"
        href="<?= isset($favi_logo['favi_img']) ? './favi_logos/' . $favi_logo['favi_img'] : ''; ?>">

    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="<?= $pdf_versions_data['title'] ?>">

    <meta itemprop="name" content="DocTrack">
    <meta itemprop="description"
        content="<?= !empty($pdf_versions_data['memo']) ? $pdf_versions_data['memo'] : " "; ?>">
    <meta itemprop="image" content="<?= $meta_url . $first_pdf ?>">

    <meta property="og:title" content="<?= $pdf_versions_data['title'] ?>">
    <meta property="og:type" content="website">
    <meta property="og:description"
        content="<?= !empty($pdf_versions_data['memo']) ? $pdf_versions_data['memo'] : " "; ?>">
    <meta property="og:url" content="<?= $meta_url . '/s?t=' . $_GET['t'] ?>">
    <meta property="og:site_name" content="DocTrack">
    <meta property="og:image" content="<?= $meta_url . $first_pdf ?>">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="600">
    <meta property="og:image:height" content="315">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $pdf_versions_data['title'] ?>">
    <meta name="twitter:description"
        content="<?= !empty($pdf_versions_data['memo']) ? $pdf_versions_data['memo'] : " "; ?>">
    <meta name="twitter:image" content="<?= $meta_url . $first_pdf ?>">

    <title><?= $pdf_versions_data['title'] ?></title>
</head>

<body id="show-pdf">
    <div class="prev-btn" id="prev-btn"></div>
    <div class="next-btn" id="next-btn"></div>
    <div class="show_pdf">
        <div class="image-box">
            <div class="mainImage-title">
                <img id="mainImage" src="<?= $pdf_path ?>">
            </div>
            <script>
                var pdf_id = "<?php echo $pdf_id; ?>";
                var matches_1 = "<?php echo $matches[1]; ?>";
                var page = <?php echo $page; ?>;
                var total_pages = <?php echo $page_count; ?>; // ここに合計ページ数を設定します

                var mainImage = document.getElementById("mainImage");
                mainImage.addEventListener("touchstart", startTouch, false);
                mainImage.addEventListener("touchmove", moveTouch, false);

                var initialX = null;

                // スワイプ開始地点を取得
                function startTouch(e) {
                    console.log('start');
                    initialX = e.touches[0].clientX;
                }

                // スワイプの方向を取得
                function moveTouch(e) {
                    if (initialX === null) {
                        return;
                    }

                    var diffX = initialX - e.touches[0].clientX;

                    // スワイプ方向が十分大きい場合に、画像を切り替え
                    if (Math.abs(diffX) > 11) {
                        if (diffX > 0 && page < total_pages - 1) {
                            // 左にスワイプした場合の処理
                            page++;
                        }
                        if (diffX < 0 && page > 0) {
                            // 右にスワイプした場合の処理
                            page--;
                        }

                        // 画像ソースを更新
                        mainImage.src = "./pdf_img/" + pdf_id + "_" + matches_1 + "-" + page + "<?= $image_type; ?>";

                        // ページURLを更新
                        window.history.pushState("", "", "?t=<?= $decode_data; ?>&page=" + page);
                    }

                    initialX = null; // スワイプ距離をリセット
                }
            </script>

        </div>
        <div class="button-box">
            <div class="pagination-table">
                <?php
                function createButton($direction, $disabled, $token, $page)
                {
                    return '<div>
                            <form action="" id="page-change" method="get">
                                <input type="hidden" name="t" value="' . $token . '">
                                <input type="hidden" name="page" value="' . $page . '">
                                <button class="btn btn-secondary" ' . ($disabled ? 'disabled' : '') . '>' . $direction . '</button>
                            </form>
                        </div>';
                }

                function isMobileDevice()
                {
                    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
                }

                function encrypt($data, $key)
                {
                    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
                    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
                    return base64_encode($iv . $encrypted);
                }
                $encryptionKey = "your_secret_key";

                $encryptedId = encrypt($client_id, $encryptionKey);

                $token = $_GET['t'];
                $isMobile = isMobileDevice();

                if ($isMobile) {
                    echo createButton('←', $page == 0, $token, max(0, $page - 1));
                    echo createButton('→', $page + 1 == $page_count, $token, min($page + 1, $page_count - 1));
                    echo "<script>
                                  document.addEventListener('DOMContentLoaded', function() {
                                      var element = document.getElementById('res_now_page');
                                      if (element) {
                                          element.innerHTML = '" . ($page + 1) . " / " . $page_count . "';
                                      }
                                  });
                              </script>";
                } else {
                    echo createButton('<', $page == 0, $token, 0);
                    echo createButton('←', $page == 0, $token, max(0, $page - 1));
                    echo '<div><p class="now_page">' . ($page + 1) . ' / ' . $page_count . '</p></div>';
                    echo createButton('→', $page + 1 == $page_count, $token, min($page + 1, $page_count - 1));
                    echo createButton('>', $page + 1 == $page_count, $token, $page_count - 1);
                }


                if (!$isMobile && $ClientData['license_display'] == "ON") {
                    echo '<div id="license_msg">
                            <a href="https://in.doc1.jp/signup_1.php?ref=' . $encryptedId . '" target="_blank"> 
                                <p>この資料閲覧機能は無料ツールで作成されてます。<span class="cta">無料作成</span><p>
                            </a>
                        </div>';
                }

                ?>
            </div>
        </div>

        <?php
        if ($isMobile && $ClientData['license_display'] == "ON") {
            echo '<div id="license_msg">
            <a href="https://in.doc1.jp/signup_1.php?ref=' . $encryptedId . '" target="_blank" style="display: block; width: 100%;">
                
                <p>この資料閲覧機能は無料ツールで作成されてます。<span class="cta">無料作成</span><p>
            </a>
        </div>';
        }
        ?>

        <script>

            var durationTime = 0;
            function timestore() {
                durationTime++;
                if ('<?php echo $user_id; ?>' !== '') {
                    var xhr = new XMLHttpRequest();
                    xhr.open("POST", "./back/store_time.php", true);
                    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                    xhr.send("timeSpent=" + durationTime + "&inserted_id=" + <?php echo $inserted_id; ?>);
                }
            }

            setInterval(timestore, 1000);

            // ページが読み込まれたときにlocalStorageからポップアップ表示フラグを取得
            function getPopupDisplayedFlag(popup_id) {
                return localStorage.getItem("popup_displayed_" + popup_id) === "true";
            }

            // ポップアップ表示フラグを設定する
            function setPopupDisplayedFlag(popup_id, value) {
                localStorage.setItem("popup_displayed_" + popup_id, value ? "true" : "false");
            }

            // $_GET["page"]が空の場合、ポップアップ表示フラグをリセット
            var currentPage = '<?php echo isset($_GET["page"]) ? $_GET["page"] : ""; ?>';
            if (currentPage === "") {
                <?php foreach ($popups as $popup_id => $popup_data): ?>
                    setPopupDisplayedFlag(<?php echo $popup_id; ?>, false);
                <?php endforeach; ?>
            }
            currentPage = parseInt(currentPage);

            // 関係のあるストレージの中身だけ表示
            console.log("Related Local Storage:");
            <?php foreach ($popups as $popup_id => $popup_data): ?>
                // console.log(localStorage);
                console.log("popup_displayed_" + <?php echo $popup_id; ?> + ":", localStorage.getItem("popup_displayed_" + <?php echo $popup_id; ?>));
            <?php endforeach; ?>

            // POPUP
            <?php foreach ($popups as $popup_id => $popup_data):

                $popup_data_with_id = $popup_data;
                $popup_data_with_id['id'] = $popup_id;

                $popup_displayed_key = "popup_displayed_" . $popup_id;
                ?>
                if ('<?php echo $popup_data['switch']; ?>' == 'ON' && !getPopupDisplayedFlag(<?php echo $popup_id; ?>)) {

                    var trigger_parameter = <?php echo $popup_data['trigger_parameter']; ?>;
                    var trigger_parameter2 = <?php echo $popup_data['trigger_parameter2']; ?>;

                    var stay_time_trigger = '<?php echo $popup_data['popup_trigger']; ?>' == '閲覧時間';
                    var page_number_trigger = '<?php echo $popup_data['popup_trigger']; ?>' == 'ページ数';
                    var access_count_trigger = '<?php echo $popup_data['popup_trigger']; ?>' == 'アクセス数';

                    if (stay_time_trigger) {
                        setTimeout(function () {
                            displayPopup(<?php echo json_encode($popup_data_with_id); ?>);
                        }, trigger_parameter * 1000);
                    }

                    if (page_number_trigger && trigger_parameter == currentPage + 1) {
                        setTimeout(function () {
                            displayPopup(<?php echo json_encode($popup_data_with_id); ?>);
                        }, trigger_parameter2 * 1000);
                    }

                    if (access_count_trigger && trigger_parameter == <?php echo $access_count; ?>) {
                        setTimeout(function () {
                            displayPopup(<?php echo json_encode($popup_data_with_id); ?>);
                        }, trigger_parameter2 * 1000);
                    }
                } <?php endforeach; ?>

            function displayPopup(popup_data) {
                var popup_id = popup_data.id;
                var url = decodeURIComponent(popup_data.url);

                document.body.style.backgroundColor = "rgba(0, 0, 0, 0.5)";

                var popup_container = document.createElement("div");

                popup_container.style.position = "fixed";
                popup_container.style.top = "50%";
                popup_container.style.left = "50%";
                popup_container.style.transform = "translate(-50%, -50%)";
                popup_container.style.zIndex = "9999";

                popup_container.style.width = window.innerWidth <= 768 ? '60%' : '30%';
                popup_container.style.transform = window.innerWidth <= 768 ? "translate(-50%, -30%)" : "translate(-50%, -50%)";

                var popup_img = document.createElement("img");
                popup_img.src = "./popup_img/" + popup_id + ".png";
                popup_img.style.width = "100%";
                popup_img.style.height = "auto";

                var close_btn = document.createElement("button");
                close_btn.innerHTML = "×";
                close_btn.style.position = "absolute";
                close_btn.style.top = "0";
                close_btn.style.right = "0";
                close_btn.style.zIndex = "10000";

                close_btn.onclick = function () {
                    document.body.removeChild(popup_container);
                };

                popup_img.onclick = function () {
                    window.open("./back/popup_redirect.php?url=" + url + "&popup_id=" + popup_id + "&access_id=<?php echo $inserted_id; ?>&user_id=<?php echo $user_id; ?>&cid=<?php echo $client_id; ?>", '_blank');
                };

                popup_container.appendChild(popup_img);
                popup_container.appendChild(close_btn);
                document.body.appendChild(popup_container);

                // ポップアップが表示されたときにフラグを更新し、localStorageに保存する
                setPopupDisplayedFlag(popup_data.id, true);
            }

            let prev_btn = document.getElementById('prev-btn');
            let next_btn = document.getElementById('next-btn');
            var page = <?= $page ?>;
            var page_count = <?= $page_count ?>;
            var token = '<?= $token ?>';

            prev_btn.addEventListener('click', function () {
                if (page > 0) {
                    page--;
                    location.href = "show_pdf.php?t=" + token + "&page=" + page;
                } else if (page <= 0) return;
            });

            next_btn.addEventListener('click', function () {
                if (page + 1 < page_count) {
                    page++;
                    location.href = "show_pdf.php?t=" + token + "&page=" + page;
                } else if (page >= page_count) return;
            });

        </script>

</body>

</html>