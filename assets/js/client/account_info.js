let gender      = document.getElementById('gender').value;
let ageGroup    = document.getElementById('age_group').value;
let regions     = document.getElementById('regions').value;
let industry    = document.getElementById('industry').value;
let departName  = document.getElementById('depart_name').value;
let role        = document.getElementById('role').value;

let inputs = document.querySelectorAll('#modal_req input, #modal_req textarea, #modal_req select');
let editButton = document.getElementById('edit_button');
let saveButton = document.getElementById('save_button');
let closeBtn = document.getElementById('close');   

function handleClick(gender,ageGroup,staffNum, regions,industry, departName,role,problem){
    alert('情報の入力後、リスト無料取得がご利用できます。');
    
    document.getElementById("gender").value = gender;
    document.getElementById("age_group").value = ageGroup;
    document.getElementById("staff_num").value = staffNum;
    document.getElementById("regions").value = regions;
    document.getElementById("industry").value = industry;    
    document.getElementById("depart_name").value = departName;
    document.getElementById("role").value = role;

    let problems = problem.split(" ");
    let cnt = problems.length;
    for(var i = 0; i<cnt; i++){
        document.getElementById('problem'+problems[i]).checked = true; 
    }
}

function enableEditing(){
    inputs.forEach(input => {
        input.disabled = false;
    });
    editButton.style.display = 'none';
    saveButton.style.display = "inline-block";
}

closeBtn.addEventListener('click', function(){
    inputs.forEach(input => {
        input.disabled = true;
    });
    saveButton.style.display = "none";
    editButton.style.display = "block";  
});

function cancelEditing(){
    inputs.forEach(input => {
        input.disabled = true;
    });
    saveButton.style.display = "none";
    editButton.style.display = "block";  
}





