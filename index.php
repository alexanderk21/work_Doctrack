<?php
session_start();
if(isset($_GET['client_id'])){
    $get = explode('/', $_GET['client_id']);
    $_SESSION['client_id'] = $get[0];
    $_SESSION['client_user'] = $get[1] ?? $get[0];
}

header('Location: clients_list.php');