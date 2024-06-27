const type       = document.getElementById('type').value;
const max_value  = document.getElementById('max_value').value;
const used_value = document.getElementById('used_value').value;





const modal_button = document.getElementById('modal_button');
modal_button.addEventListener('click', function(){
    let back_button = document.getElementById('back_button');
    back_button.setAttribute('data-bs-target', '#new');
})
if(type == "1"){    
    if(parseInt(used_value) >= parseInt(max_value)){
        modal_button.setAttribute('data-target', '#restrict_modal');
    }
}

