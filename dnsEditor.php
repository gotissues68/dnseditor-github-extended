<?php
session_start();
date_default_timezone_set('America/New_York');
include 'config.php';

if (!empty($cfg_auth)) {
    if (empty($_SESSION[successful_auth])) {
        header("");
    }
}

require_once 'MDB2.php';
$dsn = "$cfg_db_type://$cfg_db_user:$cfg_db_pass@$cfg_db_host/$cfg_db_name";
$options = array(
    'debug' => 2,
    'result_buffering' => true,
);

$mdb2 =& MDB2::factory($dsn, $options);
if (PEAR::isError($mdb2)) {
    die($mdb2->getMessage());
}

$GLOBALS["DEFAULTVAL"] = "Click to Edit";

// ini_set('log_errors', $cfg_log_errors);

// primary_ns is needed if you are going to use the provided script to update your servers.

$primary_ns = $cfg_primary_ns;

// Begin session
// session_start();

function ToDBString($string, $isNumber = false)
{
    global $mdb2;
    if ($isNumber) {
        if (preg_match("/^\d*[\.\,\']\d+|\d+[\.\,\']|\d+$/A", $string))
            return preg_replace(array("/^(\d+)[\.\,\']$/", "/^(\d*)[\.\,\'](\d+)$/"), array("\1.", "\1.\2"), $string);
        else
            die("~~|~~~~|~~String validation error: Not a number: \"" . $string . "\"");
    } else {
        return "'" . $mdb2->escape(trim(strtolower($string))) . "'";
    }
}

// Main function to update table information

function updateSerial($id)
{
    global $cfg_db_table;
    global $mdb2;
    $res =& $mdb2->query("select zone from $cfg_db_table where id =  . ToDBString($id,true) . ");
    if (PEAR::isError($res)) {
        die($res->getMessage());
    }
    $row = $res->fetchRow(MDB2_FETCHMODE_ASSOC);
    $zone = $row[0];
    if ($zone) {
        $res =& $mdb2->exec("UPDATE $cfg_db_table SET serial = " . date("U") . " WHERE zone='$zone' AND type='SOA'");
    } else {
        $errText .= "SQL Error: " . $res->getMessage() . "\r\n" . $res;
        return $errText;
    }
}

function changeText($sValue)
{
    global $cfg_db_table;
    global $mdb2;
    // decode submitted data
    $errText = "";
    $sValue_array = explode("~~|~~", $sValue);
    $sCell = explode("__", $sValue_array[1]);
    // strip bad stuff
    $parsedInput = htmlspecialchars($sValue_array[0], ENT_QUOTES);
    //update DB
    if ($sCell[0]) {
        $sql =& $mdb2->exec("UPDATE $cfg_db_table SET $sCell[1]= '$parsedInput' WHERE id = " . ToDBString($sCell[0], true));
    }
    // create string to return to the page
    $newText = '<div onclick="editCell(\'' . $sValue_array[1] . '\', this);">' . $parsedInput . '</div>~~|~~' . $sValue_array[1] . '~~|~~' . $errText;
    return $newText;
}

function changeZone($sValue)
{
    // decode submitted data
    $sValue_array = explode("~~|~~", $sValue);
    $sZone = $sValue_array[0];
    // strip bad stuff
    // create string to return to the page
    $errText = "";
    if ($err != 0) {
        $errText = "MySQL Error: " . $sql->getMessage();
    }
    $newText = getZone($sZone) . "~~|~~" . $zone . '~~|~~' . $errText;
    return $newText;
}

function addBatchRecords($zonesS)
{
    $zones = explode("\n", $zonesS);
    $errs = array();

    $num = 0;
    $failed = 0;
    foreach ($zones as $zone) {
        $zone = trim($zone);

        if (empty($zone)) continue;

        $r = addRecordX($zone, "SOA");
        list($x1, $x2, $err) = explode("~~|~~", $r);

        if (!empty($err)) {
            $errs[] = trim($err);
            ++$failed;
        } else {
            ++$num;
        }
    }

    $err = "";
    if (count($errs) > 0) {
        $err = implode("\n", $errs);
    } else if ($num == 0) {
        $err = "No valid zones entered.";
    }

    return "$num~~|~~$failed~~|~~$err";
}

function addRecord($sValue)
{
    $sValue_array = explode("~~|~~", $sValue);
    return addRecordX($sValue_array[0], $sValue_array[1]);
}

