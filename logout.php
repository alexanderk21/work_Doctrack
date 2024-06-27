<?php
session_start();

$_SESSION = array();
session_destroy();
unset($_SESSION['client_id']);

header('Location: https://in.doc1.jp/login.php');
exit();
?>
