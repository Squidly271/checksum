<?PHP
#################################################
##                                             ##
##  Checksum.  Copyright 2015, Andrew Zawadzki ##
##                                             ##
#################################################



$plugin="checksum";
$checksumPaths['usbSettings']       = "/boot/config/plugins/$plugin/settings/settings.json";
$checksumPaths['tmpSettings']       = "/tmp/checksum/temp.json";
$checksumPaths['Settings']          = "/var/local/emhttp/plugins/$plugin/settings.json";
$checksumPaths['Waiting']           = "/tmp/checksum/waiting";
$checksumPaths['Parity']            = "/tmp/checksum/parity";
$checksumPaths['VerifyParity']      = "/tmp/checksum/verifyparity";
$checksumPaths['Running']           = "/tmp/checksum/running";
$checksumPaths['Paranoia']          = "/tmp/checksum/paranoia";
$checksumPaths['Mover']             = "/tmp/checksum/mover";
$checksumPaths['Log']               = "/tmp/checksum/log.txt";
$checksumPaths['FailureLog']        = "/tmp/checksum/failurelog.txt";
$checksumPaths['Global']            = "/var/local/emhttp/plugins/$plugin/global.json";
$checksumPaths['usbGlobal']         = "/boot/config/plugins/$plugin/settings/global.json";
$checksumPaths['Schedule']          = "/boot/config/plugins/$plugin/settings/schedule.json";

$unRaidPaths['Variables']           = "/var/local/emhttp/var.ini";

$scriptPaths['InitializeWatch']     = "/usr/local/emhttp/plugins/$plugin/scripts/checksumInotify.php";
$scriptPaths['CreateWatch']         = "/usr/local/emhttp/plugins/$plugin/scripts/checksumInotify.sh";
$scriptPaths['b2sum']               = "/usr/local/emhttp/plugins/$plugin/include/b2sum";
$scriptPaths['MonitorWatch']        = "/usr/local/emhttp/plugins/$plugin/scripts/checksumInotify1.sh";
$scriptPaths['inotifywait']         = "/usr/bin/inotifywait";
$scriptPaths['checksuminotifywait'] = "/tmp/checksum/checksum_inotifywait";
$scriptPaths['RemoveCron']          = "/usr/local/emhttp/plugins/$plugin/scripts/checksumRemoveCron.php";
$scriptPaths['UpdateCron']          = "/usr/local/emhttp/plugins/$plugin/scripts/checksumUpdateCron.php";

if ( ! is_dir(pathinfo($checksumPaths['tmpSettings'],PATHINFO_DIRNAME)) )
{
  exec("mkdir -p ".pathinfo($checksumPaths['tmpSettings'],PATHINFO_DIRNAME));
}

if ( ! is_dir(pathinfo($checksumPaths['Settings'],PATHINFO_DIRNAME)) )
{
  exec("mkdir -p ".pathinfo($checksumPaths['Settings'],PATHINFO_DIRNAME));
}

if ( ! is_dir(pathinfo($checksumPaths['usbSettings'],PATHINFO_DIRNAME)) )
{
  exec("mkdir -p ".pathinfo($checksumPaths['usbSettings'],PATHINFO_DIRNAME));
}

if ( ! is_dir("/boot/config/plugins/checksum/logs") )
{
  mkdir("/boot/config/plugins/checksum/logs",0777,true);
}

if ( ! is_dir("/boot/config/plugins/checksum/settings") )
{
  mkdir("/boot/config/plugins/checksum/settings",0777,true);
}

if ( is_file("/boot/config/plugins/checksum/settings.json") )
{
  rename("/boot/config/plugins/checksum/settings.json",$checksumPaths['usbSettings']);
}

if ( is_file("/boot/config/plugins/checksum/global.json") )
{
  rename("/boot/config/plugins/checksum/global.json",$checksumPaths['usbGlobal']);
}


function logger($string, $newLine = true)
{
  global  $checksumPaths, $globalSettings;
  if ( $newLine )
  {
    $string = date("M j Y H:i:s  ").$string;
  }

  if ( @filesize($checksumPaths['Log']) > 500000 )
  {
    $string = "Log size > 500,000 bytes.  Restarting\nIf this is the last line displayed on the log window, you will have to close and reopen the log window".$string;
    file_put_contents($checksumPaths['Log'],$string,FILE_APPEND);
    if ( $globalSettings['LogSave'] )
    {
      $saveLogName = "/boot/config/plugins/checksum/logs/Command-".date("Y-m-d H-i-s").".txt";
      $saveLogText = file_get_contents($checksumPaths['Log']);
      $saveLogText = str_replace("\n","\r\n",$saveLogText);
      file_put_contents($saveLogName,$saveLogText);
    }
    unlink($checksumPaths['Log']);
  }
  file_put_contents($checksumPaths['Log'],$string,FILE_APPEND);
}


