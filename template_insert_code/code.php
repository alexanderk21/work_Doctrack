<!-- modal-body -->
<div class="modal-body" id="modal_req_code">
    <table class="table">
        <tr>
            <th>コード</th>
            <th>項目名</th>
            <th></th>
        </tr>
        <tr>
            <td>{company}</td>
            <td>企業名</td>
            <td>
                <input type="hidden" value="{company}" />
                <button style="margin-left:250px" onclick="copyToClipboard(this)" class="btn btn-primary">コピー</button>
            </td>
            </td>
        </tr>
        <tr>
            <td>{lastname}</td>
            <td>姓</td>
            <td>
                <input type="hidden" value="{lastname}" />
                <button style="margin-left:250px" onclick="copyToClipboard(this)" class="btn btn-primary">コピー</button>
            </td>
            </td>
        </tr>
        <tr>
            <td>{firstname}</td>
            <td>名</td>
            <td>
                <input type="hidden" value="{firstname}" />
                <button style="margin-left:250px" onclick="copyToClipboard(this)" class="btn btn-primary">コピー</button>
            </td>
            </td>
        </tr>
        <tr>
            <td>{fromname}</td>
            <td>差出名</td>
            <td>
                <input type="hidden" value="{fromname}" />
                <button style="margin-left:250px" onclick="copyToClipboard(this)" class="btn btn-primary">コピー</button>
            </td>
            </td>
        </tr>
    </table>
    <br>
    <br>
</div>
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.0.1/css/toastr.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.0.1/js/toastr.js"></script>