function addRecordX($zone, $rtype)
{
    global $user;
    global $cfg_db_table;
    global $mdb2;
    global $primary_ns;
    $errText = "";
    $rtype = strtoupper($rtype);


    if (isValidType($rtype)) {
        switch ($rtype) {
            case "A":
                $sql_string = "INSERT INTO $cfg_db_table (zone,type) VALUES (" . ToDBString($zone) . ",UCASE(" . ToDBString($rtype) . "));";
                if (zoneExists($zone)) {
                    $sql =& $mdb2->exec($sql_string);
                } else {
                    $errText .= "Zone does not exist.  (zone: " . $zone . ")\r\n";
                }
                break;
            case "SOA":

// For some reason UCASE(ToDBString($rtype)) does not work on this query using Postgres so I have statically defined the SOA record type here

// Adding hostmaster.$zone. meets the suggested record for the RNAME field. Not using the output of ToDBString means the input data can't be verified to be correct, however it screws up the query on Postgres as well.

                if (!empty($user)) {
                    $sql_string = "INSERT INTO $cfg_db_table (zone,host,type,data,ttl,refresh,retry,expire,minimum,serial,resp_person,owner)VALUES (" . ToDBString($zone) . ",'@','SOA','" . $primary_ns . "',86400,7200,3600,604800,3600," . date("U") . ",'hostmaster.$zone.','" . $user . "')";
                } else {
                    $sql_string = "INSERT INTO $cfg_db_table (zone,host,type,data,ttl,refresh,retry,expire,minimum,serial,resp_person)VALUES (" . ToDBString($zone) . ",'@','SOA','" . $primary_ns . "',86400,7200,3600,604800,3600," . date("U") . ",'hostmaster')";
                }

                if (!zoneExists($zone)) {
                    $sql =& $mdb2->exec($sql_string);
                    global $cfg_newzone_defaults;
                    foreach ($cfg_newzone_defaults as $dflt) {
                        switch (strtoupper($dflt["type"])) {
                            case "A":
                                $sql = "INSERT INTO $cfg_db_table (zone,host,type,data)
		        VALUES (" . ToDBString($zone) . ", " . ToDBString($dflt["name"]) . ", 'A',
			  " . ToDBString($dflt["ip"]) . ")";
                                $mdb2->exec($sql);
                                break;

                            case "NS":
                                $sql = "INSERT INTO $cfg_db_table (zone,host,type,data)
		        VALUES (" . ToDBString($zone) . ", '@', 'NS', " . ToDBString($dflt["nameserver"]) . ")";
                                $mdb2->exec($sql);
                                break;

                            case "MX":
                                if (empty($dflt["priority"])) {
                                    $dflt["priority"] = 10;
                                }

                                $sql = "INSERT INTO $cfg_db_table (zone,host,type,data,mx_priority)
		        VALUES (" . ToDBString($zone) . ", '@', 'MX',
			  " . ToDBString($dflt["name"]) . ", " . ToDBString($dflt["priority"]) . ")";
                                $mdb2->exec($sql);
                                break;

                            case "CNAME":
                                $sql = "INSERT INTO $cfg_db_table (zone,host,type,data)
		        VALUES (" . ToDBString($zone) . ", " . ToDBString($dflt["name"]) . ", 'CNAME',
			  " . ToDBString($dflt["target"]) . ")";
                                $mdb2->exec($sql);
                                break;

                            case "TXT":
                                $sql = "INSERT INTO $cfg_db_table (zone,host,type,data)
		        VALUES (" . ToDBString($zone) . ", " . ToDBString($dflt["name"]) . ", 'TXT',
			  " . ToDBString($dflt["text"]) . ")";
                                $mdb2->exec($sql);
                                break;
                        }
                    }
                } else {
                    $errText .= "Zone already exists.  (zone: " . $zone . ")\r\n";
                }

                break;
                break;
            default:
                $sql_string = "INSERT INTO $cfg_db_table (zone,host,type) VALUES (" . ToDBString($zone) . ",'@',UCASE(" . ToDBString($rtype) . "))";
                if (zoneExists($zone)) {
                    $sql =& $mdb2->exec($sql_string);
//          $err = $sql->getMessage();
                } else {
                    $errText .= "Zone does not exist.  (zone: " . $zone . ")\r\n";
                }
                break;
        }
    } else {
        $errText .= "Invalid record type specified.  (type: " . $rtype . ")\r\n";
    }
    // create string to return to the page
    $typehtml = "";
    switch ($rtype) {
        case "NS":
            $typehtml = getNSRecords($zone);
            break;
        case "MX":
            $typehtml = getMXRecords($zone);
            break;
        case "A":
            $typehtml = getARecords($zone);
            break;
        case "SOA":
            $typehtml = getSOARecord($zone);
            break;
        case 'CNAME':
            $typehtml = getCNAMERecords($zone);
            break;
        case 'TXT':
            $typehtml = getTXTRecords($zone);
            break;
    }
    $newtext = $rtype . '~~|~~' . $typehtml . '~~|~~' . $errText;
    return $newtext;
}

