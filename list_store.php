<?php
session_start();
require('common.php');
date_default_timezone_set('Asia/Tokyo');
if(isset($_SESSION['csv_data']))
    unset($_SESSION["csv_data"]);
if(isset($_SESSION['csv_data_form']))
    unset($_SESSION["csv_data_form"]);
if(isset($_SESSION['email_data']))
    unset($_SESSION['email_data']);

    $per_page = 10; 
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;  
try{
    $db= new PDO($dsn,$user,$pass,[
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);        
    
    $sql = "SELECT * FROM large_categories";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $large_categories = $stmt->fetchAll();
    
    $sql = "SELECT * FROM department";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $departs = $stmt->fetchAll();

    $sql = "SELECT * FROM industry";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $industry = $stmt->fetchAll();


    $sql = "SELECT * FROM `role`";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $roles = $stmt->fetchAll();

    $sql = "SELECT * FROM problem";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $problems = $stmt->fetchAll();

    $sql = "SELECT * FROM region";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $regions = $stmt->fetchAll();

    function fetchClientDetails($db, $client_id) {
        $sql = "SELECT * FROM clients WHERE id=:cid";
        $stmt = $db->prepare($sql);
        $stmt->execute([':cid' => $client_id]);
        return $stmt->fetch();
    }
    
    function areDetailsComplete($details) {
        $requiredFields = ['gender', 'age_group', 'staff_num', 'region', 'industry', 'depart_name', 'director', 'problem'];
    
        foreach ($requiredFields as $field) {
            if (empty($details[$field])) {
                return false;
            }
        }
        return true;
    }
    
    $details = fetchClientDetails($db, $client_id);
    $hereButton = areDetailsComplete($details) ? "true" : "false";

    
    // if($details['status'] != "無料プラン"){
    //     $cidStatus = "true";
    // }else{
    //     $cidStatus = "false";
    // }
    

    $today = date('Y-m-d');
    $sql = "SELECT * FROM store_search WHERE cid = :cid";
    $stmt = $db->prepare($sql);
    $stmt->execute([':cid'=>$client_id]);
    $total_data = $stmt->fetchAll();    

    $sql = "SELECT * FROM store_search WHERE cid = :cid ORDER BY create_at DESC LIMIT :offset, :per_page";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':cid',$client_id);
    $stmt->bindValue(':offset', ($current_page - 1) * $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $searchResult = $stmt->fetchAll();
    
}catch(PDOException $e){
        echo '接続失敗' . $e->getMessage();
        exit();
};

$searchData = "";

if(isset($_SESSION['searchData'])){
    $searchData = $_SESSION['searchData'];
}

$total_items = count($total_data);
$total_pages = ceil($total_items/$per_page);


?>

<?php require('header.php');?>

<body class="no-scroll-y">
<div class="modal fade" id="detail" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" id="modal_content">
            <div class="modal-header">
                <h5 class="modal-title">詳細情報</h5>
                <button type="button" id="close" class="btn-close" data-bs-dismiss="modal" aria-bs-label="Close">                
                </button>
            </div>
            <!-- modal-body -->
            <div class="modal-body" id="modal_req">
            <form action="./back/ch_account.php" method="post" id="myForm" onsubmit="return validateForm()">
                <table>
                    <tr>
                        <td>性別</td>
                        <td>
                            <select name="gender" id="gender" disabled required>
                                <option value="" selected disabled>未回答</option>    
                                <option value="男性">男性</option>
                                <option value="女性">女性</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>年齡層</td>
                        <td>
                            <select name="age_group" id="age_group" disabled required>
                                <option value="" selected disabled>未回答</option>
                                <option value="20">20代</option>
                                <option value="30">30代</option>
                                <option value="40">40代</option>
                                <option value="50">50代</option>
                                <option value="60">60代上</option>
                                <option value="10">10代</option>
                            </select>                                    
                        </td>
                    </tr>
                    
                    <tr>
                        <td>活勧エリア</td>
                        <td>
                            <select name="regions" id="regions" disabled required>
                                <option value="" selected disabled>未回答</option> 
                                <?php foreach($regions as $region):?>
                                    <option value="<?= $region['region_name'];?>"><?= $region['region_name'];?></option>
                                <?php endforeach;?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>業種</td>
                        <td>
                            <select name="industry" id="industry" disabled required>
                                <option value="" selected disabled>未回答</option> 
                                <?php foreach($industry as $data):?>
                                    <option value="<?= $data['industry_name'];?>"><?= $data['industry_name'];?></option>
                                <?php endforeach;?>
                            </select>
                            
                        </td>                                                    
                    </tr>
                    <tr>
                        <td>組織規模</td>
                        <td>
                            <select name="staff_num" id="staff_num" disabled required>
                                <option value="" selected disabled>未回答</option>    
                                <option value="1">2~10人</option>
                                <option value="2">11~30人</option>
                                <option value="3">31~50人</option>
                                <option value="4">51~100人</option>
                                <option value="5">101~300人</option>
                                <option value="6">301~500人</option>
                                <option value="7">501人以上</option>
                                <option value="8">1人</option>
                            </select>                                    
                        </td>
                    </tr>
                    <tr>
                        <td>部署名</td>
                        <td>
                            <select name="depart_name" id="depart_name" disabled required>
                                <option value="" selected disabled>未回答</option>    
                                <?php foreach($departs as $depart):?>
                                    <option value="<?= $depart['depart_name'];?>"><?= $depart['depart_name'];?></option>
                                <?php endforeach;?>
                            </select> 
                            
                        </td>
                    </tr>
                    <tr>
                        <td>役職</td>
                        <td>
                            <select name="role" id="role" disabled required>
                                <option value="" selected disabled>未回答</option>    
                                <?php foreach($roles as $role):?>
                                    <option value="<?= $role['role'];?>"><?= $role['role'];?></option>
                                <?php endforeach;?>
                            </select> 
                            
                        </td>
                    </tr>
                    <tr>
                        <td>解決したい課題</td>
                        <td>
                            <div class="check-col">                            
                                <?php foreach($problems as $key => $problem):?>
                                    <div class="check-row">
                                        <input type="checkbox" name="problem[]" id="problem<?= $key?>" value="<?= $key+1?>" disabled> <p class="check-title"><?= trim($problem['problem_name'])?></p>
                                    </div>                                    
                                <?php endforeach;?>
                            </div>
                        </td>
                    </tr>
                </table>
                <br>
                <br>
            </div>
            <div class="modal-footer">
                        <input type="hidden" name="cid" value="<?=$client_id?>">                        
                        <button type="button" id="edit_button" class="btn btn-dark" onclick="enableEditing()" <?= in_array('変更', $func_limit) ? 'disabled' : ''; ?>>変更</button>
                        <button type="submit" id="save_button" class="btn btn-success" style="display:none">保存</button>
                    </form>
                <button type="button" class="btn btn-secondary" onclick="cancelEditing()" data-bs-dismiss="modal">閉じる</button>
            </div>
        </div>
    </div>
</div>
    <div class="wrapper">
    <?php require('menu.php');?>
        <main>
        <div class="d-flex justify-content-between">
            <div class="page-title">
                <input type="hidden" name="problem_ctn" value="<?= count($problems)?>">
                <div class="header-with-help">
                    <h1>リストストア</h1>
                </div>
                <input type="hidden" id="here_button" value="<?= $hereButton?>">
                <p><?= isset($_SESSION['searchData'])&&$searchData=="" ? "現在、クローリング中です。しばらくお待ちください。" : ""?></p>
            </div>
                <?php require ('dropdown.php'); ?>
            </div>
            <form action="./back/list_store_search.php" method="POST" id="listData" >
                <input type="hidden" name="cid" value="<?= $client_id;?>">
                <div class="select-cates">
                    <div class="large-cates">
                        <p>大カテゴリ</p>
                        <select class="form-select" name="large_cts" aria-label="Default select example">
                            <option value="未選択" selected>未選択</option>
                            <?php foreach($large_categories as $large_category):?>
                                <option value="<?= $large_category['id']?>"><?= $large_category['title']?></option>
                            <?php endforeach;?>
                        </select>
                    </div>
                    <div class="small-cates">
                        <p>小カテゴリ</p>
                        <select class="form-select" name="small_cts" aria-label="Default select example" disabled>
                            <option value="未選択" selected>未選択</option>
                        </select>
                    </div>
                    <div class="region">
                        <p>地域</p>
                        <select class="form-select" name="d_regions" aria-label="Default select example" disabled>
                            <option selected>未選択</option>
                            <?php foreach($regions as $region):?>
                                <option value="<?= trim($region['region_name'])?>"><?= trim($region['region_name'])?></option>
                            <?php endforeach;?>
                        </select>
                    </div>
                    <input type="hidden" id="search_num" value="<?= $searchNum;?>">
                    <div class="get-free">
                        <?php if($hereButton == "false"):?>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#detail"
            onclick="handleClick(`<?= $details['gender']; ?>`,`<?= $details['age_group']; ?>`,`<?= $details['staff_num']; ?>`,`<?= $details['region']; ?>`,`<?= $details['industry']; ?>`, `<?= $details['depart_name']; ?>`, `<?= $details['director']; ?>`, `<?= $details['problem']; ?>`)">無料取得</button>
                        <?php endif;?>
                        <?php if($hereButton == "true"):?>
                        <button type="button" name="freeButton" id="freeButton" class="btn btn-secondary" disabled>無料取得</button>
                        <?php endif;?>
                    </div>
                </div>
            </form>
            <?php
                $pagination_links = '';
                $maxVisable = 5;
                $start_page = (ceil($current_page / $maxVisable)-1)* $maxVisable+1;                
                $end_page = min($start_page + $maxVisable-1, $total_pages);
                $next = $end_page < $total_pages ? $end_page + 1 : $total_pages;
                $prev = $current_page <= 5 ? max($current_page - 1, 1): $start_page - 5;
                $prev_disabled = $current_page == 1 ? "page-link inactive-link" : "page-link";
                $next_disabled = ($current_page == $total_pages || $total_pages == 0) ? "page-link inactive-link" : "page-link";

                $pagination_links .= '<li class="page-item"><a class="' . $prev_disabled . '" href="?page='. $prev . '">«前</a></li>';                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $active_class = ($i == $current_page) ? 'active' : '';    
                    $pagination_links .= '<li class="page-item ' . $active_class . '"><a class="page-link" href="?page='.$i.'">' . $i . '</a></li>';
                }
                $pagination_links .= '<li class="page-item"><a class="' . $next_disabled . '" href="?page='. $next . '"   >次»</a></li>';
            ?>
            <div class="pagination-box">
                <ul class="pagination">
                    <?= $pagination_links ?>
                </ul>
            </div>
            <div class="cates-list">
                <div class="table-box">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th scope="col">No</th>
                                <th scope="col">大カテゴリ</th>
                                <th scope="col">小カテゴリ</th>
                                <th scope="col">地域</th>
                                <th scope="col">件数</th>
                                <th scope="col">取得日時</th>
                                <th scope="col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                                if(count($searchResult)>0):                                    
                                    foreach($searchResult as $key=>$data):                                        
                                ?>
                                        <tr>
                                            <td><?= $key+1?></td>
                                            <td><?= $data['large_category'];?></td>
                                            <td><?= $data['small_category'];?></td>
                                            <td><?= $data['region'];?></td>
                                            <td><?= $data['num']==0 ? "-" : $data['num'];?></td>
                                            <td><?= substr($data['acquisition_time'],0,16)?></td>
                                            <td>
                                                <?php if($data['num']>0):?>
                                                    <form action="./back/list_store_csv_out.php" method="post">
                                                        <input type="hidden" name="small_category" value="<?= $data['small_category'];?>">
                                                        <input type="hidden" name="region" value="<?= $data['region']?>">
                                                        <button type="submit" class="btn btn-primary" <?= in_array('CSV出力', $func_limit) ? 'disabled' : ''; ?>>CSV出力</button>
                                                    </form>
                                                <?php endif;?>
                                            </td>
                                        </tr>
                            <?php    
                                    endforeach;
                                endif;    
                            ?>
                        </tbody>           
                    </table>

                    <section class="preloading">
                        <div id="preloader">
                            <div id="ctn-preloader" class="ctn-preloader">
                                <div class="animation-preloader">
                                    <div class="spinner"></div>
                                    <div class="txt-loading">
                                        <span data-text-preloader="現在、" class="letters-loading">
                                            現在、
                                        </span>
                                        
                                        <span data-text-preloader="クロー" class="letters-loading">
                                            クロー
                                        </span>

                                        <span data-text-preloader="リング" class="letters-loading">
                                            リング
                                        </span>
                                        
                                        <span data-text-preloader="中です。" class="letters-loading">
                                            中です。
                                        </span>
                                        
                                        <span data-text-preloader="しばらくお" class="letters-loading">
                                            しばらくお
                                        </span>
                                        
                                        <span data-text-preloader="待ちく" class="letters-loading">
                                            待ちく
                                        </span>
                                        
                                        <span data-text-preloader="ださい。" class="letters-loading">
                                            ださい。
                                        </span>
                                    </div>
                                </div>	
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>
    
    <script src="./assets/js/client/list_store.js"></script>
    <script src="./assets/js/preloading.js"></script>
    <script src="./assets/js/client/account_info.js"></script>
    <script type="text/javascript">
        function validateForm() {
            const checkboxes = document.querySelectorAll("input[name='problem[]']");
            let checkedCount = 0;

            checkboxes.forEach((checkbox) => {
                if (checkbox.checked) {
                    checkedCount++;
                }
            });
            if (checkedCount === 0) {
                alert('解決したい課題は、最低一つを選択してください。');
                return false;
            }
            
            return true;
        }
        document.getElementById('myForm').addEventListener('submit', function(e) {
        const selectNames = ["gender", "age_group", "regions", "industry", "staff_num", "depart_name", "role"];
        let isValid = true;

        selectNames.forEach(function(name) {
            const element = document.getElementById(name);
            if (element && element.value === "") {
                alert(`${name} を選択してください。`);
                isValid = false;
            }
        });

        if (!isValid) {
            e.preventDefault();
        }
    });
    </script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
        crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI"
        crossorigin="anonymous"></script>
</body>
</html>