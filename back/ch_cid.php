<?php
include_once './../config/database.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (isset($_POST['new_cid'])) {
    $new_cid = $_POST['new_cid'];
    $old_cid = $_POST['old_cid'];
    $user_id = $_POST['user_id'];

    try {
        $db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        //Change Id of clients table.
        $stmt = $db->prepare('UPDATE clients SET id=:new_cid WHERE id = :id');
        $stmt->execute(array(':id' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of clients table.
        $stmt = $db->prepare('UPDATE clients SET ref=:new_cid WHERE ref = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of clients table.
        $stmt = $db->prepare('UPDATE clients SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of actions table.
        $stmt = $db->prepare('UPDATE actions SET client_id=:new_cid WHERE client_id = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of ad_click_cnt table.
        $stmt = $db->prepare('UPDATE ad_click_cnt SET clicked_cid=:new_cid WHERE clicked_cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of biz_template_setting table.
        $stmt = $db->prepare('UPDATE biz_template_setting SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of business_schedules table.
        $stmt = $db->prepare('UPDATE business_schedules SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of cmss table.
        $stmt = $db->prepare('UPDATE cmss SET client_id=:new_cid WHERE client_id = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of csv_history table.
        $stmt = $db->prepare('UPDATE csv_history SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of favi_logos table.
        $stmt = $db->prepare('UPDATE favi_logos SET client_id=:new_cid WHERE client_id = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of form_logos table.
        $stmt = $db->prepare('UPDATE form_logs SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of form_users table.
        $stmt = $db->prepare('UPDATE form_users SET client_id=:new_cid WHERE client_id = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of froms table.
        $stmt = $db->prepare('UPDATE froms SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of logs table.
        $stmt = $db->prepare('UPDATE logs SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of pdf_versions table.
        $stmt = $db->prepare('UPDATE pdf_versions SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of popups table.
        $stmt = $db->prepare('UPDATE popups SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of popup_access table.
        $stmt = $db->prepare('UPDATE popup_access SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of redirects table.
        $stmt = $db->prepare('UPDATE redirects SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of schedules table.
        $stmt = $db->prepare('UPDATE schedules SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of stages table.
        $stmt = $db->prepare('UPDATE stages SET client_id=:new_cid WHERE client_id = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of stops table.
        $stmt = $db->prepare('UPDATE stops SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of store_search table.
        $stmt = $db->prepare('UPDATE store_search SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of tag_sample table.
        $stmt = $db->prepare('UPDATE tag_sample SET client_id=:new_cid WHERE client_id = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of templates table.
        $stmt = $db->prepare('UPDATE templates SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of users table.
        $stmt = $db->prepare('UPDATE users SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cuser_id of users table.
        $stmt = $db->prepare('UPDATE users SET cuser_id=:new_cid WHERE cuser_id = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of user_cms_access table.
        $stmt = $db->prepare('UPDATE user_cms_access SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of user_pdf_access table.
        $stmt = $db->prepare('UPDATE user_pdf_access SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));
        //Change Cid of user_redirects_access table.
        $stmt = $db->prepare('UPDATE user_redirects_access SET cid=:new_cid WHERE cid = :cid');
        $stmt->execute(array(':cid' => $old_cid, ':new_cid' => $new_cid));

    } catch (PDOException $e) {
        echo '接続失敗' . $e->getMessage();
        exit();
    }
}

if($user_id == $old_cid){
    $client_user = $new_cid;
}else{
    $client_user = $user_id;
}
header('Location: https://' . $new_cid . '.doc1.jp?client_id=' . $new_cid . '/' . $client_user);
exit();