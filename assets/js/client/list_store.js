const large_categories = document.querySelector('select[name="large_cts"]');
const small_categories = document.querySelector('select[name="small_cts"]');
const d_regions          = document.querySelector('select[name="d_regions"]');
const freeButton       = document.querySelector('button[name="freeButton"]');



$(document).ready(function() {
    $('select[name="large_cts"]').change(function(e){
        
        if(large_categories.value != "未選択"){
            e.preventDefault();
            $.ajax({
                url: "../../../back/categories.php",
                method: "POST",
                // dataType:"json",
                data: {
                    large_category : large_categories.value
                },
                success: function(response) {
                  // Handle the response from the PHP file
                small_categories.innerHTML = "<option value='未選択'>未選択</option>";
                for(var i=0; i<response.length; i++){
                    small_categories.innerHTML += "<option value='"+response[i].title+"'>"+response[i].title+"</option>" 
                }
                  
                },
                error: function(xhr, status, error) {
                  // Handle any errors
                  console.error(error);
                }
            });
            
            small_categories.disabled = false;
        }else{
            small_categories.disabled = true;
            small_categories.innerHTML = "<option value='未選択'>未選択</option>" 
        }        
    });
    $('select[name="small_cts"]').change(function(){
        if(small_categories.value!="未選択"){
            d_regions.disabled = false;
            
        }else{
            d_regions.disabled = true;
        }
    }); 
    $('select[name="d_regions"]').change(function(){
        let hereBtn = document.getElementById('here_button').value;
        if(d_regions.value!="未選択" && hereBtn =="true"){
            freeButton.disabled = false;
            freeButton.className = "btn btn-primary";
        }else if(d_regions.value=="未選択"){
            freeButton.disabled = true;
            freeButton.className = "btn btn-secondary";
        }
    });
    $('#freeButton').click(function(){
        var num = $('#search_num').val();
        if(num >= 3){
            alert("リストの無料取得ができるのは1日3回までとなります。");
            location.reload();
        }else{
            $('#listData').submit();
        }
    });
  });
