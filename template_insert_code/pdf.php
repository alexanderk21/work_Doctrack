<?php
try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $db->prepare("SELECT * FROM pdf_versions WHERE deleted = 0 AND cid =? AND (pdf_id, uploaded_at) IN (SELECT pdf_id, MAX(uploaded_at) FROM pdf_versions WHERE cid=? GROUP BY pdf_id)");
    $stmt->execute([$client_id, $client_id]);
    $pdf_version_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($pdf_version_ids) {
        $unique_pdf_version_ids = array_unique($pdf_version_ids);

        $pdf_placeholders = implode(',', array_fill(0, count($unique_pdf_version_ids), '?'));
        $pdf_sql = "SELECT DISTINCT pdf_id, title, pdf_version_id
                    FROM pdf_versions
                    WHERE pdf_version_id IN ($pdf_placeholders)";
        $pdf_stmt = $db->prepare($pdf_sql);
        $pdf_stmt->execute(array_values($unique_pdf_version_ids));
        $pdf_data = $pdf_stmt->fetchAll(PDO::FETCH_ASSOC);
    }


} catch (PDOException $e) {
    echo '接続失敗' . $e->getMessage();
    exit();
}
?>

<!-- modal-body -->
<div class="modal-body" id="modal_req_pdf">
    <table class="table">
        <?php if (empty($pdf_data)): ?>
            <tr>
                <td colspan="3">PDFファイルの登録がありません。</td>
            </tr>
        <?php else: ?>
            <tr>
                <th>コード</th>
                <th>タイトル</th>
                <th></th>
                <th></th>
            </tr>
            <?php foreach ($pdf_data as $i => $row): ?>
                <tr>
                    <td>{pdf-<?= $row['pdf_id'] ?>}</td>
                    <td>
                        <input type="text" name="insert_code_pdf_<?= $i ?>" value="<?php if (isset($row['title'])) {
                              echo $row['title'];
                          } ?>" disabled>
                    </td>
                    <td>
                        <input type="hidden" value="{pdf-<?= $row['pdf_id'] ?>}" />
                        <button style="margin-left:20px" onclick="copyToClipboard(this)" class="btn btn-primary">コピー</button>
                    </td>
                    <td>
                        <a style="margin-left:20px;" href="./pdf/<?= $row['pdf_id'] ?>_<?= $row['pdf_version_id'] ?>.pdf"
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