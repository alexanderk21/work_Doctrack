<style>
    nav {
        width: 240px;
        padding: 32px 33px;
    }

    .container {
        max-width: 100%;
        padding-right: 0;
        padding-left: 0;
    }

    .container img {
        width: 180px;
        margin: 50px auto;
    }

    .navbar-nav .nav-link {
        padding-right: 0;
        padding-left: 0;
    }
</style>

<nav class="navbar-dark bg-dark">
    <div class="container menu">
        <ul class="navbar-nav">
            <li class="nav-item"><a class="nav-link" href="clients_list.php">
                    <i class="fa fa-users"></i> 顧客一覧</a></li>
            <li class="nav-item"><a class="nav-link" href="access_analysis.php">
                    <i class="fa fa-line-chart"></i> アクセス解析</a></li>
            <li class="nav-item"><a class="nav-link" href="email_distribution.php">
                    <i class="fa fa-pencil-square-o"></i> メール配信</a></li>
            <li class="nav-item"><a class="nav-link" href="form_submission.php">
                    <i class="fa fa-envelope"></i> フォーム営業</a></li>
            <li class="nav-item"><a class="nav-link" href="stage.php">
                    <i class="fa fa-tasks"></i> スコアリング</a></li>
            <li class="nav-item"><a class="nav-link" href="pdf.php">
                    <i class="fa fa-cogs"></i> トラッキング設定</a></li>
            <li class="nav-item"><a class="nav-link" href="template_setting.php">
                    <i class="fa fa-file-text-o"></i> テンプレート設定</a></li>
            <?php if (!in_array('ユーザー管理', $func_limit)): ?>
                <li class="nav-item"><a class="nav-link" href="users.php">
                        <i class="fa  fa-male" aria-hidden="true"></i> ユーザー管理</a></li>
            <?php endif; ?>
            <?php if (!in_array('各種設定', $func_limit)): ?>
                <li class="nav-item"><a class="nav-link" href="settings.php">
                        <i class="fa fa-wrench"></i> 各種設定</a></li>
            <?php endif; ?>
            <?php if (!in_array('アフィリエイト', $func_limit)): ?>
                <li class="nav-item"><a class="nav-link" href="affiliate.php">
                        <i class="fa fa-user-plus"></i> アフィリエイト</a></li>
            <?php endif; ?>
            <?php if (!in_array('契約情報', $func_limit)): ?>
                <li class="nav-item"><a class="nav-link" href="account_info.php">
                        <i class="fa fa-info-circle"></i> 契約情報</a></li>
            <?php endif; ?>
            <!-- <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="trackingDropdown" role="button"
                    data-bs-toggle="dropdown" aria-expanded="false"><i class="fa fa-cogs"></i> トラッキング設定</a>
                <ul class="dropdown-menu" aria-labelledby="trackingDropdown">
                    <li><a class="dropdown-item" href="pdf.php">PDFファイル</a></li>
                    <li><a class="dropdown-item" href="redirect.php">リダイレクト</a></li>
                    <li><a class="dropdown-item" href="popup.php">ポップアップ</a></li>
                </ul>
            </li> -->
            <!-- <li class="nav-item"><a class="nav-link" href="list_store.php"><i class="fa fa-database"></i> リストストア</a></li> -->
        </ul>
        <?php
        try {
            $db = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $stmt = $db->prepare("SELECT * FROM ad_common_setting");
            $stmt->execute();
            $ad_common = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT * FROM ad_setting WHERE cid=?");
            $stmt->execute([$client_id]);
            $ref_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $ref_id = $ClientData['ref'];
            if ($ref_data && $ClientData['ad_display'] === 'ON') {
                echo '<a href="back/affiliate_ad_redirect.php?ref=' . $ref_data['cid'] . '&cid=' . $client_id . '&url=' . $ref_data['url'] . '" target="_blank"><img src="./img_ad_setting/' . $ref_data['cid'] . '.png" ></a>';
            } else if (!$ref_data && $ClientData['ad_display'] === 'ON' && $ad_common) {
                echo '<a href="back/affiliate_ad_redirect.php?ref=' . $ref_id . '&cid=' . $client_id . '&url=' . $ad_common['url'] . '" target="_blank"><img src="https://mgt-sys.doc1.jp/img_ad_setting/common.png" ></a>';
            }
        } catch (PDOException $e) {
            echo '接続失敗' . $e->getMessage();
            exit();
        }
        ?>
    </div>
</nav>

<script>
    $(document).ready(function () {
        $('.dropdown-toggle').click(function () {
            $('.dropdown-menu').not($(this).next('.dropdown-menu')).hide();

            $(this).next('.dropdown-menu').toggle();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.dropdown-toggle').length) {
                $('.dropdown-menu').hide();
            }
        });
    });
</script>