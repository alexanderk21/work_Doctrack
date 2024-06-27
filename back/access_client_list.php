<?php
session_start();
require('../common.php');
$client_id = $_GET['client_id'];

if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $confirm_user_id = $_GET['user_id'];
} else {
    $queryString = ltrim($_GET['filter_customers'], '?');
    parse_str($queryString, $confirm_results);
    $confirm_user_id = implode(",", $confirm_results);
}
header('Location: ../clients_list.php?users_id=' . $confirm_user_id);
?>