function createShare($i,$settings = false)
{
  $runSetting = "";
  if ( ! $settings )
  {
    $settings = array();

    $settings['Path'] = "";
    $settings['Index'] = $i;
    $settings['Separate'] = false;
    $settings['Parity'] = false;
    $settings['Changed'] = true;
    $settings['Algorithm'] = "md5";
    $settings['IncludeAll'] = true;
    $settings['Monitor'] = true;
    $settings['Include'] = "";
    $settings['Exclude'] = "";

    $runSetting = "disabled";
  }
  $includedFiles = $settings['Include'];
  $excludedFiles = $settings['Exclude'];
  $directories = array();
  if ( is_dir("/mnt/user") )
  {
    $directories = array_diff(scandir("/mnt/user"),array(".",".."));
  }

  $userShares[] = "";

  foreach ($directories as $folder)
  {
    if ( is_dir("/mnt/user/$folder") )
    {
      if ( strcasecmp("appdata",$folder) != 0 )
      {
        $userShares[$folder] = $folder;
      }
    }
  }

  $t = "<table><tr><td>";
  $t .= "<b>Share To Monitor: </b>";
  $t .= "</td><td><select id='share$i' size='1' onchange='shareChanged($i);'>";

  $folder = basename($settings['Path']);

  $flag = false;
  foreach ( $userShares as $share)
  {
    if ( $share == "" )
    {
      $t .= "<option value='undefined' selected disabled>Select A User Share To Include</option";
    }
    if ( $share == $folder )
    {
      $t .= "<option value='$share' selected>$share</option>";
      $flag = true;
    } else {
      $t .= "<option value='$share'>$share</option>";
    }
  }
  if ( $flag )
  {
    $t .= "<option value='***'>Custom</option>";
  } else {
    $t .= "<option value='***' selected>Custom</option>";
  }

  $t .= "</select>";

  $t .= "<br><br>";

  if ( $flag )
  {
    $t .= "<input type='text' id='custom$i' class='narrow' disabled oninput='validatePath($i);'></input>";
  } else {
    $t .= "<input type='text' id='custom$i' class='narrow' value='".$settings['Path']."' oninput='validatePath($i);'></input>";
  }
  $t .= "<br><br><span id='patherror$i'></span><br>";
  $t .= "<span id='includeerror$i'></span>";
  $t .= "</td><td><table><tr>";

  $t .= "<td><b>Algorithm to use: </b></td>";
  $t .= "<td><select id='algorithm$i' onchange='enableApply($i);'>";

  foreach ( array("md5","sha1","sha256","blake2") as $algorithm)
  {
    $algorithmComment = "";
    if ( $algorithm == "sha256" )
    {
      $algorithmComment = "  (* see help)";
    }

    if ( $settings['Algorithm'] == $algorithm )
    {
      $t .= "<option value='$algorithm' selected>$algorithm$algorithmComment</option>";
    } else {
      $t .= "<option value='$algorithm'>$algorithm$algorithmComment</option>";
    }
  }
  $t .= "</select></td>";

  $t .= "<td><b>Update Changed Files: </b>";
  $t .= "</td><td><select id='update$i' onchange='enableApply($i);'>";
  if ( $settings['Changed'] )
  {
    $t .= "<option value='yes' selected>Yes</option>";
    $t .= "<option value='no'>No</option>";
  } else {
    $t .= "<option value='yes'>Yes</option>";
    $t .= "<option value='no' selected>No</option>";
  }
  $t .= "</select></td>";

  $t .= "</tr>";
  $t .= "<tr><td>";
  $t .= "<b>Single checksum file per folder?</b>";
  $t .= "</td><td><select id='separate$i' onchange='enableApply($i);'>";
  if ( $settings['Separate'] )
  {
    $t .= "<option value='yes'>Yes</option>";
    $t .= "<option value='no' selected>No</option>";
  } else {
    $t .= "<option value='yes' selected>Yes</option>";
    $t .= "<option value='no'>No</option>";
  }
  $t .= "</select>";
  $t .= "</td>";

  $t .= "<td><b>Extension for checksum file:</b></td>";
  $t .= "<td><select id='extension$i' onchange='enableApply($i);'>";
  if ( $settings['Extension'] == ".hash" )
  {
    $t .= "<option value='.hash' selected>.hash</option>";
    $t .= "<option value='algorithm'>checksum algorithm</option>";
  } else {
    $t .= "<option value='.hash'>.hash</option>";
    $t .= "<option value='algorithm' selected>checksum algorithm</option>";
  }
  $t .= "</select></td></tr>";

  $t .= "<tr><td><b>Include all files</b></td>";
  $t .= "<td><select id='includeall$i' onchange='includeChanged($i);'>";

  $includeFlag = "disabled";
  if ( $settings['IncludeAll'] )
  {
    $t .= "<option value='yes' selected>Yes</option>";
    $t .= "<option value='no'>No</option>";
  } else {
    $t .= "<option value='yes'>Yes</option>";
    $t .= "<option value='no' selected>No</option>";
    $includeFlag = "";
  }
  $t .= "</select></td>";

  $t .= "<td><b>Monitor Folder?</b></td>";
  $t .= "<td><select id='monitor$i' onchange='changeMonitor($i);'>";

  if ( $settings['Monitor'] )
  {
    $t .= "<option value='yes' selected>Yes</option>";
    $t .= "<option value='no'>No</option>";
  } else {
    $t .= "<option value='yes'>Yes</option>";
    $t .= "<option value='no' selected>No</option>";
  }
  $t .= "</select></td></tr>";

  $t .= "<tr><td><b>Included Files:</b></td>";
  $t .= "<td><input type='text' id='included$i' $includeFlag class='narrow' oninput='validateInclude($i);' value='$includedFiles'></input></td>";

  $t .= "<td><b>Excluded Files:</b></td>";
  $t .= "<td><input type='text' id='excluded$i' $includeFlag class='narrow' oninput='validateInclude($i);' value='$excludedFiles'></input></td><tr>";

  $t .= "</tr></table>";

  $t .= "</td></tr></table>";

  $t .= "<center><input type='button' id='apply$i' value='Apply' onclick='apply($i);' disabled></input>";
  $t .= "<input type='button' id='delete$i' value='Delete' onclick='deleteMonitor($i);'></input></center>";

  if ( $runSetting == "disabled" )
  {
  $t .= "<font color='red'> </font>";
  }
  return $t;
}

