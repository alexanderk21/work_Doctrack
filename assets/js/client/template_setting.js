document.getElementById('code_copy').addEventListener('click', function () {
    let content = document.getElementById('new_content').value;
    if (localStorage) {
        localStorage.setItem('content', content)
    }
})

document.getElementById('back_button').addEventListener('click', function () {
    if (localStorage) {
        var storage_content = localStorage.getItem('content')
        if (storage_content != 'undefined' || storage_content != 'null') {
            document.getElementById('new_content').value = storage_content;
        }
    }
})

document.getElementById('cancel_button').addEventListener('click', function () {
    if (localStorage) {
        var storage_content = localStorage.getItem('content')
        if (storage_content != 'undefined' || storage_content != 'null') {
            localStorage.removeItem('content');
        }
    }
})

function newSave() {
    var form = document.getElementById("new_form");
    var division = document.getElementById("new_division").value;
    // var email = document.getElementById("new_email_select").value;
    var subject = document.getElementById("new_subject").value;
    var limit = document.getElementById("limit").value;
    localStorage.removeItem('content');
    if (division == "フォーム営業" || division == "SNS") {
        if (subject == "") {
            document.getElementById('new_subject').focus();
        }
        if (limit == 0) {
            alert("上限数が0なので新規登録はできません。")
        }
        if (subject != "" && limit != "") {
            form.submit();
        }
    } else {
        // if (email == "") {
        //     alert("アドレス選択してください。");
        // }
        if (subject == "") {
            alert("件名を入力してください。")
            document.getElementById('new_subject').focus();
        }

        if (limit == 0) {
            alert("上限数が0なので新規登録はできません。")
        }
        if (subject != "" && limit != "") {
            form.submit();
        }
    }
}



// コードモーダル
const redirect = document.getElementById('modal_req_redi');
const pdf = document.getElementById('modal_req_pdf');
const code = document.getElementById('modal_req_code');
const cms = document.getElementById('modal_req_cms');
cms.style.display = "none";
pdf.style.display = "none";
redirect.style.display = "none";

function code_default() {
    code.style.display = "block";
    pdf.style.display = "none";
    cms.style.display = "none";
    redirect.style.display = "none";
    handleButtonClick('code_default');
}
function code_pdf() {
    cms.style.display = "none";
    code.style.display = "none";
    pdf.style.display = "block";
    redirect.style.display = "none";
    handleButtonClick('code_pdf');
}
function code_redirect() {
    cms.style.display = "none";
    code.style.display = "none";
    pdf.style.display = "none";
    redirect.style.display = "block";
    handleButtonClick('code_redirect');
}
function code_cms() {
    cms.style.display = "block";
    code.style.display = "none";
    pdf.style.display = "none";
    redirect.style.display = "none";
    handleButtonClick('code_cms');
}

function handleButtonClick(buttonId) {
    // Remove active class from all buttons
    document.querySelectorAll('.code-title-btn').forEach(function (button) {
        button.classList.remove('btn-secondary');
        button.classList.add('btn-primary');
    });

    // Add active class to the clicked button
    document.getElementById(buttonId).classList.add('btn-secondary');
}
