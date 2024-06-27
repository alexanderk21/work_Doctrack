<?php
try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare("SELECT * FROM cmss WHERE client_id =?");
    $stmt->execute([$client_id]);
    $cms_data = $stmt->fetchAll();

} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
?>

<!-- modal-body -->
<div class="modal-body" id="modal_req_cms">
    <table class="table">
        <?php if (empty($cms_data)): ?>
            <tr>
                <td colspan="3">CMSの登録がありません。</td>
            </tr>
        <?php else: ?>
            <tr>
                <th>コード</th>
                <th>タイトル</th>
                <th></th>
                <th></th>
            </tr>
            <?php foreach ($cms_data as $row): ?>
                <tr>
                    <td>{cms-<?= $row['cms_id'] ?>}</td>
                    <td>
                        <input type="text" name="insert_code_cms_<?= $i ?>" value="<?php if (isset($row['title'])) {
                              echo $row['title'];
                          } ?>" disabled>
                    </td>
                    <td>
                        <input type="hidden" value="{cms-<?= $row['cms_id'] ?>}" />
                        <button style="margin-left:20px" onclick="copyToClipboard(this)" class="btn btn-primary">コピー</button>
                    </td>
                    <td>
                        <a style="margin-left:20px;" href="./c?t=<?= $row['cms_id'] ?>"
                            target="_blank" class="btn btn-primary">プレビュー</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
    <br>
    <br>
</div>
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.0.1/css/toastr.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.0.1/js/toastr.js"></script>