function buildDisplay($allSettings)
{
  foreach ($allSettings as $Settings)
  {
    $index = $Settings['Index'];
    $output .= createShare($index,$Settings)."<br><hr><br><br>";
  }
  if ( sizeof($allSettings) == 0 )
  {
    $output = "<center><strong>No Monitored Shares Defined</strong></center>";
  }

  return $output;
}

function createHeader()
{
  $output = "<center><font size='3'><b>Creation Settings</b></font></center><br><br><br>";
  return $output;
}

function createFooter()
{
  $output = "<center><input type='button' value='Add Another Share' id='addMonitor' onclick='addMonitor();'></input></center>";
  return $output;
}

function createCron($share,$index,$schedule)
{
  $t = "<table>
        <tr>
          <td>
            <b>How Often To Run:</b>
          </td>
          <td>
            <select id='frequency$share$index' class='narrow' onchange='changeFrequency(&quot;$share&quot;,&quot;$index&quot;);'>
              <option value='never'>Never</option>
              <option value='daily'>Daily</option>
              <option value='weekly'>Weekly</option>
              <option value='monthly'>Monthly</option>
              <option value='yearly'>Yearly</option>
              <option value='custom'>Custom</option>
            </select>
          </td>
        </tr>
        <tr>
          <td>
            <b>Day Of Week:</b>
          </td>
          <td>
            <select id='weekday$share$index' class='narrow' onchange='scheduleApply(&quot;$share&quot;,&quot;$index&quot;);'>
              <option value='0'>Sunday</option>
              <option value='1'>Monday</option>
              <option value='2'>Tuesday</option>
              <option value='3'>Wednesday</option>
              <option value='4'>Thursday</option>
              <option value='5'>Friday</option>
              <option value='6'>Saturday</option>
            </select>
          </td>
          <td>
            <b>Day Of Month</b>
          </td>
          <td>
            <select id='monthday$share$index' class='narrow' onchange='scheduleApply(&quot;$share&quot;,&quot;$index&quot;);'>";

  for ( $day = 1; $day < 32; $day++)
  {
    $t .= "<option value='$day'>$day";

    switch ($day)
    {
      case "1":
        $t .= "st";
        break;
      case "2":
        $t .= "nd";
        break;
      case "3":
        $t .= "rd";
        break;
      case "21":
        $t .= "st";
        break;
      case "22":
        $t .= "nd";
        break;
      case "23":
        $t .= "rd";
        break;
      case "31":
        $t .= "st";
        break;
      default:
        $t .= "th";
        break;
    }
    $t .= "</option>";
  }
  $t .= "<option value='L'>Last Day</option>";
  $t .= "</select>";
  $t .= "</td>
      </tr>
      <tr>
        <td>
          <b>Month:</b>
        </td>
        <td>
          <select id='month$share$index' class='narrow' onchange='scheduleApply(&quot;$share&quot;,&quot;$index&quot;);'>
            <option value='1'>January</option>
            <option value='2'>February</option>
            <option value='3'>March</option>
            <option value='4'>April</option>
            <option value='5'>May</option>
            <option value='6'>June</option>
            <option value='7'>July</option>
            <option value='8'>August</option>
            <option value='9'>September</option>
            <option value='10'>October</option>
            <option value='11'>November</option>
            <option value='12'>December</option>
          </select>
        </td>
      </tr>
      <tr>
        <td>
          <b>Hour:</b>
        </td>
        <td>
          <select id='hour$share$index' class='narrow' onchange='scheduleApply(&quot;$share&quot;,&quot;$index&quot;);'>";
  for ( $hour = 0; $hour < 24; $hour++ )
  {
    $displayHour = $hour;
    if ($displayHour < 12)
    {
      $amPM = "am";
    } else {
      $amPM = "pm";
      $displayHour = $displayHour - 12;
    }
    if ($displayHour == 0)
    {
      $displayHour = 12;
    }

    $t .= "<option value='$hour'>$displayHour$amPM</option>";
  }
  $t .= "</select>";
  $t .= "</td>
         <td>
           <b>Minute:</b>
         </td>
         <td>";

  $t .= "<select id='minute$share$index' class='narrow' onchange='scheduleApply(&quot;$share&quot;,&quot;$index&quot;);'>";
  for ( $minute = 0; $minute < 60; $minute++ )
  {
    $t .= "<option value='$minute'>$minute</option>";
  }
  $t .= "<select>
       </td>
     </tr>
     <tr>
       <td>
         <b>Custom Cron Entry:</b>
       </td>
       <td>
         <input id='custom$share$index' type='text' class='narrow' onchange='scheduleApply(&quot;$share&quot;,&quot;$index&quot;);'></input>
       </td>
       <td>
         <b>Generated Cron Entry:</b>
       </td>
       <td>
         <span id='cron$share$index'></span>
       </td>
     </tr>";

  if ( ($share == "Verify") || ($share == "Disk") )
  {
    $t .= "<tr>
             <td>
               <b>Percent To Verify</b>
             </td>
             <td>
               <input type='number' id='percent$share$index' class='narrow' onchange='scheduleApply(&quot;$share&quot;,&quot;$index&quot;);'></input>
             </td>
             <td>
               <b>Last Scheduled Check:</b>
             </td>
             <td>
               <span id='lastPercent$share$index'></span>
             </td>
           </tr>";
  }
  $t .= "<tr>
           <td>
             <input type='button' id='apply$share$index' value='Apply' disabled onclick='applyFrequency(&quot;$share&quot;,&quot;$index&quot;);'></input>
           </td>
         </tr>
       </table>";

  $t .= "<script>";

  if ( is_array($schedule) )
  {
    $t .= "$('#frequency$share$index').val('".$schedule['Frequency']."');";
    $t .= "$('#weekday$share$index').val('".$schedule['DayOfWeek']."');";
    $t .= "$('#monthday$share$index').val('".$schedule['DayOfMonth']."');";
    $t .= "$('#month$share$index').val('".$schedule['Month']."');";
    $t .= "$('#hour$share$index').val('".$schedule['Hour']."');";
    $t .= "$('#minute$share$index').val('".$schedule['Minute']."');";
    $t .= "$('#custom$share$index').val('".$schedule['Custom']."');";

    if ( $schedule['PercentToCheck'] )
    {
      $t .= "$('#percent$share$index').val('".$schedule['PercentToCheck']."');";
    } else {
      $t .= "$('#percent$share$index').val('10');";
    }
 } else {
    $t .= "$('#percent$share$index').val('10');";
  }

  if ( $schedule['LastStatus'] )
  {
    $lastCheckedLine = "<font color=red>";
  } else {
    $lastCheckedLine = "<font color=green>";
  }

  if ( $schedule['LastChecked'] )
  {
    $lastCheckedLine .= "->".$schedule['LastChecked']."% on ".$schedule['LastCheckedDate']."</font>";
    $t .= "$('#lastPercent$share$index').html('$lastCheckedLine');";
  } else {
    $t .= "$('#lastPercent$share$index').html('<font color=red>Never</font>');";
  }

  $t .= "changeFrequency('$share','$index', true);</script>";


  return $t;
}




