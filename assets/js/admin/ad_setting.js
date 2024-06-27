let form       = document.getElementById('myform');
let save_btn   = document.getElementById('save_btn');
let cancel_btn = document.getElementById('cancel_btn');
let edit_btn   = document.getElementById('edit_btn');

let img_link   = document.getElementById('img_link');



let fileSelect = document.getElementById("fileSelect");
let fileElem   = document.getElementById("file");

let url             = document.getElementById('url');
var fileInput       = document.getElementById('file');
var fileNameDisplay = document.getElementById('file-name');


fileSelect.addEventListener("click", (e) => {
    if (fileElem) {
        fileElem.click();
    }
}, false);

function displayFileName() {
    var fileInput = document.getElementById('file');
    var fileNameDisplay = document.getElementById('file-name');
    if (fileInput.files.length > 0) {
        fileNameDisplay.textContent = fileInput.files[0].name;
        img_link.style.display = "none";
    } else {
        fileNameDisplay.textContent = ' ファイルが選択されていません';
    }
}



save_btn.addEventListener('click', () => {
    if( url.value != "" || fileInput.value != ""){
        form.submit();
    }else{
        alert("広告画像とURLを入力してください！");    
    }
});



function enableEditing(){
    url.disabled = false;
    save_btn.style.display = "inline-block";
    cancel_btn.style.display = "inline-block";
    fileSelect.disabled = false;
    edit_btn.style.display = "none";
}

function cancel() {
    location.reload();
}