function delZone($sValue)
{
    global $cfg_db_table;
    global $mdb2;
    $errText = "";
    $sValue_array = explode("~~|~~", $sValue);
    $zone = $sValue_array[0];
    if (zoneExists($zone)) {
        $sql =& $mdb2->exec("delete from $cfg_db_table where zone=" . ToDBString($zone) . "");
        $err = $sql->getMessage;
    } else {
        $errText .= "Cannot delete zone: Zone does not exist.  (zone: " . $zone . ")\r\n";
    }
    // create string to return to the page
    if ($err != 0) {
        $errText .= "SQL Error: " . $sql->getMessage . "\r\n";
    }
    $typehtml = "";
    $newtext = '~~|~~~~|~~' . $errText;
    return $newtext;

}

function delRecord($sValue)
{
    global $cfg_db_table;
    global $mdb2;
    $errText = "";
    $sValue_array = explode("~~|~~", $sValue);
    $zone = $sValue_array[0];
    $rtype = $sValue_array[1];
    $rtype = strtoupper($rtype);
    $id = $sValue_array[2];
    if (idExists($id)) {
        $sql =& $mdb2->exec("delete from $cfg_db_table where id=" . ToDBString($id, true) . "");
//    $err = $sql->getMessage;
    } else {
        $errText .= "Record id does not exist.  (id: " . $id . ")\r\n";
    }
    // create string to return to the page
//  if ($err != 0) {
//    $errText .= "SQL Error: " . $sql->getMessage . "\r\n";
//  }
    $typehtml = "";
    switch ($rtype) {
        case "NS":
            $typehtml = getNSRecords($zone);
            break;
        case "MX":
            $typehtml = getMXRecords($zone);
            break;
        case "A":
            $typehtml = getARecords($zone);
            break;
        case "SOA":
            $typehtml = getSOARecord($zone);
            break;
        case "CNAME":
            $typehtml = getCNAMERecords($zone);
            break;
        case "TXT":
            $typehtml = getTXTRecords($zone);
            break;
    }
    $newtext = $rtype . '~~|~~' . $typehtml . '~~|~~' . $errText;
    return $newtext;
}

function zoneExists($zone)
{
    global $cfg_db_table;
    global $mdb2;
    $sql =& $mdb2->query("select id from $cfg_db_table where type='SOA' and zone=" . ToDBString($zone) . "");

//  $err = $sql->getMessage();
    $rec = $sql->numRows();
    return $rec > 0;
}

function isValidType($rtype)
{
    switch ($rtype) {
        case "NS":
        case "MX":
        case "SOA":
        case "A":
        case "TXT":
        case "CNAME":
            return 1;
            break;
        default:
            return 0;
            break;
    }
}

function idExists($id)
{
    global $cfg_db_table;
    global $mdb2;
    $sql =& $mdb2->query("select id from $cfg_db_table where id=" . ToDBString($id, true) . "");
//  $err = $sql->getMessage();
    $rec = $sql->numRows();
    return $rec > 0;
}

function getZone($zone)
{
    if (zoneExists($zone)) {
        $html .= "<table class=\"editable_table\" border=\"0\">\n";
        $html .= "<tr class=\"yellow\"><th>Edit Zone [" . $zone . "]</th></tr>\n";
        $html .= "</table>\n";
        $html .= "<div id=\"div_soa_records\">" . getSOARecord($zone) . "</div>";
        $html .= "<div id=\"div_ns_records\">" . getNSRecords($zone) . "</div>";
        $html .= "<div id=\"div_mx_records\">" . getMXRecords($zone) . "</div>";
        $html .= "<div id=\"div_a_records\">" . getARecords($zone) . "</div>";
        $html .= '<div id="div_cname_records">' . getCNAMERecords($zone) . '</div>';
        $html .= '<div id="div_txt_records">' . getTXTRecords($zone) . '</div>';
    } else {
        $html .= "<h2>Zone [$zone] does not exist.</h2>";
    }
    return $html;
}

