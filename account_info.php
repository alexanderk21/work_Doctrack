<?php
session_start();
require ('./common.php');
if (isset($_SESSION['csv_data']))
    unset($_SESSION["csv_data"]);
if (isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);
if (isset($_SESSION['email_data']))
    unset($_SESSION['email_data']);

try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = "SELECT count(*) FROM clients WHERE cid=:cid";
    $stmt = $db->prepare($sql);
    $stmt->execute([':cid' => $client_id]);
    $user_cnt = $stmt->fetchColumn();

    $sql = "SELECT count(*) FROM froms WHERE cid=:cid";
    $stmt = $db->prepare($sql);
    $stmt->execute([':cid' => $client_id]);
    $address_cnt = $stmt->fetchColumn();

    $sql = "SELECT count(*) FROM templates WHERE cid=:cid";
    $stmt = $db->prepare($sql);
    $stmt->execute([':cid' => $client_id]);
    $temp_cnt = $stmt->fetchColumn();

    $sql = "SELECT count(*) FROM pdf_versions WHERE cid=:cid";
    $stmt = $db->prepare($sql);
    $stmt->execute([':cid' => $client_id]);
    $pdf_cnt = $stmt->fetchColumn();

    $sql = "SELECT count(*) FROM redirects WHERE cid=:cid";
    $stmt = $db->prepare($sql);
    $stmt->execute([':cid' => $client_id]);
    $redl_cnt = $stmt->fetchColumn();

    $sql = "SELECT count(*) FROM popups WHERE cid=:cid";
    $stmt = $db->prepare($sql);
    $stmt->execute([':cid' => $client_id]);
    $popup_cnt = $stmt->fetchColumn();

    $sql = "SELECT count(*) FROM cmss WHERE client_id=:client_id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':client_id' => $client_id]);
    $cms_cnt = $stmt->fetchColumn();

    $sql = "SELECT count(*) FROM stages WHERE client_id=:cid";
    $stmt = $db->prepare($sql);
    $stmt->execute([':cid' => $client_id]);
    $stage_cnt = $stmt->fetchColumn();

    $sql = "SELECT count(*) FROM tag_sample WHERE client_id=:cid";
    $stmt = $db->prepare($sql);
    $stmt->execute([':cid' => $client_id]);
    $tag_cnt = $stmt->fetchColumn();

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
?>

<?php require ('header.php'); ?>
<style>
    .modal-dialog {
        width: 440px;
    }

    .planShow td,
    .planShow th {
        text-align: center;
        vertical-align: middle;
        border: 1px solid;
        border-color: inherit;
    }
</style>

