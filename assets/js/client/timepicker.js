
let date = new Date();

let hour = date.getHours();
let minutes = date.getMinutes();
let hourSelect = document.getElementById("hour");
let minuteSelect = document.getElementById("minute");
let dates = document.getElementById('date');

let flag = 0;
function timepicker(){    
    var step = 5;    
    hourSelect.innerHTML="<option value=''> </option>";
    minuteSelect.innerHTML="<option value=''> </option>";
    for(var i=0 ; i<=23 ; i++){
        if(i < 10){
            var j = '0'+i;
            hourSelect.innerHTML +="<option value='"+j+"'>"+j+"</option>";    
        }else{
            hourSelect.innerHTML +="<option value='"+i+"'>"+i+"</option>";    
        }
    }   
    for(var i=0 ; i<=11 ; i++){
        var minute = step * i;
        if(minute < 10){
            var m = '0' + minute;
            minuteSelect.innerHTML += "<option value='"+m+"'>"+m+"</option>";
        }else{
            minuteSelect.innerHTML += "<option value='"+minute+"'>"+minute+"</option>";
        }
    }  
}
if(dates){
    dates.addEventListener('change', function(){
        flag += 1;
    });
}

hourSelect.addEventListener('change', function(){
    flag += 1;
});

if(minuteSelect){
    minuteSelect.addEventListener('change', function(){
        
        if(flag >= 2 && minuteSelect.value != "" && hourSelect.value != ""){
            document.querySelector('button[type="submit"]').disabled=false;
        }else{
            document.querySelector('button[type="submit"]').disabled=true;
        }
    })
}

