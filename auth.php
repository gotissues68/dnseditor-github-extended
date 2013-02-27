<?php
function loginFunction()
{
    /*
     * Change the HTML output so that it fits to your
     * application.
     */
    echo "<html>";
    echo "<head>";
    echo "<title>Live Zones Login</title>";
    echo "<meta http-equiv=content-type content=text/html charset=utf-8>";
    echo "<link rel=stylesheet href=default.css type=text/css>";
    echo "<style type=text/css media=screen></style>";
    echo "</head>";
    echo "<body>";
    echo "<center>";
    echo "<form method=post action=$PHP_SELF>";
    echo "<fieldset>";
    echo "<legend>Live Zones</legend>";
    echo "<p><label>Username</label> <input type=text name=\"username\"></p>";
    echo "<p><label>Password</label> <input type=password name=\"password\"><br/></p>";
    echo "<p class=submit><input type=submit value=login></p>";
    echo "</fieldset>";
    echo "</form>";
    echo "</center>";
    echo "</body>";
    echo "</html>";
}

// Other auth methods are supported. See http://pear.php.net/packages/Auth for more information

require_once "Auth/Auth.php";
    $auth = new Auth("LDAP", array(
       'host' => '',
       'port' => '389',
       'version' => 3,
       'basedn' => 'dc=,dc=',
       'userattr' => 'uid'),
       "loginFunction"

);
$auth->start();
if ($auth->checkAuth()) {
	$_SESSION['user'] = $auth->getUsername();
	$_SESSION['successful_auth'] = 'true';
        header("Location: http://");

}
?>
