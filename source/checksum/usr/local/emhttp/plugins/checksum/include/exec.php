<?PHP
$plugin="checksum";
$checksumPaths['usbSettings'] = "/boot/config/plugins/$plugin/settings.json";
$checksumPaths['tmpSettings'] = "/tmp/checksum/temp.json";
$checksumPaths['Settings'] = "/var/local/emhttp/plugins/$plugin/settings.json";

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

    $runSetting = "disabled";
  }

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
  $t .= "<br><br><span id='patherror$i'></span>";
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
  if ( $settings['Extension'] == "hash" )
  {
    $t .= "<option value='hash' selected>.hash</option>";
    $t .= "<option value='algorithm'>checksum algorithm</option>";
  } else {
    $t .= "<option value='hash'>.hash</option>";
    $t .= "<option value='algorithm' selected>checksum algorithm</option>";
  }
  $t .= "</select></td></tr>";

  $t .= "<tr><td><b>Include all files</b></td>";
  $t .= "<td><select id='includeall$i' onchange='includeChanged($i);'>:";

  $includeFlag = "disabled";
  if ( $settings['IncludeAll'] )
  {
    $t .= "<option value='yes' selected>Yes</option>";
    $t .= "<option value='no'>No</option>";
  } else {
    $t .= "<option value='yes'>Yes</option>";
    $t .= "<option value='no' selected>No</option>";
    $includeFalg = "";
  }
  $t .= "</select></td><tr>";

  $t .= "<td><b>Included Files:</b></td>";
  $t .= "<td><input type='text' id='included$i' $includeFlag class='narrow' oninput='enableApply($i);'></input></td>";

  $t .= "<td><b>Excluded Files:</b></td>";
  $t .= "<td><input type='text' id='excluded$i' $includeFlag class='narrow' oninput='enableApply($i);'></input></td>";


  $t .= "</tr></table>";

  $t .= "</td></tr></table>";
  $t .= "<center><input type='button' id='apply$i' value='Apply' onclick='apply($i);' disabled></input><input type='button' id='run$i' value='Run Now' $runSetting></input><input type='button' id='delete$i' value='Delete' onclick='deleteMonitor($i);'></input></center>";

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
    $flag = true;
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
case 'initialize':

  if ( file_exists($checksumPaths['usbSettings']) )
  {
    exec("cp ".$checksumPaths['usbSettings']." ".$checksumPaths['Settings']);
    exec("cp ".$checksumPaths['Settings']." ".$checksumPaths['tmpSettings']);
    $shareSettings = json_decode(file_get_contents($checksumPaths['tmpSettings']),true);
  } else {
    $shareSettings = array();
  }

  $output = buildDisplay($shareSettings);

  echo $output;
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

  $include = urldecode(($_POST['included']));
  $included = explode(" ",$include);

  foreach ($included as $file)
  {
    $settings['Include'][$file] = true;
  }

  $exclude = urldecode(($_POST['excluded']));
  $excluded = explode(" ",$exclude);

  foreach ($excluded as $file)
  {
    $settings['Exclude'][$file] = true;
  }


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

  $output = buildDisplay($newSettings);

  echo $output;
  echo "deleted";

  file_put_contents($checksumPaths['Settings'],json_encode($newSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ));
  file_put_contents($checksumPaths['usbSettings'],json_encode($newSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ));
  break;

case 'add':
  $allSettings = json_decode(file_get_contents($checksumPaths['usbSettings']),true);

  $maxIndex = 0;
  foreach ($allSettings as $settings)
  {
    if ( $settings['Index'] > $maxIndex )
    {
      $maxIndex = $settings['Index'];
    }
  }
  $allSettings['***']['Index'] = $maxIndex + 1;

  $output = buildDisplay($allSettings);
  $output .= "<script>validatePath($maxIndex +1);</script>";
  echo $output;
}

?>