<body>

    <div class="wrapper account-info">
        <?php require ('menu.php'); ?>
        <main>
            <div class="d-flex justify-content-between">
                <div class="account_title">
                    <h1>契約情報</h1>
                </div>
                <?php require ('dropdown.php'); ?>
            </div>

            <div class="modal fade" id="idChModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">契約ID変更</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form action="" method="post" onsubmit="return confirmSubmission()">
                            <div class="modal-body d-flex justify-content-center flex-column" id="modal_req">
                                <input type="text" class="w-75 mx-auto" id="newId" name="new_cid">
                                <p id="possible" class="text-center d-none"><span
                                        class="text-success">◯</span>このIDは利用可能です。</p>
                                <p id="impossible" class="text-center d-none"><span
                                        class="text-danger">×</span>このIDは利用できません。</p>
                            </div>
                            <div class="modal-footer">
                                <input type="hidden" name="userid" value='<?= $client_id ?>'>
                                <button type="button" data-dismiss="modal" data-toggle="modal"
                                    data-target="#confirmModal" id="saveBtn" class="btn btn-success" disabled
                                    <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel"
                aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="confirmModalLabel">契約ID変更</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="d-flex justify-content-center flex-column">
                                <p class="text-center">https:// <span id="old_subdomain"></span> .doc1.jp/▲▲▲/●●●/■■■
                                </p>
                                <div class="d-flex justify-content-center mb-2">
                                    <img src="./img/sort_desc.png" alt="">
                                </div>
                                <p class="text-center">https:// <span id="new_subdomain"></span> .doc1.jp/▲▲▲/●●●/■■■
                                </p>
                                <p class="text-center">過去に作成したトラッキングURLにアクセスできなくなります。</p>
                                <p class="text-center">よろしいでしょうか？</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <form action="./back/ch_cid.php" method="post">
                                <input type="hidden" class="w-75" id="new_cid" name="new_cid">
                                <input type="hidden" class="w-75" id="oldId" name="old_cid"
                                    value="<?php echo $client_id; ?>">
                                <input type="hidden" class="w-75" id="userId" name="user_id"
                                    value="<?php echo $ClientData['id']; ?>">
                                <button type="submit" class="btn btn-primary">はい</button>
                            </form>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">いいえ</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-2">
                <button class="switch_btns btn btn-primary" data-target="accountShow"
                    id="switch_account">契約ユーザー</button>
                <button class="switch_btns btn btn-primary ms-2" data-target="limitShow"
                    id="switch_limit">機能上限数</button>
                <button class="switch_btns btn btn-primary ms-2" data-target="planShow" id="switch_plan">契約プラン</button>
            </div>

            <table class="table accountShow">
                <tr>
                    <td>契約ID</td>
                    <td>
                        <?= $client_id ?>
                        <?php if ($client_id == $ClientData['id']): ?>
                            <button class="btn btn-dark ms-2" data-toggle="modal" data-target="#idChModal" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>契約プラン</td>
                    <td>
                        <?= $ClientData['status'] ?>
                    </td>
                </tr>
                <tr>
                    <td>利用開始日</td>
                    <td><?= substr($ClientData['created_at'], 0, 10) ?></td>
                </tr>
                <tr>
                    <td>契約メールアドレス</td>
                    <td><?= $ClientData['email'] ?></td>
                </tr>
                <tr>
                    <td>連絡先電話番号</td>
                    <td><?= $ClientData['tel'] ?></td>
                </tr>
                <tr>
                    <td>企業名・屋号名</td>
                    <td><?= $ClientData['name'] ?></td>
                </tr>
                <tr>
                    <td>契約者名</td>
                    <td><?= $ClientData['last_name'] . $ClientData['first_name'] ?></td>
                </tr>
            </table>

            <table class="table limitShow">
                <tr>
                    <th></th>
                    <th>登録数</th>
                    <th>上限数</th>
                </tr>
                <tr>
                    <?php
                    if ($ClientData['max_froms_type'] == 1) {
                        $max_froms = $ClientData['max_froms'];
                    } else {
                        $max_froms = '無制限';
                    }
                    ?>
                    <td class="d-flex justify-content-between">
                        追加アドレス
                        <?php
                        if ($max_froms != '無制限' && $max_froms < ($address_cnt - $user_cnt)) {
                            echo '<p class="text-danger mb-0 text-end">登録上限に達しました</p>';
                        }
                        ?>
                    </td>
                    <td><?= $address_cnt - $user_cnt; ?></td>
                    <td><?= $max_froms ?>
                    </td>
                </tr>
                <tr>
                    <?php
                    if ($ClientData['max_temp_type'] == 1) {
                        $max_temp = $ClientData['max_temp'];
                    } else {
                        $max_temp = '無制限';
                    }
                    ?>
                    <td class="d-flex justify-content-between">
                        テンプレート
                        <?php
                        if ($max_temp != '無制限' && $max_temp < $temp_cnt) {
                            echo '<p class="text-danger mb-0 text-end">登録上限に達しました</p>';
                        }
                        ?>
                    </td>
                    <td>
                        <?= $temp_cnt; ?>
                    </td>
                    <td>
                        <?= $max_temp ?>
                    </td>
                </tr>
                <tr>
                    <?php
                    if ($ClientData['max_pdf_type'] == 1) {
                        $max_pdf = $ClientData['max_pdf'];
                    } else {
                        $max_pdf = '無制限';
                    }
                    ?>
                    <td class="d-flex justify-content-between">
                        PDFファイル
                        <?php
                        if ($max_pdf != '無制限' && $max_pdf <= $pdf_cnt) {
                            echo '<p class="text-danger mb-0 text-end">登録上限に達しました</p>';
                        }
                        ?>
                    </td>
                    <td>
                        <?= $pdf_cnt; ?>
                    </td>
                    <td>
                        <?= $max_pdf ?>
                    </td>
                </tr>
                <tr>
                    <?php
                    if ($ClientData['max_redl_type'] == 1) {
                        $max_redl = $ClientData['max_redl'];
                    } else {
                        $max_redl = '無制限';
                    }
                    ?>
                    <td class="d-flex justify-content-between">
                        リダイレクト
                        <?php
                        if ($max_redl != '無制限' && $max_redl <= $redl_cnt) {
                            echo '<p class="text-danger mb-0 text-end">登録上限に達しました</p>';
                        }
                        ?>
                    </td>
                    <td>
                        <?= $redl_cnt; ?>
                    </td>
                    <td>
                        <?= $max_redl ?>
                    </td>
                </tr>
                <tr>
                    <?php
                    if ($ClientData['max_pops_type'] == 1) {
                        $max_pops = $ClientData['max_pops'];
                    } else {
                        $max_pops = '無制限';
                    }
                    ?>
                    <td class="d-flex justify-content-between">
                        ポップアップ
                        <?php
                        if ($max_pops != '無制限' && $max_pops <= $popup_cnt) {
                            echo '<p class="text-danger mb-0 text-end">登録上限に達しました</p>';
                        }
                        ?>
                    </td>
                    <td>
                        <?= $popup_cnt; ?>
                    </td>
                    <td>
                        <?= $max_pops ?>
                    </td>
                </tr>
                <tr>
                    <?php
                    if ($ClientData['max_cms_type'] == 1) {
                        $max_cms = $ClientData['max_cms'];
                    } elseif ($ClientData['status'] == 'Free') {
                        $max_cms = 1;
                    } else {
                        $max_cms = "無制限";
                    }
                    ?>
                    <td class="d-flex justify-content-between">
                        CMS
                        <?php
                        if ($max_cms != '無制限' && $max_cms <= $cms_cnt) {
                            echo '<p class="text-danger mb-0 text-end">登録上限に達しました</p>';
                        }
                        ?>
                    </td>
                    <td>
                        <?= $cms_cnt; ?>
                    </td>
                    <td>
                        <?= $max_cms ?>
                    </td>
                </tr>
                <tr>
                    <?php
                    if ($ClientData['max_stage_type'] == 1) {
                        $max_stage = $ClientData['max_stage'];
                    } elseif ($ClientData['status'] == 'Free') {
                        $max_stage = 1;
                    } else {
                        $max_stage = "無制限";
                    }
                    ?>
                    <td class="d-flex justify-content-between">
                        ステージ
                        <?php
                        if ($max_stage != '無制限' && $max_stage <= $stage_cnt) {
                            echo '<p class="text-danger mb-0 text-end">登録上限に達しました</p>';
                        }
                        ?>
                    </td>
                    <td>
                        <?= $stage_cnt; ?>
                    </td>
                    <td>
                        <?= $max_stage ?>
                    </td>
                </tr>
                <tr>
                    <?php
                    if ($ClientData['max_tag_type'] == 1) {
                        $max_tag = $ClientData['max_tag'];
                    } elseif ($ClientData['status'] == 'Free') {
                        $max_tag = 1;
                    } else {
                        $max_tag = "無制限";
                    }
                    ?>
                    <td class="d-flex justify-content-between">
                        タグ
                        <?php
                        if ($max_tag != '無制限' && $max_tag <= $tag_cnt) {
                            echo '<p class="text-danger mb-0 text-end">登録上限に達しました</p>';
                        }
                        ?>
                    </td>
                    <td>
                        <?= $tag_cnt; ?>
                    </td>
                    <td>
                        <?= $max_tag ?>
                    </td>
                </tr>
                <tr>
                    <?php
                    if ($ClientData['max_user_type'] == 1) {
                        $max_user = $ClientData['max_user'];
                    } else {
                        $max_user = '無制限';
                    }
                    ?>
                    <td class="d-flex justify-content-between">
                        ユーザー
                        <?php
                        if ($max_user != '無制限' && $max_user <= $user_cnt) {
                            echo '<p class="text-danger mb-0 text-end">登録上限に達しました</p>';
                        }
                        ?>
                    </td>
                    <td>
                        <?= $user_cnt; ?>
                    </td>
                    <td>
                        <?= $max_user ?>
                    </td>
                </tr>
            </table>

            <table class="table planShow">
                <thead>
                    <tr style="height: 100px;">
                        <th rowspan="2"></th>
                        <th style="background-color: #fbe0e0; font-size: 25px;">Freeプラン</th>
                        <th style="background-color: #f5b7b7; font-size: 25px;">Starterプラン</th>
                        <th style="background-color: #df5858; font-size: 25px;">Basicプラン</th>
                        <th style="background-color: #d10000; font-size: 25px;">Proプラン</th>
                    </tr>
                    <tr>
                        <th>まずはお試して！</th>
                        <th>一人で使う！</th>
                        <th>少人数で使いたい！</th>
                        <th>大人数で使いたい！</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>初期費用</td>
                        <td colspan="4">0円</td>
                    </tr>
                    <tr>
                        <td>月額費用</td>
                        <td>0円</td>
                        <td>10,000円</td>
                        <td>30,000円</td>
                        <td>50,000円</td>
                    </tr>
                    <tr>
                        <td></td>
                        <?php if ($ClientData['status'] == 'Free'): ?>
                            <td><button class="w-75 btn btn-secondary rounded-pill">利用中</button></td>
                            <td><button class="w-75 btn btn-success rounded-pill">アップグレード</button></td>
                            <td><button class="w-75 btn btn-success rounded-pill">アップグレード</button></td>
                            <td><button class="w-75 btn btn-success rounded-pill">アップグレード</button></td>
                        <?php elseif ($ClientData['status'] == 'Starter'): ?>
                            <td><button class="w-75 btn btn-secondary rounded-pill">ダウングレード</button></td>
                            <td><button class="w-75 btn btn-secondary rounded-pill">利用中</button></td>
                            <td><button class="w-75 btn btn-success rounded-pill">アップグレード</button></td>
                            <td><button class="w-75 btn btn-success rounded-pill">アップグレード</button></td>
                        <?php elseif ($ClientData['status'] == 'Basic'): ?>
                            <td colspan="2"><button class="w-75 btn btn-secondary rounded-pill">ダウングレード</button></td>
                            <td><button class="w-75 btn btn-secondary rounded-pill">利用中</button></td>
                            <td><button class="w-75 btn btn-success rounded-pill">アップグレード</button></td>
                        <?php elseif ($ClientData['status'] == 'Pro'): ?>
                            <td colspan="3"><button class="w-75 btn btn-secondary rounded-pill">ダウングレード</button></td>
                            <td><button class="w-75 btn btn-secondary rounded-pill">利用中</button></td>
                        <?php endif; ?>
                    </tr>
                </tbody>
            </table>
            <div class="w-50 mx-auto p-5 pb-3 planShow" style="background-color: #d3d3d3;">
                <div class="d-flex align-items-center justify-content-center">
                    <i class="fa fa-exclamation-circle me-3" style="font-size: 40px;" aria-hidden="true"></i>
                    <div style="width: max-content;">
                        ・料金は税抜表示となります。 <br>
                        ・現在の利用データをそのまま引き継いでプラン移行されます。<br>
                        ・アップグレード後、利用上限数は反映までお待ちください。<br>
                        ・ダウングレードする際はお問い合わせください。
                    </div>
                </div>
                <div class="d-flex justify-content-center mt-3">
                    <a href="https://doctrack.jp/price/" class="btn btn-success" target="_blank">料金プラン</a>
                    <a href="https://doctrack.jp/terms_of_service/" class="btn btn-warning ms-2"
                        target="_blank">サービス約款</a>
                    <a href="https://doctrack.jp/privacy/" class="btn btn-info ms-2" target="_blank">プライバシーポリシー</a>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
        crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI"
        crossorigin="anonymous"></script>
    <script>
        $('.switch_btns').on('click', function () {
            var targetClass = $(this).data('target');

            $('.accountShow, .limitShow, .planShow').hide();
            $('.' + targetClass).show();

            $('.switch_btns').removeClass('btn-light');
            $(this).addClass('btn-light');
        });

        $('#switch_account').click();
        
        <?php if (isset($_GET['action']) && $_GET['action'] == 'plan'): ?>
            $('#switch_plan').click();
        <?php endif; ?>

        $('#newId').change(function () {
            var id = $(this).val();
            $.ajax({
                type: 'POST',
                url: './back/check_id.php',
                data: {
                    id: id,
                },
                success: function (response) {
                    if (response == 'yes') {
                        $('#saveBtn').prop('disabled', false);
                        $('#possible').removeClass('d-none');
                        $('#impossible').addClass('d-none');
                    } else {
                        $('#saveBtn').prop('disabled', true);
                        $('#possible').addClass('d-none');
                        $('#impossible').removeClass('d-none');
                    }
                },
                error: function (xhr, status, error) {
                    console.log('Error: ', error);
                }
            });
        })

        $('#saveBtn').click(function () {
            var new_cid = $('#newId').val();
            var old_cid = "<?php echo $client_id; ?>"
            $('#old_subdomain').text(old_cid);
            $('#new_subdomain').text(new_cid);
            $('#new_cid').val(new_cid);
        });

    </script>
</body>

</html>