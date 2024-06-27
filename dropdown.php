<div class="d-flex align-items-center">
    <a href="https://doctrack.jp/usersupport/category/" target="_blank" class="btn btn-danger me-2">
        マニュアル
    </a>
    <div class="btn-group">
        <button type="button" class="btn dropdown-toggle d-flex align-items-center" data-toggle="dropdown"
            aria-haspopup="true" aria-expanded="false">
            <img src="https://in.fanqcall.com/img/user.png" style="width: 30px;" alt="">
            <h5><?= $ClientUser['email']; ?></h5>
        </button>
        <div class="dropdown-menu dropdown-menu-right">
            <a href="my_page.php" class="dropdown-item"><i class="fa fa-user"></i> マイページ</a>
            <a href="logout.php" class="dropdown-item"><i class="fa fa-sign-out"></i> ログアウト</a>
        </div>
    </div>
</div>