// functions for each record type
function getSOARecord($zone)
{
    global $cfg_db_table;
    global $mdb2;
    // the TR string
    $table = "";
    // query DB
    $sql =& $mdb2->query("SELECT id,host,data,resp_person,refresh,retry,expire,minimum FROM $cfg_db_table WHERE type='SOA' AND zone=" . ToDBString($zone) . "");
    // build table
    $table .= "<table id=\"tbl_soa_records\" class=\"editable_table\">";
    $table .= "<tr class=\"yellow\"><td class=\"row_head\"><strong>SOA</strong></td><td>MNAME</td><td>RNAME</td><td>Refresh</td><td>Retry</td><td>Expire</td><td>Minimum</td></tr>\n";
    while ($row = $sql->fetchRow(MDB2_FETCHMODE_ASSOC)) {
        stripslashes(extract($row));
        if (empty($data)) $data = $GLOBALS["DEFAULTVAL"];

        $table .= "<tr><td class=\"row_head\" align=\"right\"><!-- <span class=\"del\" onclick=\"delRecord('$zone', 'SOA', $id);\">-</span>//--></td>\n";
        $table .= "<td class=\"point\" id=\"" . $id . "__data\" onmouseover=\"bgSwitch('on', this, 'Modify Zone Primary Master Server' );\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "  <div onclick=\"editCell('" . $id . "__data', this);\">" . $data . "</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point\" id=\"" . $id . "__resp_person\" onmouseover=\"bgSwitch('on', this, 'Modify Zone Responsible Person');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "  <div onclick=\"editCell('" . $id . "__resp_person', this);\">" . $resp_person . "</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point\" id=\"" . $id . "__refresh\" onmouseover=\"bgSwitch('on', this, 'Refresh determines the number of seconds between a successful check on the serial number on the zone of the primary, and the next attempt. Usually around 2-24 hours. Not used by a primary server.');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "  <div onclick=\"editCell('" . $id . "__refresh', this);\">" . $refresh . "</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point\" id=\"" . $id . "__retry\" onmouseover=\"bgSwitch('on', this, 'If a refresh attempt fails, a server will retry after this many seconds. Not used by a primary server.');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "  <div onclick=\"editCell('" . $id . "__retry', this);\">" . $retry . "</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point\" id=\"" . $id . "__expire\" onmouseover=\"bgSwitch('on', this, 'Measured in seconds. If the refresh and retry attempts fail after that many seconds the server will stop serving the zone. Typical value is 1 week. Not used by a primary server.');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "  <div onclick=\"editCell('" . $id . "__expire', this);\">" . $expire . "</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point\" id=\"" . $id . "__minimum\" onmouseover=\"bgSwitch('on', this, 'The default TTL for every record in the zone. Can be overidden for any particular record. Typical values range from eight hours to four days. When changes are being made to a zone, often set at ten minutes or less.');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "  <div onclick=\"editCell('" . $id . "__minimum', this);\">" . $minimum . "</div>\n";
        $table .= "</td>\n";
        $table .= "</tr>\n";
    }
    $table .= "</table>\n";
    return $table;
}

function getARecords($zone)
{
    global $cfg_db_table;
    global $mdb2;
    // the TR string
    $table = "";
    // query DB
    $sql =& $mdb2->query("SELECT id,host,data FROM $cfg_db_table WHERE type='A' AND zone=" . ToDBString($zone) . " ORDER BY host");
    // build table
    $table .= "<table id=\"tbl_a_records\" class=\"editable_table\">";
    $table .= "<tr class=\"yellow\">\n";
    $table .= "<td class=\"row_head\"><strong><span class=\"add\" onclick=\"addRecord('$zone', 'A');\" onmouseover=\"bgSwitch('on', this, 'Add A Record');\" onmouseout=\"bgSwitch('off', this, '');\"\"><img src=\"add.png\" border=0 alt=\"Add Record\" /></span> A</strong></td>\n";
    $table .= "<td>Name</td>\n";
    $table .= "<td>IP Address</td>\n";
    $table .= "</tr>\n";
    while ($row = $sql->fetchRow(MDB2_FETCHMODE_ASSOC)) {
        stripslashes(extract($row));
        if (empty($data)) $data = $GLOBALS["DEFAULTVAL"];
        if (empty($host)) $host = $GLOBALS["DEFAULTVAL"];

        $table .= "<tr>\n<td class=\"row_head\" align=\"right\"><span class=\"del\" onclick=\"delRecord('$zone', 'A', $id);\"><img src=\"delete.png\" border=0 alt=\"Delete Record\" /></span></td>\n";
        $table .= "<td class=\"point\" id=\"" . $id . "__host\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record Host');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "<div onclick=\"editCell('" . $id . "__host', this);\">" . $host . "</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point\" id=\"" . $id . "__data\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record IP');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "<div onclick=\"editCell('" . $id . "__data', this);\">" . $data . "</div>\n";
        $table .= "</td>\n</tr>\n";
    }
    $table .= "</table>\n";
    return $table;
}

