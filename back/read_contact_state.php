<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unsetSession'])) {
    unset($_SESSION['read_contact']);
} elseif($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setSession'])){
    $_SESSION['read_contact'] = 'on';
} else {
    http_response_code(400);
}
?>