######## BEGIN MAIN #############

switch ($_POST['action']) {

case 'inotifywait':
  if ( file_exists($scriptPaths['inotifywait']) )
  {
  } else {
    echo "not installed";
  }
  break;

case 'show_create':

  if ( file_exists($checksumPaths['usbSettings']) )
  {
    copy($checksumPaths['usbSettings'],$checksumPaths['Settings']);
    $shareSettings = json_decode(file_get_contents($checksumPaths['Settings']),true);
  } else {
    $shareSettings = array();
  }

  $output = createHeader();

  $output .= buildDisplay($shareSettings);

  $output .= createFooter();
  echo $output;
  break;

case 'stop_monitor':
  logger("Background Monitor Stopping\n");
  system("/usr/local/emhttp/plugins/checksum/event/stopping_svcs");
  sleep(10);
  echo "hopefully stopped";
  break;

case 'start_monitor':
  logger("Background Monitor Starting\n");
  system("/usr/local/emhttp/plugins/checksum/event/disks_mounted > /dev/null 2>&1");
  sleep(10);
  echo "done";
  break;


case 'validate_path':
  $path = urldecode(($_POST['path']));
  $index = urldecode(($_POST['index']));

  $path = trim($path);

  if ( $path == "/mnt/" || $path == "mnt/" || $path == "mnt" || $path == "/mnt" || $path == "/" || $path == "." || $path == ".." )
  {
    echo "<font color='red'>$path is NOT allowed to be used</font>";
    break;
  }

  if ( ! is_dir($path) )
  {
    echo "<font color='red'>Not a valid path</font>";
  } else {
    echo "<font color='green'>Valid Path</font>";
  }

  break;


case 'apply':

  $index = urldecode(($_POST['index']));
  $settings['Index'] = $index;
  $settings['Path'] = "/mnt/user/".urldecode(($_POST['share']));

  $settings['Algorithm'] = urldecode(($_POST['algorithm']));

  if ( $settings['Path'] == "/mnt/user/***" )                   { $settings['Path'] = urldecode(($_POST['custom'])); }

  if ( urldecode(($_POST['changed'])) == "yes" )      { $settings['Changed'] = true;      } else { $settings['Changed'] = false; }
  if ( urldecode(($_POST['separate'])) == "yes" )     { $settings['Separate'] = false;    } else { $settings['Separate'] = true; }
  if ( urldecode(($_POST['parity'])) == "yes" )       { $settings['Parity'] = true;       } else { $settings['Parity'] = false; }
  if ( urldecode(($_POST['includeall'])) == "yes" )   { $settings['IncludeAll'] = true;   } else { $settings['IncludeAll'] = false; }
  if ( urldecode(($_POST['extension'])) == ".hash" )  { $settings['Extension'] = ".hash"; } else { $settings['Extension'] = ".".$settings['Algorithm']; }
  if ( urldecode(($_POST['monitor'])) == "yes" )      { $settings['Monitor'] = true;      } else { $settings['Monitor'] = false; }

  $include = urldecode(($_POST['included']));
  $include = preg_replace('/\s+/', ' ', $include);
  $settings['Include'] = trim($include);

  $exclude = urldecode(($_POST['excluded']));
  $exclude = preg_replace('/\s+/', ' ', $exclude);
  $settings['Exclude'] = trim($exclude);

  if ( file_exists($checksumPaths['Settings']) )
  {
    $allSettings = json_decode(file_get_contents($checksumPaths['Settings']),true);
  } else {
    $allSettings = array();
  }

  $newSettings = array();
  foreach ($allSettings as $eachSetting)
  {
    if ( $eachSetting['Index'] == $index )
    {
      continue;
    } else {
      $newSettings[$eachSetting['Path']] = $eachSetting;
    }
  }
  $newSettings[$settings['Path']] = $settings;

  file_put_contents($checksumPaths['Settings'],json_encode($newSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ));
  file_put_contents($checksumPaths['usbSettings'],json_encode($newSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ));

  break;

case 'delete':
  $index = urldecode(($_POST['index']));

  $allSettings = json_decode(file_get_contents($checksumPaths['usbSettings']),true);

  $newSettings = array();

  $newIndex = 0;
  foreach ($allSettings as $settings)
  {
    if ( $settings['Index'] == $index )
    {
      continue;
    }

    $settings['Index'] = $newIndex;
    $newSettings[$settings['Path']] = $settings;

    $newIndex = ++$newIndex;
  }

  $output = createHeader().buildDisplay($newSettings).createFooter();

  echo $output;

  file_put_contents($checksumPaths['Settings'],json_encode($newSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ));
  file_put_contents($checksumPaths['usbSettings'],json_encode($newSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ));
  break;

case 'add':
  if ( file_exists($checksumPaths['usbSettings']) )
  {
    $allSettings = json_decode(file_get_contents($checksumPaths['usbSettings']),true);
  } else {
    $allSettings = array();
  }

  $maxIndex = 0;
  foreach ($allSettings as $settings)
  {
    if ( $settings['Index'] > $maxIndex )
    {
      $maxIndex = $settings['Index'];
    }
  }
  $allSettings['***']['Index']      = $maxIndex + 1;
  $allSettings['***']['Algorithm']  = "md5";
  $allSettings['***']['Changed']    = true;
  $allSettings['***']['Separate']   = false;
  $allSettings['***']['Extension']  = ".hash";
  $allSettings['***']['IncludeAll'] = true;
  $allSettings['***']['Monitor']    = true;

  $output = createHeader().buildDisplay($allSettings).createFooter();
  $output .= "<script>validatePath($maxIndex +1);</script>";
  echo $output;
  break;

case 'status':
  $status = exec('ps -A -f | grep -v grep | grep "checksum_inotifywait"');
  $verifyStatus = exec('ps -A -f | grep -v grep | grep "checksum/scripts/verify.php"');

  $inotifyInstalled = is_file("/usr/bin/inotifywait");

  $t = "";
  if ( $inotifyInstalled)
  {
    if ( $status )
    {
      $t .= "<font color='green'>Running</font><script>$('#restart').prop('disabled',true);$('#stop').prop('disabled',false);</script>";
    } else {
      $t .= "<font color='red'>Not Running</font><script>$('#restart').prop('disabled',false);$('#stop').prop('disabled',true);</script>";
    }
  } else {
    $t .= "<font color='red'>inotifywait NOT installed</font><script>$('#restart').prop('disabled',true);$('#stop').prop('disabled',true);</script>";
  }

  $md5Status = "Idle";

  if ( file_exists("/tmp/checksum/waiting") )  { $md5Status = "Waiting For Timeout"; }
  if ( file_exists("/tmp/checksum/running") )  { $md5Status = "Running"; }
  if ( file_exists("/tmp/checksum/mover") )    { $md5Status = "<font color='red'>Paused during mover operation</font>"; }
  if ( file_exists("/tmp/checksum/parity") )   { $md5Status = "<font color='red'>Paused for parity check / rebuild</font>"; }
  if ( file_exists("/tmp/checksum/paranoia") ) { $md5Status = "<font color='red'><strong>Paranoia Checks Failed.  Review Logs</strong></font>"; }

  $t .= "  Checksum Calculations <font color='green'>$md5Status</font>";

  $t .= "  Verifier Status: ";

  if ( is_file($checksumPaths['VerifyParity']) )
  {
    $t .= "<font color='red'><strong>Paused for Parity Check / Rebuild</strong></font>";
  } else {
    if ( $verifyStatus )
    {
      $t .= "<font color='green'><strong>Running</strong></font>";
    } else {
      $t .= "<font color='green'><strong>Idle</strong></font>";
    }
  }

  if ( is_file($checksumPaths['FailureLog']) )
  {
    $t .= "<script>$('#failureLog').prop('disabled',false);</script>";
  } else {
    $t .= "<script>$('#failureLog').prop('disabled',true);</script>";
  }

  echo $t;
  break;

case 'logline':
  $logline = shell_exec('tail -n 3 "/tmp/checksum/log.txt"');
  $logline = str_replace("\n","<br>",$logline);
  echo $logline;
  break;

case 'run_now':

  $share = urldecode(($_POST['share']));
  $custom = urldecode(($_POST['custom']));

  $status = exec('ps -A -f | grep -v grep | grep "checksum_inotifywait"');

  if ( ! $status )
  {
    logger("Can't queue a job if monitor isn't running\n");
    echo "done";
    break;
  }

  if ( $share == "/mnt/user/***" ) { $share = $custom; }

  $commandLine = 'echo "***'.time().'***'.$share.'***recursive" >> /tmp/checksumPipe';
  logger("Manually Added $share to queue\n");

  exec($commandLine);

  sleep (2);
  echo "done";

  break;

case 'change_global':
  if ( urldecode(($_POST['parity'])) == "yes" ) { $globalSettings['Parity'] = true; } else { $globalSettings['Parity'] = false; }
  if ( urldecode(($_POST['ignorehour'])) == "yes" ) { $globalSettings['IgnoreHour'] = true; } else { $globalSettings['IgnoreHour'] = false; }
  if ( urldecode(($_POST['notify'])) == "yes" ) { $globalSettings['Notify'] = true; } else { $globalSettings['Notify'] = false; }
  if ( urldecode(($_POST['logsave'])) == "yes" ) { $globalSettings['LogSave'] = true; } else { $globalSettings['LogSave'] = false; }

  $globalSettings['NumWatches'] = urldecode(($_POST['numwatches']));
  $globalSettings['NumQueue'] = urldecode(($_POST['numqueue']));

  $globalSettings['Pause'] = urldecode(($_POST['pause']));

  file_put_contents("/boot/config/plugins/checksum/settings/numqueue",$globalSettings['NumQueue']."\n");
  file_put_contents("/boot/config/plugins/checksum/settings/numwatches",$globalSettings['NumWatches']."\n");
  file_put_contents($checksumPaths['Global'],json_encode($globalSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  file_put_contents($checksumPaths['usbGlobal'],json_encode($globalSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  break;

case 'verify_now':
  $share = urldecode(($_POST['share']));
  $percent = urldecode(($_POST['percent']));
  $lastPercent = urldecode(($_POST['lastPercent']));

  file_put_contents("/tmp/checksum/verifyShare",$share."\n");
  file_put_contents("/tmp/checksum/verifyPercent",$percent."\n");
  file_put_contents("/tmp/checksum/verifyLast",$lastPercent."\n");

  logger("Begin Verification of $share.  Percent To Verify: $percent%  Starting Position: $lastPercent%\n");

  exec("/usr/local/emhttp/plugins/checksum/scripts/start_verify.sh");

  echo "done";

  break;

case 'show_manual':
  $t = "<center><font size='3'><b>Single Disk Verify Settings</b></font><br><br>";
  $t .= "<b>Disk To Check Manually: ";

  $allDisks = array_diff(scandir("/mnt/"),array(".","..","disks","user","user0","cache"));
  sort($allDisks, SORT_NATURAL);

  $t .= "<select id='disk2check'>";

  foreach ($allDisks as $Disk)
  {
    $diskNum = str_replace("disk","",$Disk);
    $t .= "<option value='$diskNum'>$Disk</option>";
  }
  $t .= "</select>";

  $t .= "<b>Percent To Check: </b><input type='number' id='diskpercent' value='100' class='narrow'></input";
  $t .= "<b>Percent To Start At: </b><input type='number' id='disklastpercent' value='0' class='narrow'></input>";
  $t .= "<input type='button' id='diskVerifyButton' value='Verify Disk' onclick='verifyDisk();'></input></center>";

  if ( file_exists($checksumPaths['usbSettings']) )
  {
    copy($checksumPaths['usbSettings'],$checksumPaths['Settings']);
    $shareSettings = json_decode(file_get_contents($checksumPaths['Settings']),true);
  } else {
    $shareSettings = array();
  }

  if ( sizeof($shareSettings) )
  {
    $t .= "<br><hr><center><font size='3'><b>Manual Share Verification</b></font></center><br>";

    $maxLength = 0;
    foreach ($shareSettings as $settings)
    {
      if ( strlen($settings['Path']) > $maxLength )
      {
        $maxLength = strlen($settings['Path']);
      }
    }

    $t .= "<center>";
    foreach ($shareSettings as $settings)
    {
      $i = $settings['Index'];

      $t .= "<span id='share$i' hidden>".$settings['Path']."</span>";
      $t .= "<font face='Courier New'>";
      $t .= str_replace(" ","&nbsp;",str_pad($settings['Path'],$maxLength+5));
      $t .= "</font>";
      $t .= "<b>Percent To Check: </b><input type='number' id='percent$i' class='narrow' value='100'></input>";
      $t .= "<b>Percent To Start At: </b><input type='number' id='last$i' class='narrow' value='0'></input>";
      $t .= "<input type='button' id='verify$i' value='Verify Share' onclick='verifyNow($i);'></input>";
      $t .= "<br><br>";
    }
    $t .= "</center>";

    $t .= "<hr>";
    $t .= "<center><font size='3'><b>Manual Checksum Creation</b></font></center><br>";

    $t .= "<center>";
    foreach ($shareSettings as $settings)
    {
      $i = $settings['Index'];
      $t .= "<font face='Courier New'>";
      $t .= str_replace(" ","&nbsp;",str_pad($settings['Path'],$maxLength+5));
      $t .= "</font><input type='button' id='run$i' value='Create Checksums' onclick='runNow($i);'></input><br>";
    }
  }
  echo $t;
  break;


case 'show_global':
  $t = "<center><font size='3'><b>Global Settings</b></font></center><br><br>";
  $t .= "<center><table style='width:40%'>
           <tr>
             <td>
               <b>Pause During Parity Check / Rebuild:</b>
             </td>
             <td>
               <select id='parity' onchange='changeGlobal();'>
                <option value='yes'>Yes</option>
                <option value='no'>No</option>
               </select>
             </td>
           </tr>
           <tr>
             <td>
               <b>Pause Before Creation:</b>
             </td>
             <td>
               <select id='pause' onchange='changeGlobal();'>
                <option value='60'>1 Minute</option>
                <option value='300'>5 Minutes</option>
                <option value='600'>10 Minutes</option>
                <option value='1800'>30 Minutes</option>
                <option value='3600'>1 Hour</option>
              </select>
            </td>
          </tr>
          <tr>
            <td>
              <b>Ignore Minor Corz Time Stamp Issues:</b>
            </td>
            <td>
              <select id='ignoreHour' onchange='changeGlobal();'>
                <option value='yes'>Yes</option>
                <option value='no'>No</option>
              </select>
            </td>
          </tr>
          <tr>
            <td>
              <b>Maximum Number Of Inotify Watches:</b>
            </td>
            <td>
              <input type='number' id='numwatches' class='narrow' oninput='validateWatches();'></input>
            </td>
          </tr>
          <tr>
            <td>
              <b>Maximum Number Of Queued Events:</b>
            </td>
            <td>
              <input type='number' id='numqueue' class='narrow' onput='validateWatches();'></input>
            </td>
          </tr>
          <tr>
            <td>
              <b>Notify On Verification Failure:</b>
            </td>
            <td>
              <select id='notify' onchange='changeGlobal();'>
                <option value='yes'>Yes</option>
                <option value='no'>No</option>
              </select>
            </td>
          </tr>
          <tr>
            <td>
              <b>Save Logs To Flash:</b>
            </td>
            <td>
              <select id='logsave' onchange='changeGlobal();'>
                <option value='yes'>Yes</option>
                <option value='no'>No</option>
              </select>
            </td>
          </tr>
        </table></center>";
  $t .= "<center>
    <input type='button' id='globalApply' onclick='globalApply();' value='Apply' disabled></input>
    </center>
  ";

  if ( file_exists($checksumPaths['usbGlobal']) )
  {
    copy($checksumPaths['usbGlobal'],$checksumPaths['Global']);
    $globalSettings = json_decode(file_get_contents($checksumPaths['Global']),true);
  } else {
    $globalSettings['Parity'] = true;
    $globalSettings['Pause'] = 3600;
  }

  if ( $globalSettings['NumWatches'] )
  {
    $numWatches = intval($globalSettings['NumWatches']);
  } else {
    if ( file_exists("/proc/sys/fs/inotify/max_user_watches") )
    {
      $numWatches = intval(file_get_contents("/proc/sys/fs/inotify/max_user_watches"));
    } else {
      $numWatches = 524288;
    }
  }

  if ( $globalSettings['NumQueue'] )
  {
    $numQueue = intval($globalSettings['NumQueue']);
  } else {
    if ( file_exists("/proc/sys/fs/inotify/max_queued_events") )
    {
      $numQueue = intval(file_get_contents("/proc/sys/fs/inotify/max_queued_events"));
    } else {
      $numQueue = 16384;
    }
  }


  $t .= "<script>";

  $t .= "$('#pause').val('".$globalSettings['Pause']."');";

  $t .= ($globalSettings['LogSave']) ? "$('#logsave').val('yes');" : "$('#logsave').val('no');";
  $t .= ($globalSettings['IgnoreHour']) ? "$('#ignoreHour').val('yes');" : "$('#ignoreHour').val('no');";
  $t .= ($globalSettings['Parity']) ? "$('#parity').val('yes');" : "$('#parity').val('no');";
  $t .= ($globalSettings['Notify']) ? "$('#notify').val('yes');" : "$('#notify').val('no');";

  $t .= "$('#numqueue').val('$numQueue');";
  $t .= "$('#numwatches').val('$numWatches');";

  $t .= "</script>";




  echo $t;
  break;

case "show_schedule":
  if ( file_exists($checksumPaths['usbSettings']) )
  {
    copy($checksumPaths['usbSettings'],$checksumPaths['Settings']);
    $shareSettings = json_decode(file_get_contents($checksumPaths['Settings']),true);
  } else {
    $shareSettings = array();
  }

  if ( file_exists($checksumPaths['Schedule']) )
  {
    $allSchedule = json_decode(file_get_contents($checksumPaths['Schedule']),true);
    $createSchedule = $allSchedule['Create'];
    $verifySchedule = $allSchedule['Verify'];
  }

  if ( ! is_array($createSchedule) )
  {
    $createSchedule = array();
  }
  if ( ! is_array($verifySchedule) )
  {
    $verifySchedule = array();
  }

  if ( ! sizeof($shareSettings) )
  {
    echo "<center><font size='3' color='red'>You must define at least one creation share</font></center>";
    return;
  }

  $index = 0;
  $t = "<center><font size='3'><b>Creation Schedule</b></font></center><br><br><center><table style='width:70%'>";
  foreach ($shareSettings as $settings)
  {
    $tag = "Create";
    $t .= "<tr><td style='width:30%'>";
    $t .= "<font size='2'><b>".$settings['Path']."</b></font><br>";

    if ( $settings['Monitor'] )
    {
      $t .= "<font color='green'>Also Monitored Automatically For Changes</font>";
    }

    $t .= "<span id='path$tag$index' hidden>".$settings['Path']."</span>";
    $t .= "</td><td>";
    $t .= createCron($tag,$index,$createSchedule[$settings['Path']]);
    $t .= "<td><br><br></tr>";
    $t .= "<tr><td></td></tr><tr><td></td></tr>";

    $index = ++$index;
  }
  $t .= "</table></center>";

  echo $t;
  break;


case "apply_schedule":
  $frequency = urldecode(($_POST['frequency']));
  $weekday = urldecode(($_POST['weekday']));
  $monthday = urldecode(($_POST['monthday']));
  $month = urldecode(($_POST['month']));
  $hour = urldecode(($_POST['hour']));
  $minute = urldecode(($_POST['minute']));
  $custom = urldecode(($_POST['custom']));
  $path = urldecode(($_POST['path']));
  $cron = urldecode(($_POST['cron']));
  $percent = urldecode(($_POST['percent']));
  $share = urldecode(($_POST['share']));


  if ( file_exists($checksumPaths['Schedule']) )
  {
    $allSchedule = json_decode(file_get_contents($checksumPaths['Schedule']),true);
  } else {
    $allSchedule = array();
  }

  $allSchedule[$share][$path]['Path']           = $path;
  $allSchedule[$share][$path]['Frequency']      = $frequency;
  $allSchedule[$share][$path]['DayOfWeek']      = $weekday;
  $allSchedule[$share][$path]['DayOfMonth']     = $monthday;
  $allSchedule[$share][$path]['Month']          = $month;
  $allSchedule[$share][$path]['Hour']           = $hour;
  $allSchedule[$share][$path]['Minute']         = $minute;
  $allSchedule[$share][$path]['Custom']         = $custom;
  $allSchedule[$share][$path]['GeneratedCron']  = $cron;
  $allSchedule[$share][$path]['PercentToCheck'] = $percent;

  file_put_contents($checksumPaths['Schedule'],json_encode($allSchedule, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

  exec($scriptPaths['RemoveCron']);
  exec($scriptPaths['UpdateCron']);

  echo "ok";
  break;

case "show_verify_schedule":
  $tag = urldecode(($_POST['tag']));

  if ( file_exists($checksumPaths['usbSettings']) )
  {
    copy($checksumPaths['usbSettings'],$checksumPaths['Settings']);
    $shareSettings = json_decode(file_get_contents($checksumPaths['Settings']),true);
  } else {
    $shareSettings = array();
  }

  if ( file_exists($checksumPaths['Schedule']) )
  {
    $allSchedule = json_decode(file_get_contents($checksumPaths['Schedule']),true);
    $createSchedule = $allSchedule['Create'];
    $verifySchedule = $allSchedule['Verify'];
    $diskSchedule = $allSchedule['Disk'];
  }

  if ( ! is_array($createSchedule) )
  {
    $createSchedule = array();
  }
  if ( ! is_array($verifySchedule) )
  {
    $verifySchedule = array();
  }
  if ( ! is_array($diskSchedule) )
  {
    $diskSchedule = array();
  }

  if ( $tag == "Verify" )
  {
    $t = "<center><font size='3'><b>Share Verification Schedule</b></font></center><br><br><center><table style='width:70%'>";

    if ( ! sizeof($shareSettings) )
    {
      echo "<center><font size='3' color='red'>You must define at least one creation share</font></center>";
      return;
    }
  } else {
    $t = "<center><font size='3'><b>Disk Verification Schedule</b></font></center><br><br><center><table style='width:70%'>";

    $shareSettings = array_diff(scandir("/mnt/"),array(".","..","user","user0","disks","cache"));
    sort($shareSettings, SORT_NATURAL);

  }
  $index = 0;


  foreach ($shareSettings as $settings)
  {
    if ( $tag == "Verify" )
    {
      $path = $settings['Path'];
      $cronSettings = $verifySchedule[$path];
    } else {
      $path = $settings;
      $cronSettings = $diskSchedule[$path];
    }

    $t .= "<tr><td style='width:30%'>";
    $t .= "<font size='2'><b>".$path."</b></font><br>";
    $t .= "<span id='path$tag$index' hidden>".$path."</span>";
    $t .= "</td><td>";
    $t .= createCron($tag,$index,$cronSettings);
    $t .= "<td><br><br></tr>";
    $t .= "<tr><td></td></tr><tr><td></td></tr>";

    $index = ++$index;
  }
  $t .= "</table></center>";

  echo $t;

}
?>