function getCNAMERecords($zone)
{
    global $cfg_db_table;
    global $mdb2;
    // the TR string
    $table = "";
    // query DB
    $sql =& $mdb2->query("SELECT id,host,data FROM $cfg_db_table WHERE type='CNAME' AND zone=" . ToDBString($zone) . " ORDER BY host");
    // build table
    $table .= "<table id=\"tbl_cname_records\" class=\"editable_table\">";
    $table .= "<tr class=\"yellow\">\n";
    $table .= "<td class=\"row_head\"><strong><span class=\"add\" onclick=\"addRecord('$zone', 'CNAME');\" onmouseover=\"bgSwitch('on', this, 'Add CNAME Record');\" onmouseout=\"bgSwitch('off', this, '');\"\"><img src=\"add.png\" border=0 alt=\"Add Record\" /></span> CNAME</strong></td>\n";
    $table .= "<td>Name</td>\n";
    $table .= "<td>Canonical Name</td>\n";
    $table .= "</tr>\n";
    while ($row = $sql->fetchRow(MDB2_FETCHMODE_ASSOC)) {
        stripslashes(extract($row));
        if (empty($data)) $data = $GLOBALS["DEFAULTVAL"];

        $table .= "<tr>\n<td class=\"row_head\" align=\"right\"><span class=\"del\" onclick=\"delRecord('$zone', 'CNAME', $id);\"><img src=\"delete.png\" border=0 alt=\"Delete Record\" /></span></td>\n";
        $table .= "<td class=\"point\" id=\"" . $id . "__host\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record Host');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "<div onclick=\"editCell('" . $id . "__host', this);\">" . $host . "</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point\" id=\"" . $id . "__data\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record IP');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "<div onclick=\"editCell('" . $id . "__data', this);\">" . $data . "</div>\n";
        $table .= "</td>\n</tr>\n";
    }
    $table .= "</table>\n";
    return $table;
}

function getTXTRecords($zone)
{
    global $cfg_db_table;
    global $mdb2;
    // the TR string
    $table = "";
    // query DB
    $sql =& $mdb2->query("SELECT id,host,data FROM $cfg_db_table WHERE type='TXT' AND zone=" . ToDBString($zone) . " ORDER BY host");
    // build table
    $table .= "<table id=\"tbl_txt_records\" class=\"editable_table\">";
    $table .= "<tr class=\"yellow\">\n";
    $table .= "<td class=\"row_head\"><strong><span class=\"add\" onclick=\"addRecord('$zone', 'TXT');\" onmouseover=\"bgSwitch('on', this, 'Add TXT Record');\" onmouseout=\"bgSwitch('off', this, '');\"\"><img src=\"add.png\" border=0 alt=\"Add Record\" /></span> TXT</strong></td>\n";
    $table .= "<td>Name</td>\n";
    $table .= "<td>Text</td>\n";
    $table .= "</tr>\n";
    while ($row = $sql->fetchRow(MDB2_FETCHMODE_ASSOC)) {
        stripslashes(extract($row));
        if (empty($data)) $data = $GLOBALS["DEFAULTVAL"];

        $table .= "<tr>\n<td class=\"row_head\" align=\"right\"><span class=\"del\" onclick=\"delRecord('$zone', 'TXT', $id);\"><img src=\"delete.png\" border=0 alt=\"Delete Record\" /></span></td>\n";
        $table .= "<td class=\"point\" id=\"" . $id . "__host\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record Host');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "<div onclick=\"editCell('" . $id . "__host', this);\">" . $host . "</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point\" id=\"" . $id . "__data\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record IP');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "<div onclick=\"editCell('" . $id . "__data', this);\">" . $data . "</div>\n";
        $table .= "</td>\n</tr>\n";
    }
    $table .= "</table>\n";
    return $table;
}

