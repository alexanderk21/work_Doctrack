<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./assets/css/login.css" type="text/css">
    <link rel="icon" href="">
    <title>ログイン</title>
</head>

<body>
    <div class="wrapper">
        <h1>ログイン</h1>
        <form action="./back/login-checker.php" method="POST">
        <?php
            if(isset($_GET['error'])){
                if($_GET['error'] == "pwderror"){
                    echo "<p class='error'>電子メールまたはパスワードでエラーが発生しました。<p>";
                }else{
                    echo "<p class='error'>アカウントの登録がありません。<p>";
                }
            }
        ?>
            <input name="email" type="email" placeholder="メールアドレス" required><br>
            <input name="pass" type="password" placeholder="パスワード" required><br><br>           
            <input type="submit" value="ログイン">
        </form>
        <br>
        <small><a href="./forgot_pwd.php">パスワードを忘れた場合はこちら</a></small><br>
        <small><a href="./signup_1.php">新しくアカウントを作成する</a></small>
    </div>
</body>

</html>