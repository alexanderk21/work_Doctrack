let total_address = document.getElementById('total_address').value;
let new_user_register = document.getElementById('new_user_register');
let form = document.getElementById('new_users_form');

function insert_confirm() {
    var inputs = new_user_register.getElementsByTagName('input');
    var i = 0;
    for (var j = 0; j < inputs.length; j++) {
        var input = inputs[j];
        if (input.value != "") {
            i++;
        }
    }
    if (i > 0) {
        form.submit();
    } else {
        alert("値を入力する必要があります。");
    }
}
function pageMove() {
    if(total_address == 0){
        alert("現在存在している住所はありません。");
    }else{
        location.href = "./email_distribution.php";
    }
    
}