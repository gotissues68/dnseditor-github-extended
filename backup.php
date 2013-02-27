<?php
session_start();
include 'config.php';

if(!empty($cfg_auth)) {
		if(empty($_SESSION[successful_auth])) {
		header("Location: ");
	}
}

$date = date("mdYU");
exec("mysqldump -u $cfg_db_user -p$cfg_db_pass $cfg_db_name $cfg_db_table > tmp/$date.backup.sql");
$res = file_get_contents("tmp/$date.backup.sql"); 
$filename = "backup_".date("m-d-Y-U").".sql";
header("Content-type: attachment; charset=utf-8");
header("Content-Disposition: attachment; filename=".$filename);
print $res;
exit;
?>
