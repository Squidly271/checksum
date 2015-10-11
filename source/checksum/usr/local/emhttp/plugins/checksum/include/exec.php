<?PHP

$plugin="checksum";
$checksumPaths['usbSettings']       = "/boot/config/plugins/$plugin/settings.json";
$checksumPaths['tmpSettings']       = "/tmp/checksum/temp.json";
$checksumPaths['Settings']          = "/var/local/emhttp/plugins/$plugin/settings.json";
$checksumPaths['Waiting']           = "/tmp/checksum/waiting";
$checksumPaths['Parity']            = "/tmp/checksum/parity";
$checksumPaths['Running']           = "/tmp/checksum/running";
$checksumPaths['Log']               = "/tmp/checksum/log.txt";
$checksumPaths['Global']            = "/var/local/emhttp/plugins/$plugin/global.json";
$checksumPaths['usbGlobal']         = "/boot/config/plugins/$plugin/global.json";
$unRaidPaths['Variables']           = "/var/local/emhttp/var.ini";

$scriptPaths['InitializeWatch']     = "/usr/local/emhttp/plugins/$plugin/scripts.checksumInotify.php";
$scriptPaths['CreateWatch']         = "/usr/local/emhttp/plugins/$plugin/scripts/checksumInotify.sh";
$scriptPaths['b2sum']               = "/usr/local/emhttp/plugins/$plugin/include/b2sum";
$scriptPaths['MonitorWatch']        = "/usr/local/emhttp/plugins/$plugin/scripts/checksumInotify1.sh";
$scriptPaths['inotifywait']         = "/usr/bin/inotifywait";
$scriptPaths['checksuminotifywait'] = "/tmp/checksum/checksum_inotifywait";

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

  $directories = array_diff(scandir("/mnt/user"),array(".",".."));

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
    if ( $settings['Algorithm'] == $algorithm )
    {
      $t .= "<option value='$algorithm' selected>$algorithm</option>";
    } else {
      $t .= "<option value='$algorithm'>$algorithm</option>";
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
  $t .= "<td><input type='text' id='excluded$i' $includeFlag class='narrow' oninput='validateInclude($i);' value='$excludedFiles'></input></td>";

  $t .= "</tr></table>";

  $t .= "</td></tr></table>";

  $t .= "<center><input type='button' id='apply$i' value='Apply' onclick='apply($i);' disabled></input><input type='button' id='run$i' value='Add To Queue' $runSetting onclick='runNow($i);'></input><input type='button' id='delete$i' value='Delete' onclick='deleteMonitor($i);'></input></center>";

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

######## BEGIN MAIN #############

switch ($_POST['action']) {

case 'inotifywait':
  if ( file_exists($scriptPaths['inotifywait']) )
  {
    exec("cp ".$scriptPaths['inotifywait']." ".$scriptPaths['checksuminotifywait']);
  } else {
    echo "not installed";
  }
  break;

case 'initialize':

  if ( file_exists($checksumPaths['usbSettings']) )
  {
    copy($checksumPaths['usbSettings'],$checksumPaths['Settings']);
    $shareSettings = json_decode(file_get_contents($checksumPaths['Settings']),true);
  } else {
    $shareSettings = array();
  }

  $output = buildDisplay($shareSettings);
  if ( file_exists($checksumPaths['usbGlobal']) )
  {
    copy($checksumPaths['usbGlobal'],$checksumPaths['Global']);
    $globalSettings = json_decode(file_get_contents($checksumPaths['Global']),true);

    $output .= "<script>$('#pause').val('".$globalSettings['Pause']."');";

    if ( $globalSettings['Parity'] )
    {
      $output .= "$('#parity').val('yes');";
    } else {
      $output .= "$('#parity').val('no');";
    }
    $output .= "</script>";
  }

  echo $output;
  break;

case 'start_monitor':
  exec("pkill checksumInotify*");
  exec("pkill checksum_inotifywait");
  system("/usr/local/emhttp/plugins/checksum/scripts/start_monitor.sh > /dev/null 2>&1");
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

  if ( urldecode(($_POST['parity'])) == "yes" )  { $globalSettings['Parity'] = true; } else { $globalSettings['Parity'] = false; }
  $globalSettings['Pause'] = urldecode(($_POST['pause']));

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

  file_put_contents($checksumPaths['Global'],json_encode($globalSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ));
  file_put_contents($checksumPaths['usbGlobal'],json_encode($globalSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ));

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

  $output = buildDisplay($newSettings);

  echo $output;
  echo "deleted";

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

  $output = buildDisplay($allSettings);
  $output .= "<script>validatePath($maxIndex +1);</script>";
  echo $output;
  break;

case 'status':
  $status = exec('ps -A -f | grep -v grep | grep "checksum_inotifywait"');

  $t = "";

  if ( $status )
  {
    $t .= "<font color='green'>Running</font>";
  } else {
    $t .= "<font color='red'>Not Running</font>";
  }

  $md5Status = "Idle";

  if ( file_exists("/tmp/checksum/scanning") ) { $md5Status = "Scanning"; }
  if ( file_exists("/tmp/checksum/waiting") )  { $md5Status = "Waiting For Timeout"; }
  if ( file_exists("/tmp/checksum/running") )  { $md5Status = "Running"; }
  if ( file_exists("/tmp/checksum/parity") )   { $md5Status = "<font color='red'>Paused for parity check / rebuild</font>"; }

  $t .= "  Checksum Calculations <font color='green'>$md5Status</font>";

  echo $t;
  break;

case 'logline':
  $logline = shell_exec('tail -n 3 "/tmp/checksum/log.txt"');
  $logline = str_replace("\n","<br>",$logline);
  echo $logline;
  break;

case 'run_now':

  $share = "/mnt/user/".urldecode(($_POST['share']));
  $custom = urldecode(($_POST['custom']));

  if ( $share == "/mnt/user/***" ) { $share = $custom; }

  $commandLine = 'echo "***'.time().'***'.$share.'***recursive" >> /tmp/checksumPipe';
  echo $commandLine;
  exec($commandLine);
  break;

case 'change_global':
  if ( urldecode(($_POST['parity'])) == "yes" ) { $globalSettings['Parity'] = true; } else { $globalSettings['Parity'] = false; }
  $globalSettings['Pause'] = urldecode(($_POST['pause']));

  file_put_contents($checksumPaths['Global'],json_encode($globalSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  file_put_contents($checksumPaths['usbGlobal'],json_encode($globalSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  break;

}
?>
