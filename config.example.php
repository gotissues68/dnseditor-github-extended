<?php

// Global configuration

// Domain owner and greeting information if not using some auth mechanism

$user = $_SESSION['user'];

// Configure authentiation options here
// If you leave $cfg_auth empty the auth will not be enabled

$cfg_auth = "";

// Do we log errors?

$cfg_log_errors = true;

// your primary nameserver
// This option was used for scripting updates for replication I believe but it's not documented and it's not used
$cfg_primary_ns = "";

// Database connection information.
// See http://pear.php.net/packages/MDB2 for supported database types.

$cfg_db_type = "mysql";
$cfg_db_name = "dns";
$cfg_db_table = "records";
$cfg_db_host = "localhost";
$cfg_db_user = "someuser";
$cfg_db_pass = "somepassword";

// show the "Update Servers" button. You need to configure the dns_forbidden.update script for this to work
$cfg_updateservers = false;

// default zone options
// possible key's
// ALL: type (type of record, ie. A, NS, MX, TXT or CNAME)
// NS: nameserver
// MX: name, priority
// A: name, ip
// CNAME: name, target
// TXT: name, text
$cfg_newzone_defaults = array(
  array(
      "type" => "A"
    , "name" => "@"
    , "ip"   => ""
  ),

  array(
      "type" => "A"
    , "name" => "www"
    , "ip"   => ""
  ),

  array(
      "type" => "A"
    , "name" => "mail"
    , "ip"   => ""
  ),

  array(
      "type" => "NS"
    , "nameserver" => ""
  ),

  array(
      "type" => "NS"
    , "nameserver" => ""
  ),

  array(
      "type" => "MX"
    , "name" => "mail"
  ),

  array(
      "type" => "TXT"
    , "name" => "whois"
    , "text" => ""
  ),

  array(
      "type" => "CNAME"
    , "name" => "smtp"
    , "target" => ""
  ),

  array(
      "type" => "CNAME"
    , "name" => "pop"
    , "target" => ""
  )
);

?>