function getMXRecords($zone)
{
    global $cfg_db_table;
    global $mdb2;
    // the TR string
    $table = "";
    // query DB
    $sql =& $mdb2->query("SELECT id,mx_priority,data FROM $cfg_db_table WHERE type='MX' AND zone=" . ToDBString($zone) . " ORDER BY mx_priority");
    // build table
    $table .= "<table id=\"tbl_mx_records\" class=\"editable_table\">";
    $table .= "<tr class=\"yellow\">\n";
    $table .= "<td class=\"row_head\"><strong><span class=\"add\" onclick=\"addRecord('$zone', 'MX');\" onmouseover=\"bgSwitch('on', this, 'Add MX Record');\" onmouseout=\"bgSwitch('off', this, '');\"\"><img src=\"add.png\" border=0 alt=\"Add Record\" /></span> MX</strong></td>\n";
    $table .= "<td>Name</td>\n";
    $table .= "<td>Priority</td>\n";
    $table .= "</tr>\n";
    while ($row = $sql->fetchRow(MDB2_FETCHMODE_ASSOC)) {
        stripslashes(extract($row));
        if (empty($data)) $data = $GLOBALS["DEFAULTVAL"];
        if (empty($mx_priority)) $mx_priority = $GLOBALS["DEFAULTVAL"];

        $table .= "<tr>\n<td class=\"row_head\" align=\"right\"><span class=\"del\" onclick=\"delRecord('$zone', 'MX', $id);\"><img src=\"delete.png\" border=0 alt=\"Delete Record\" /></span></td>\n";
        $table .= "<td class=\"point\" id=\"" . $id . "__data\" onmouseover=\"bgSwitch('on', this, 'Modify Mail Exchange Name');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "<div onclick=\"editCell('" . $id . "__data', this);\">" . $data . "</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point_small\" id=\"" . $id . "__mx_priority\" onmouseover=\"bgSwitch('on', this, 'Modify Mail Exchange Priority');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "<div onclick=\"editCell('" . $id . "__mx_priority', this);\">" . $mx_priority . "</div>\n";
        $table .= "</td>\n</tr>\n";
    }
    $table .= "</table>\n";
    return $table;
}

function getNSRecords($zone)
{
    global $cfg_db_table;
    global $mdb2;
    // the TR string
    $table = "";
    // query DB
    $sql =& $mdb2->query("SELECT id,data FROM $cfg_db_table WHERE type='NS' AND zone=" . ToDBString($zone) . " order by DATA");
    // build table
    $table .= "<table id=\"tbl_nsrecords\" class=\"editable_table\">";
    $table .= "<tr class=\"yellow\">\n";
    $table .= "<td class=\"row_head\"><strong><span class=\"add\" onclick=\"addRecord('$zone', 'NS');\"  onmouseover=\"bgSwitch('on', this, 'Add Nameserver Record');\" onmouseout=\"bgSwitch('off', this, '');\"><img src=\"add.png\" border=0 alt=\"Add Record\" /></span> NS</strong></td>\n";
    $table .= "<td>Nameserver</td>\n";
    $table .= "</tr>\n";
    while ($row = $sql->fetchRow(MDB2_FETCHMODE_ASSOC)) {
        stripslashes(extract($row));
        if (empty($data)) $data = $GLOBALS["DEFAULTVAL"];

        $table .= "<tr>\n<td class=\"row_head\" align=\"right\"><span class=\"del\" onclick=\"delRecord('$zone', 'NS', $id);\"  onmouseover=\"bgSwitch('on', this, 'Delete Nameserver Record');\" onmouseout=\"bgSwitch('off', this, '');\"><img src=\"delete.png\" border=0 alt=\"Delete Record\" /></span></td>\n";
        $table .= "<td class=\"point\" id=\"" . $id . "__data\" onmouseover=\"bgSwitch('on', this, 'Modify Nameserver Record');\" onmouseout=\"bgSwitch('off', this, '');\">\n";
        $table .= "<div onclick=\"editCell('" . $id . "__data', this);\">" . $data . "</div>\n";
        $table .= "</td>\n</tr>\n";
    }
    $table .= "</table>\n";
    return $table;
}

