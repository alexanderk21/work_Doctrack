<div class="modal fade" id="limitModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" id="modal_content">
            <div class="modal-header">
                <h5 class="modal-title">上限到達</h5>
                <button type="button" id="close" class="btn-close" data-bs-dismiss="modal" aria-bs-label="Close">
                </button>
            </div>
            <!-- modal-body -->
            <div class="modal-body" id="modal_req">
                現在の登録数：<?= $current;?>　　登録できる上限数：<?= $max;?> <br>
                登録できる上限に達しました。<br>
                プランをアップグレードして上限数を増やしましょう！<br>
            </div>
            <div class="modal-footer">
                <a href="./account_info.php?action=plan" class="btn btn-primary">はい</a>
                <button type="button" class="btn btn-secondary ms-2" data-bs-dismiss="modal">いいえ</button>
            </div>
        </div>
    </div>
</div>