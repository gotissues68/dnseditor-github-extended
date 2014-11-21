<?php
session_start();

include 'config.php';

if(!empty($cfg_auth)) {
    if(empty($_SESSION[successful_auth])) {
        header("Location: http://www.techiekb.com/dnsExtended/auth.php")
        ;
    }
}

require_once '/usr/share/pear/MDB2.php';
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

function ToDBString($string, $isNumber=false) {
    global $mdb2;
    if($isNumber) {
        if(preg_match("/^\d*[\.\,\']\d+|\d+[\.\,\']|\d+$/A", $string))
            return preg_replace( array("/^(\d+)[\.\,\']$/","/^(\d*)[\.\,\'](\d+)$/"),array("\1.","\1.\2"), $string);
        else
            die("~~|~~~~|~~String validation error: Not a number: \"".$string ."\"");
    } else {
        return "'".$mdb2->escape(trim(strtolower($string)))."'";
    }
}
// Main function to update table information

function updateSerial($id) {
    global $cfg_db_table;
    global $mdb2;
    $res =& $mdb2->query("select zone from $cfg_db_table where id =  . ToDBString($id,true) . ");
    if (PEAR::isError($res)) {
        die($res->getMessage());
    }
    $row = $res->fetchRow(MDB2_FETCHMODE_ASSOC);
    $zone = $row[0];
    if ($zone) {
        $res =& $mdb2->exec("UPDATE $cfg_db_table SET serial = " . date("U") . " WHERE zone='$zone' AND type='SOA';");
    } else {
        $errText .= "SQL Error: " . $res->getMessage() . "\r\n" . $res;
        return $errText;
    }
}
function changeText($sValue) {
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
        $sql =& $mdb2->exec("UPDATE $cfg_db_table SET $sCell[1]= '$parsedInput' WHERE id = ".ToDBString($sCell[0],true));
    }
    // create string to return to the page
    $newText = '<div onclick="editCell(\''.$sValue_array[1].'\', this);">'.$parsedInput.'</div>~~|~~'.$sValue_array[1] . '~~|~~' . $errText;
    return $newText;
}
function changeZone($sValue) {
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
    foreach ($zones as $zone)
    {
        $zone = trim($zone);

        if (empty($zone)) continue;

        $r = addRecordX($zone, "SOA");
        list($x1, $x2, $err) = explode("~~|~~", $r);

        if (!empty($err))
        {
            $errs[] = trim($err);
            ++$failed;
        }
        else
        {
            ++$num;
        }
    }

    $err = "";
    if (count($errs) > 0)
    {
        $err = implode("\n", $errs);
    }
    else if ($num == 0)
    {
        $err = "No valid zones entered.";
    }

    return "$num~~|~~$failed~~|~~$err";
}
function addRecord($sValue) {
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
                $sql_string = "INSERT INTO $cfg_db_table (zone,type) VALUES (".ToDBString($zone) . ",UCASE(" . ToDBString($rtype) . "));";
                if (zoneExists($zone)) {
                    $sql =& $mdb2->exec($sql_string);
                } else {
                    $errText .= "Zone does not exist.  (zone: " . $zone . ")\r\n";
                }
                break;
            case "SOA":

// For some reason UCASE(ToDBString($rtype)) does not work on this query using Postgres so I have statically defined the SOA record type here

// Adding hostmaster.$zone. meets the suggested record for the RNAME field. Not using the output of ToDBString means the input data can't be verified to be correct, however it screws up the query on Postgres as well.

                if(!empty($user)) {
                    $sql_string = "INSERT INTO $cfg_db_table (zone,host,type,data,ttl,refresh,retry,expire,minimum,serial,resp_person,owner)VALUES (".ToDBString($zone) . ",'@','SOA','".$primary_ns."',86400,7200,3600,604800,3600,".date("U").",'hostmaster.$zone.','".$user."');";
                }

                else {
                    $sql_string = "INSERT INTO $cfg_db_table (zone,host,type,data,ttl,refresh,retry,expire,minimum,serial,resp_person)VALUES (".ToDBString($zone) . ",'@','SOA','".$primary_ns."',86400,7200,3600,604800,3600,".date("U").",'hostmaster');";
                }

                if (!zoneExists($zone)) {
                    $sql =& $mdb2->exec($sql_string);
                    global $cfg_newzone_defaults;
                    foreach ($cfg_newzone_defaults as $dflt)
                    {
                        switch (strtoupper($dflt["type"]))
                        {
                            case "A":
                                $sql = "INSERT INTO $cfg_db_table (zone,host,type,data)
		        VALUES (".ToDBString($zone).", ".ToDBString($dflt["name"]).", 'A',
			  ".ToDBString($dflt["ip"]).")";
                                $mdb2->exec($sql);
                                break;

                            case "NS":
                                $sql = "INSERT INTO $cfg_db_table (zone,host,type,data)
		        VALUES (".ToDBString($zone).", '@', 'NS', ".ToDBString($dflt["nameserver"]).")";
                                $mdb2->exec($sql);
                                break;

                            case "MX":
                                if (empty($dflt["priority"]))
                                {
                                    $dflt["priority"] = 10;
                                }

                                $sql = "INSERT INTO $cfg_db_table (zone,host,type,data,mx_priority)
		        VALUES (".ToDBString($zone).", '@', 'MX',
			  ".ToDBString($dflt["name"]).", ".ToDBString($dflt["priority"]).")";
                                $mdb2->exec($sql);
                                break;

                            case "CNAME":
                                $sql = "INSERT INTO $cfg_db_table (zone,host,type,data)
		        VALUES (".ToDBString($zone).", ".ToDBString($dflt["name"]).", 'CNAME',
			  ".ToDBString($dflt["target"]).")";
                                $mdb2->exec($sql);
                                break;

                            case "TXT":
                                $sql = "INSERT INTO $cfg_db_table (zone,host,type,data)
		        VALUES (".ToDBString($zone).", ".ToDBString($dflt["name"]).", 'TXT',
			  ".ToDBString($dflt["text"]).")";
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
                $sql_string = "INSERT INTO $cfg_db_table (zone,host,type) VALUES (".ToDBString($zone) . ",'@',UCASE(" . ToDBString($rtype) . "));";
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

function delZone($sValue) {
    global $cfg_db_table;
    global $mdb2;
    $errText = "";
    $sValue_array = explode("~~|~~", $sValue);
    $zone = $sValue_array[0];
    if (zoneExists($zone)) {
        $sql =& $mdb2->exec("delete from $cfg_db_table where zone=".ToDBString($zone).";");
        $err = $sql->getMessage;
    } else {
        $errText .= "Cannot delete zone: Zone does not exist.  (zone: ".$zone.")\r\n";
    }
    // create string to return to the page
    if ($err != 0) {
        $errText .= "SQL Error: " . $sql->getMessage . "\r\n";
    }
    $typehtml = "";
    $newtext = '~~|~~~~|~~' . $errText;
    return $newtext;

}

function delRecord($sValue) {
    global $cfg_db_table;
    global $mdb2;
    $errText = "";
    $sValue_array = explode("~~|~~", $sValue);
    $zone = $sValue_array[0];
    $rtype = $sValue_array[1];
    $rtype = strtoupper($rtype);
    $id = $sValue_array[2];
    if (idExists($id)) {
        $sql =& $mdb2->exec("delete from $cfg_db_table where id=".ToDBString($id,true).";");
//    $err = $sql->getMessage;
    } else {
        $errText .= "Record id does not exist.  (id: ".$id.")\r\n";
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

function zoneExists($zone) {
    global $cfg_db_table;
    global $mdb2;
    $sql =& $mdb2->query("select id from $cfg_db_table where type='SOA' and zone=".ToDBString($zone).";");

//  $err = $sql->getMessage();
    $rec = $sql->numRows();
    return $rec > 0;
}

function isValidType($rtype) {
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

function idExists($id) {
    global $cfg_db_table;
    global $mdb2;
    $sql =& $mdb2->query("select id from $cfg_db_table where id=".ToDBString($id,true).";");
//  $err = $sql->getMessage();
    $rec = $sql->numRows();
    return $rec > 0;
}

function getZone($zone) {
    if (zoneExists($zone)) {
        $html .= "<table class=\"editable_table\" border=\"0\">\n";
        $html .= "<tr class=\"yellow\"><th>Edit Zone [". $zone . "]</th></tr>\n";
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
function getSOARecord($zone) {
    global $cfg_db_table;
    global $mdb2;
    // the TR string
    $table = "";
    // query DB
    $sql =& $mdb2->query("SELECT id,host,data,resp_person,refresh,retry,expire,minimum FROM $cfg_db_table WHERE type='SOA' AND zone=".ToDBString($zone)."");
    // build table
    $table .= "<table id=\"tbl_soa_records\" class=\"editable_table\">";
    $table .= "<tr class=\"yellow\"><td class=\"row_head\"><strong>SOA</strong></td><td>MNAME</td><td>RNAME</td><td>Refresh</td><td>Retry</td><td>Expire</td><td>Minimum</td></tr>\n";
    while($row = $sql->fetchRow(MDB2_FETCHMODE_ASSOC)){
        stripslashes(extract($row));
        if (empty($data)) $data = $GLOBALS["DEFAULTVAL"];

        $table .= "<tr><td class=\"row_head\" align=\"right\"><!-- <span class=\"del\" onclick=\"delRecord('$zone', 'SOA', $id);\">-</span>//--></td>\n";
        $table .= "<td class=\"point\" id=\"".$id."__data\" onmouseover=\"bgSwitch('on', this, 'Modify Zone Primary Master Server' );\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "  <div onclick=\"editCell('".$id."__data', this);\">".$data."</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point\" id=\"".$id."__resp_person\" onmouseover=\"bgSwitch('on', this, 'Modify Zone Responsible Person');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "  <div onclick=\"editCell('".$id."__resp_person', this);\">".$resp_person."</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point\" id=\"".$id."__refresh\" onmouseover=\"bgSwitch('on', this, 'Refresh determines the number of seconds between a successful check on the serial number on the zone of the primary, and the next attempt. Usually around 2-24 hours. Not used by a primary server.');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "  <div onclick=\"editCell('".$id."__refresh', this);\">".$refresh."</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point\" id=\"".$id."__retry\" onmouseover=\"bgSwitch('on', this, 'If a refresh attempt fails, a server will retry after this many seconds. Not used by a primary server.');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "  <div onclick=\"editCell('".$id."__retry', this);\">".$retry."</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point\" id=\"".$id."__expire\" onmouseover=\"bgSwitch('on', this, 'Measured in seconds. If the refresh and retry attempts fail after that many seconds the server will stop serving the zone. Typical value is 1 week. Not used by a primary server.');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "  <div onclick=\"editCell('".$id."__expire', this);\">".$expire."</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point\" id=\"".$id."__minimum\" onmouseover=\"bgSwitch('on', this, 'The default TTL for every record in the zone. Can be overidden for any particular record. Typical values range from eight hours to four days. When changes are being made to a zone, often set at ten minutes or less.');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "  <div onclick=\"editCell('".$id."__minimum', this);\">".$minimum."</div>\n";
        $table .= "</td>\n";
        $table .= "</tr>\n";
    }
    $table .= "</table>\n";
    return $table;
}

function getARecords($zone) {
    global $cfg_db_table;
    global $mdb2;
    // the TR string
    $table = "";
    // query DB
    $sql =& $mdb2->query("SELECT id,host,data FROM $cfg_db_table WHERE type='A' AND zone=".ToDBString($zone)." ORDER BY host");
    // build table
    $table .= "<table id=\"tbl_a_records\" class=\"editable_table\">";
    $table .= "<tr class=\"yellow\">\n";
    $table .= "<td class=\"row_head\"><strong><span class=\"add\" onclick=\"addRecord('$zone', 'A');\" onmouseover=\"bgSwitch('on', this, 'Add A Record');\" onmouseout=\"bgSwitch('off', this, '');\"\"><img src=\"add.png\" border=0 alt=\"Add Record\" /></span> A</strong></td>\n";
    $table .= "<td>Name</td>\n";
    $table .= "<td>IP Address</td>\n";
    $table .= "</tr>\n";
    while($row = $sql->fetchRow(MDB2_FETCHMODE_ASSOC)){
        stripslashes(extract($row));
        if (empty($data)) $data = $GLOBALS["DEFAULTVAL"];
        if (empty($host)) $host = $GLOBALS["DEFAULTVAL"];

        $table .=  "<tr>\n<td class=\"row_head\" align=\"right\"><span class=\"del\" onclick=\"delRecord('$zone', 'A', $id);\"><img src=\"delete.png\" border=0 alt=\"Delete Record\" /></span></td>\n";
        $table .= "<td class=\"point\" id=\"".$id."__host\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record Host');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "<div onclick=\"editCell('".$id."__host', this);\">".$host."</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point\" id=\"".$id."__data\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record IP');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .=  "<div onclick=\"editCell('".$id."__data', this);\">".$data."</div>\n";
        $table .= "</td>\n</tr>\n";
    }
    $table .= "</table>\n";
    return $table;
}

function getCNAMERecords($zone) {
    global $cfg_db_table;
    global $mdb2;
    // the TR string
    $table = "";
    // query DB
    $sql =& $mdb2->query("SELECT id,host,data FROM $cfg_db_table WHERE type='CNAME' AND zone=".ToDBString($zone)." ORDER BY host");
    // build table
    $table .= "<table id=\"tbl_cname_records\" class=\"editable_table\">";
    $table .= "<tr class=\"yellow\">\n";
    $table .= "<td class=\"row_head\"><strong><span class=\"add\" onclick=\"addRecord('$zone', 'CNAME');\" onmouseover=\"bgSwitch('on', this, 'Add CNAME Record');\" onmouseout=\"bgSwitch('off', this, '');\"\"><img src=\"add.png\" border=0 alt=\"Add Record\" /></span> CNAME</strong></td>\n";
    $table .= "<td>Name</td>\n";
    $table .= "<td>Canonical Name</td>\n";
    $table .= "</tr>\n";
    while($row = $sql->fetchRow(MDB2_FETCHMODE_ASSOC)){
        stripslashes(extract($row));
        if (empty($data)) $data = $GLOBALS["DEFAULTVAL"];

        $table .=  "<tr>\n<td class=\"row_head\" align=\"right\"><span class=\"del\" onclick=\"delRecord('$zone', 'CNAME', $id);\"><img src=\"delete.png\" border=0 alt=\"Delete Record\" /></span></td>\n";
        $table .= "<td class=\"point\" id=\"".$id."__host\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record Host');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "<div onclick=\"editCell('".$id."__host', this);\">".$host."</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point\" id=\"".$id."__data\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record IP');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .=  "<div onclick=\"editCell('".$id."__data', this);\">".$data."</div>\n";
        $table .= "</td>\n</tr>\n";
    }
    $table .= "</table>\n";
    return $table;
}

function getTXTRecords($zone) {
    global $cfg_db_table;
    global $mdb2;
    // the TR string
    $table = "";
    // query DB
    $sql =& $mdb2->query("SELECT id,host,data FROM $cfg_db_table WHERE type='TXT' AND zone=".ToDBString($zone)." ORDER BY host");
    // build table
    $table .= "<table id=\"tbl_txt_records\" class=\"editable_table\">";
    $table .= "<tr class=\"yellow\">\n";
    $table .= "<td class=\"row_head\"><strong><span class=\"add\" onclick=\"addRecord('$zone', 'TXT');\" onmouseover=\"bgSwitch('on', this, 'Add TXT Record');\" onmouseout=\"bgSwitch('off', this, '');\"\"><img src=\"add.png\" border=0 alt=\"Add Record\" /></span> TXT</strong></td>\n";
    $table .= "<td>Name</td>\n";
    $table .= "<td>Text</td>\n";
    $table .= "</tr>\n";
    while($row = $sql->fetchRow(MDB2_FETCHMODE_ASSOC)){
        stripslashes(extract($row));
        if (empty($data)) $data = $GLOBALS["DEFAULTVAL"];

        $table .=  "<tr>\n<td class=\"row_head\" align=\"right\"><span class=\"del\" onclick=\"delRecord('$zone', 'TXT', $id);\"><img src=\"delete.png\" border=0 alt=\"Delete Record\" /></span></td>\n";
        $table .= "<td class=\"point\" id=\"".$id."__host\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record Host');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "<div onclick=\"editCell('".$id."__host', this);\">".$host."</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point\" id=\"".$id."__data\" onmouseover=\"bgSwitch('on', this, 'Modify Address Record IP');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .=  "<div onclick=\"editCell('".$id."__data', this);\">".$data."</div>\n";
        $table .= "</td>\n</tr>\n";
    }
    $table .= "</table>\n";
    return $table;
}

function getMXRecords($zone) {
    global $cfg_db_table;
    global $mdb2;
    // the TR string
    $table = "";
    // query DB
    $sql =& $mdb2->query("SELECT id,mx_priority,data FROM $cfg_db_table WHERE type='MX' AND zone=".ToDBString($zone)." ORDER BY mx_priority");
    // build table
    $table .= "<table id=\"tbl_mx_records\" class=\"editable_table\">";
    $table .= "<tr class=\"yellow\">\n";
    $table .= "<td class=\"row_head\"><strong><span class=\"add\" onclick=\"addRecord('$zone', 'MX');\" onmouseover=\"bgSwitch('on', this, 'Add MX Record');\" onmouseout=\"bgSwitch('off', this, '');\"\"><img src=\"add.png\" border=0 alt=\"Add Record\" /></span> MX</strong></td>\n";
    $table .= "<td>Name</td>\n";
    $table .= "<td>Priority</td>\n";
    $table .= "</tr>\n";
    while($row = $sql->fetchRow(MDB2_FETCHMODE_ASSOC)){
        stripslashes(extract($row));
        if (empty($data)) $data = $GLOBALS["DEFAULTVAL"];
        if (empty($mx_priority)) $mx_priority = $GLOBALS["DEFAULTVAL"];

        $table .=  "<tr>\n<td class=\"row_head\" align=\"right\"><span class=\"del\" onclick=\"delRecord('$zone', 'MX', $id);\"><img src=\"delete.png\" border=0 alt=\"Delete Record\" /></span></td>\n";
        $table .= "<td class=\"point\" id=\"".$id."__data\" onmouseover=\"bgSwitch('on', this, 'Modify Mail Exchange Name');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .=  "<div onclick=\"editCell('".$id."__data', this);\">".$data."</div>\n";
        $table .= "</td>\n";
        $table .= "<td class=\"point_small\" id=\"".$id."__mx_priority\" onmouseover=\"bgSwitch('on', this, 'Modify Mail Exchange Priority');\" onmouseout=\"bgSwitch('off', this);\">\n";
        $table .= "<div onclick=\"editCell('".$id."__mx_priority', this);\">".$mx_priority."</div>\n";
        $table .= "</td>\n</tr>\n";
    }
    $table .= "</table>\n";
    return $table;
}
function getNSRecords($zone) {
    global $cfg_db_table;
    global $mdb2;
    // the TR string
    $table = "";
    // query DB
    $sql =& $mdb2->query("SELECT id,data FROM $cfg_db_table WHERE type='NS' AND zone=".ToDBString($zone)." order by DATA");
    // build table
    $table .= "<table id=\"tbl_nsrecords\" class=\"editable_table\">";
    $table .= "<tr class=\"yellow\">\n";
    $table .= "<td class=\"row_head\"><strong><span class=\"add\" onclick=\"addRecord('$zone', 'NS');\"  onmouseover=\"bgSwitch('on', this, 'Add Nameserver Record');\" onmouseout=\"bgSwitch('off', this, '');\"><img src=\"add.png\" border=0 alt=\"Add Record\" /></span> NS</strong></td>\n";
    $table .= "<td>Nameserver</td>\n";
    $table .= "</tr>\n";
    while($row = $sql->fetchRow(MDB2_FETCHMODE_ASSOC)){
        stripslashes(extract($row));
        if (empty($data)) $data = $GLOBALS["DEFAULTVAL"];

        $table .=  "<tr>\n<td class=\"row_head\" align=\"right\"><span class=\"del\" onclick=\"delRecord('$zone', 'NS', $id);\"  onmouseover=\"bgSwitch('on', this, 'Delete Nameserver Record');\" onmouseout=\"bgSwitch('off', this, '');\"><img src=\"delete.png\" border=0 alt=\"Delete Record\" /></span></td>\n";
        $table .= "<td class=\"point\" id=\"".$id."__data\" onmouseover=\"bgSwitch('on', this, 'Modify Nameserver Record');\" onmouseout=\"bgSwitch('off', this, '');\">\n";
        $table .=  "<div onclick=\"editCell('".$id."__data', this);\">".$data."</div>\n";
        $table .= "</td>\n</tr>\n";
    }
    $table .= "</table>\n";
    return $table;
}
function getZoneList() {
    global $cfg_db_table;
    global $user;
    global $mdb2;
// the TR string
    $table = "";
// query DB

    if(!empty($user)) {
        $sql =& $mdb2->query("SELECT DISTINCT zone,id FROM $cfg_db_table WHERE type='SOA' and owner='$user' order by zone asc");
    }
    else {
        $sql =& $mdb2->query("SELECT DISTINCT zone,id FROM $cfg_db_table WHERE type='SOA' order by zone asc");
    }
// build table
    while($row = $sql->fetchRow(MDB2_FETCHMODE_ASSOC)){
        stripslashes(extract($row));
        if (empty($data)) $data = $GLOBALS["DEFAULTVAL"];

        $table .=  "<tr><td class=\"point_del\"><span class=\"del\" onclick=\"javascript:delZone('".$zone."');\" onmouseover=\"bgSwitch('on', this, 'Delete Zone: ".$zone."');\" onmouseout=\"bgSwitch('off', this);\"><img src=\"delete.png\" border=\"0\" alt=\"Delete Zone\" /></span></td>\n";
        $table .= "<td class=\"point\" id=\"".$id."__zone\" onmouseover=\"bgSwitch('on', this, 'Edit Zone: ".$zone."');\" onmouseout=\"bgSwitch('off', this, '');\">\n";
        $table .=  "<div onclick=\"editZone('".$zone."');\">".$zone."</div>\n";
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

<html>
<head>
    <link href='http://fonts.googleapis.com/css?family=Roboto+Condensed' rel='stylesheet' type='text/css'>
    <title>dnsEditor: Ajaxified</title>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    <meta http-equiv="Content-Language" content="en-us" />
    <meta name="ROBOTS" content="ALL" />
    <meta http-equiv="imagetoolbar" content="no" />
    <meta name="MSSmartTagsPreventParsing" content="true" />
    <meta name="Copyright" content="(c) 2005 Copyright content & design: Lokkju, Inc" />
    <meta name="Keywords" content="dns ajax edit bind" />
    <meta name="Description" content="Ajax based edit in place DNS Record configuration system" />
    <!-- (c) Copyright 2005 by Lokkju, Inc All Rights Reserved. -->
    <meta http-equiv="X-UA-Compatible" content="chrome=1">
    <link href="dnsEditor.css" rel="stylesheet" type="text/css" media="all" />

    <!-- KLUDGE:: Win IE 5 -->
    <!-- corrects the unsightly Flash of Unstyled Content. See http://www.bluerobot.com/web/css/fouc.asp for more info -->
    <script type="text/javascript"></script>
    <!-- END KLUDGE:: -->
    <script src="filtertable.js" type="text/javascript" language="javascript" charset="utf-8"></script>
    <script type="text/javascript">
        <?php
          sajax_show_javascript();
        ?>
        function trim(str) {
            str = " " + str + " ";
            return str.replace(/^\s+/g, '').replace(/\s+$/g, '');
        }
        function textChanger_cb(result) {
            var result_array=result.split("~~|~~");
            if (result_array[2]) {
                alert(result_array[2]);
            }
            document.getElementById(result_array[1]).innerHTML = result_array[0];
            Fat.fade_element(result_array[1], 30, 1500, "#EEFCC5", "#FFFFFF")
        }
        function zoneChanger_cb(result) {
            var result_array=result.split("~~|~~");
            if (result_array[2]) {
                alert(result_array[2]);
            }
            document.getElementById('zoneinfo').innerHTML = result_array[0];
        }
        function getZoneList_cb(result) {
            var result_array=result.split("~~|~~");
            if (result_array[2]) {
                alert(result_array[2]);
            }
            document.getElementById('zonemenu_filterable').innerHTML = result_array[0];
        }

        function addBatchRecords_cb(result)
        {
            var resArray = result.split("~~|~~");
            if (resArray[2])
            {
                alert(resArray[2]);
            }
            var num = parseInt(resArray[0]);
            var failed = parseInt(resArray[1]);

            var msg = [], msgI = 0;
            if (num > 0)
            {
                msg[msgI++] = "Successfully added " + num + " zones.";
                refreshZones();
            }

            if (failed > 0)
            {
                msg[msgI++] = "Failed adding " + failed + " zones.";
            }

            if (msgI > 0)
            {
                msg = msg.join(' ');
                alert(msg);
            }

            document.getElementById("batchzones").style.display = "block";
            document.getElementById("batchbtn").style.display = "inline";
            document.getElementById("batchloader").style.display = "none";
        }

        function addRecord_cb(result) {
            var result_array=result.split("~~|~~");
            if (result_array[2]) {
                alert(result_array[2]);
            }
            switch(result_array[0]) {
                case "NS":
                    document.getElementById('div_ns_records').innerHTML = result_array[1];
                    break;
                case "MX":
                    document.getElementById('div_mx_records').innerHTML = result_array[1];
                    break;
                case "A":
                    document.getElementById('div_a_records').innerHTML = result_array[1];
                    break;
                case 'CNAME':
                    document.getElementById('div_cname_records').innerHTML = result_array[1];
                    break;
                case 'TXT':
                    document.getElementById('div_txt_records').innerHTML = result_array[1];
                    break;
                case 'SOA':
                    refreshZones();
                    break;
            }
            document.getElementById("addbtn").style.display = "inline";
            document.getElementById("addloader").style.display = "none";
        }
        function delZone_cb(result) {
            var result_array=result.split("~~|~~");
            if (result_array[2]) {
                alert(result_array[2]);
            }
            refreshZones();
        }
        function delRecord_cb(result) {
            var result_array=result.split("~~|~~");
            if (result_array[2]) {
                alert(result_array[2]);
            }
            switch(result_array[0]) {
                case "NS":
                    document.getElementById('div_ns_records').innerHTML = result_array[1];
                    break;
                case "MX":
                    document.getElementById('div_mx_records').innerHTML = result_array[1];
                    break;
                case "A":
                    document.getElementById('div_a_records').innerHTML = result_array[1];
                    break;
                case 'CNAME':
                    document.getElementById('div_cname_records').innerHTML = result_array[1];
                    break;
                case 'TXT':
                    document.getElementById('div_txt_records').innerHTML = result_array[1];
                    break;
                case "SOA":
                    refreshZones();
                    break;
            }
        }
        function updateServers_cb(result) {
            var result_array=result.split("~~|~~");
            if (result_array[1]) {
                alert(result_array[1]);
            }
            alert(result_array[0]);
            document.getElementById('btn_updateServers').value='Update Servers';
        }

        function parseForm(cellID, inputID) {
            var temp = trim(document.getElementById(inputID).value);
            var obj = /^(\s*)([\W\w]*)(\b\s*$)/;
            if (obj.test(temp)) { temp = temp.replace(obj, '$2'); }
            var obj = /  /g;
            while (temp.match(obj)) { temp = temp.replace(obj, " "); }
            if (temp == " ") { temp = ""; }
            if (! temp) {alert("This field must contain at least one non-whitespace character.");return;}
            var st = trim(document.getElementById(inputID).value) + '~~|~~' + cellID;
            document.getElementById(cellID).innerHTML = "<div class=\"update\"><img src=\"loadersmall.gif\"/> Updating...</div>";
            x_changeText(st, textChanger_cb);
            document.getElementById(cellID).style.border = 'none';
        }
        function editCell(id, cellSpan) {
            var oldCellSpan = trim(cellSpan.innerHTML);
            var inputWidth = cellSpan.offsetWidth + 5;
            if (oldCellSpan == "<?php $GLOBALS["DEFAULTVAL"]?>") oldCellSpan = "";
            document.getElementById(id).innerHTML = "<div id=\"" + id + "span\"><form name=\"activeForm\" onsubmit=\"parseForm('"+id+"', '"+id+"input');return false;\" style=\"margin:0;\" action=\"\"><input type=\"text\" class=\"dynaInput\" id=\""+id+"input\" style=\"font: 12px Verdana; width: "+ inputWidth + "px\" onblur=\"parseForm('"+id+"', '"+id+"input');return false;\"><br /><noscript><input value=\"OK\" type=\"submit\"></noscript></form></div>";
            document.getElementById(id+"input").value = oldCellSpan;
            document.getElementById(id).style.background = '#ffc';
            document.getElementById(id+"span").style.border = '1px solid #fc0';
            document.getElementById(id+"input").focus(); // for some reason, two focus calls are needed - no idea why?  perhaps one to render, and the other to focus?
            document.getElementById(id+"input").focus();
        }
        function editZone(zone) {
            x_changeZone(trim(zone), zoneChanger_cb);
            document.getElementById('zoneinfo').innerHTML = "<div id='zone_edit_msg'><strong>Retrieving Zone [" + zone + "]...</strong></div>";
        }
        function addRecord(zone,rtype) {
            var st = trim(zone) + "~~|~~" + trim(rtype);
            document.getElementById("addbtn").style.display = "none";
            document.getElementById("addloader").style.display = "inline";
            x_addRecord(st,addRecord_cb);
        }
        function addBatchRecords(zones)
        {
            document.getElementById("batchzones").style.display = "none";
            document.getElementById("batchbtn").style.display = "none";
            document.getElementById("batchloader").style.display = "inline";
            x_addBatchRecords(zones, addBatchRecords_cb);
        }
        function addZone() {
            var zone = prompt("Please enter the domain name to add:","");
            if ((zone==null)||(zone.length==0)) {
                alert("Invalid Zone");
                return false;
            }
            addRecord(zone,"SOA");
        }
        function addZones() {
            var zone = document.getElementById("batchzones").value;
            if ((zone==null)||(zone.length==0)) {
                alert("Please enter zones, one per line.");
                return false;
            }
            addBatchRecords(zone);
        }
        function delZone(zone) {
            var answer = confirm ("You are about to delete all records for " + zone + "\r\nAre your sure you want to delete ALL entries for this zone?")
            if (answer) {
                var st = trim(zone);
                x_delZone(st,delZone_cb);
            }
        }
        function refreshZones() {
            document.getElementById('zonemenu_filterable').innerHTML = "<tr><td><img src=\"loadersmall.gif\"/> Updating...</td></tr>";
            document.getElementById('zoneinfo').innerHTML = "<div id='zone_edit_msg'><strong>Select a zone.</strong></div>";
            x_getZoneList("",getZoneList_cb);
        }
        function delRecord(zone,rtype,id) {
            var answer = confirm ("Are your sure you want to delete this record?")
            if (answer) {
                var st = trim(zone) + "~~|~~" + trim(rtype) + '~~|~~' + trim(id);
                x_delRecord(st,delRecord_cb);
            }
        }
        function bgSwitch(ac, td, st) {
            if (ac == 'on'){
                if (td.tagName == "TD") td.style.background = '#ffc';
                if (st) mys(st);
            } else if (ac == 'off'){
                if (td.tagName == "TD") td.style.background = '#ffffff';
                mys('');
            }
        }
        function mys(s) {
            if (s==null || s==''){
                document.getElementById("status_div_over").innerHTML = "";
            } else {
                document.getElementById("status_div_over").innerHTML = s;
            }
        }
    </script>
    <script type="text/javascript" src="fat.js"></script>
    <style type="text/css">

    </style>
    <!-- <base href="" /> Breaks in IE -->

</head>
<body id="thebody" onload="">
<!-- preload the small ajax loader, since it's dynamically created -->
<img id="preload1" src="loadersmall.gif" style="display:none"/>
<div id="zonelist">
    <div id="zonelist_top">
        <table class="" border="0" width="100%">
            <tr>
                <th colspan="1" align="left">Zone List&nbsp;&nbsp;<a href="javascript:refreshZones();"><img src="refresh.png" border=0 alt="refresh" onmouseover="bgSwitch('on', this, 'Refresh Zone List');" onmouseout="bgSwitch('off', this, '');"/></a></th>
                <td align="right">
                    <img src="loader.gif" style="display:none" id="addloader"/>
                    <input type="button" class="cmdBtn" id="addbtn" value="Add Zone" onClick="javascript:addZone();" onmouseover="bgSwitch('on', this, 'Add a new Zone');" onmouseout="bgSwitch('off', this, '');"/>
                    <?php
                    if (@$cfg_updateservers)
                    {
                        ?>
                        <br><input class="cmdBtn" type="button" value="Update Servers" name="btn_updateServers" id="btn_updateServers" onclick="javascript:this.value='<img src=\"loadersmall.gif\"/> Updating...';x_updateServers('',updateServers_cb);" onmouseover="bgSwitch('on', this, 'Syncronize all DNS Servers');" onmouseout="bgSwitch('off', this, '');" />
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
                           onClick="javascript:addZones();" onmouseover="bgSwitch('on', this, 'Add batch Zones');" onmouseout="bgSwitch('off', this, '');"/>
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
    <div id="zonelist_filter_div"  onmouseover="bgSwitch('on', this, 'Filter zones based on text string');" onmouseout="bgSwitch('off', this, '');">
        Filter: <input name="zonemenu_filterable_filter" id="zonemenu_filterable_filter" type="text" value="" size="10" maxlength="10"/>
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
<div id="config_properties">
    Configuration
</div>
<div id="dashboard_view">
    Dashboard Status
</div>
<div id="domain_view">
    Domain Information
</div>
<div id="zoneinfo">Select a zone from the menu on the left.
    <div id="zone_edit_msg"></div>
</div>
</div>
</body>
</html>