function getZoneList()
{
    global $cfg_db_table;
    global $user;
    global $mdb2;
// the TR string
    $table = "";
// query DB

    if (!empty($user)) {
        $sql =& $mdb2->query("SELECT DISTINCT zone,id FROM $cfg_db_table WHERE type='SOA' and owner='$user' order by zone asc");
    } else {
        $sql =& $mdb2->query("SELECT DISTINCT zone,id FROM $cfg_db_table WHERE type='SOA' order by zone asc");
    }
// build table
    while ($row = $sql->fetchRow(MDB2_FETCHMODE_ASSOC)) {
        stripslashes(extract($row));
        if (empty($data)) $data = $GLOBALS["DEFAULTVAL"];

        $table .= "\'' . $zone . "');\" onmouseover=\"bgSwitch('on', this, 'Delete Zone: " . $zone . "');\" onmouseout=\"bgSwitch('off', this);\"><img src=\"delete.png\" border=\"0\" alt=\"Delete Zone\" /></span></td>\n";
        $table .= "<td class=\"point\" id=\"" . $id . "__zone\" onmouseover=\"bgSwitch('on', this, 'Edit Zone: " . $zone . "');\" onmouseout=\"bgSwitch('off', this, '');\">\n";
        $table .= "<div onclick=\"editZone('" . $zone . "');\">" . $zone . "</div>\n";
        $table .= "</td>\n</tr>\n";
    }
    return $table;

}

// sajax
require_once("sajax.php");
// $sajax_request_type = "POST";
sajax_init();
// $sajax_debug_mode = 1;
sajax_export("changeText");
sajax_export("changeZone");
sajax_export("addRecord");
sajax_export("delRecord");
sajax_export("updateServers");
sajax_export("getZoneList");
sajax_export("delZone");
sajax_export("addBatchRecords");
sajax_handle_client_request();
?>























<?php
sajax_show_javascript();
?>






















































































































































<?php $GLOBALS["DEFAULTVAL"]?>

























































































<?php
$auth_user = ucfirst($user);
echo "<tr>";
echo "<th colspan=1 valign=top align=left>";
echo "Hi $auth_user";
echo "</th>";
echo "<td align=right>";
echo "<form action=logout.php method=POST>";
echo "<input id=cmdBtn1 type=submit value='Logout'>";
echo "<input type=hidden name=LOGOUT>";
echo "</form>";
echo "</td>";
echo "</tr>";

echo "<tr>";
echo "<th colspan=1 valign=top align=left>";
echo "</th>";
echo "<td align=right>";
echo "<form action=backup.php method=POST>";
echo "<input id=cmdBtn1 type=submit value='Backup DB'>";
echo "<input type=hidden name=backup>";
echo "</form>";
echo "</td>";
echo "</tr>";

?>










<?php
if (@$cfg_updateservers) {
    ?>


<?php
}
?>
</td>

</tr>
</table>
<hr/>
<table border="0" width="100%" id="batchadd">
    <thead>
    <tr>
        <th colspan='2'>
            Batch Add Zones
        </th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td align="left" style="font-size: 11px">(1/line)</td>
        <th>
            <input type="button" class="cmdBtn" id="batchbtn" value="Add Zones"
                   onClick="javascript:addZones();" onmouseover="bgSwitch('on', this, 'Add batch Zones');"
                   onmouseout="bgSwitch('off', this, '');"/>
        </th>
    </tr>
    <tr>
        <td colspan='2' class='batchcell'>
            <textarea name="batchzones" id="batchzones"></textarea>
            <img id="batchloader" src="loader.gif" style="display:none"/>
        </td>
    </tr>
    </tbody>
</table>
<hr/>
    </div>
<div id="zonelist_filter_div" onmouseover="bgSwitch('on', this, 'Filter zones based on text string');"
     onmouseout="bgSwitch('off', this, '');">
    Filter: <input name="zonemenu_filterable_filter" id="zonemenu_filterable_filter" type="text" value="" size="10"
                   maxlength="10"/>
    </div>
<div id="zonelist_menu">
    <table class="zonemenu_filterable" id="zonemenu_filterable" border="0">
        <?php echo getZoneList(); ?>
    </table>
    </div>
<div id="status_div">
    <div id="status_div_over">
        &nbsp;
    </div>
    <div id="status_div_ajax">
    </div>
    </div>
</div>
<div id="zoneinfo">
    <div id="zone_edit_msg"><strong>Select a zone.</strong></div>
</div>
</div>
</body>
</html>
