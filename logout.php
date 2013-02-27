<?php
session_start();
require_once "Auth/Auth.php";
    $auth = new Auth("LDAP", array(
       'host' => '',
       'port' => '389',
       'version' => 3,
       'basedn' => 'dc=,dc=',
       'userattr' => 'uid')
);

echo $_POST['LOGOUT'];

    if (isset($_POST['LOGOUT'])) {
	session_unset();
        $auth->logout();
	$host  = $_SERVER['HTTP_HOST'];
	$uri  = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
	$extra = 'auth.php';
	setcookie ("authen", "", time() - 3600);
	header("Location: http://$host$uri/$extra");
    exit;
    }

?>
