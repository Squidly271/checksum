#!/usr/bin/php
<?php

$plugin="checksum";
$checksumPaths['usbSettings'] = "/boot/config/plugins/$plugin/settings/settings.json";
$checksumPaths['tmpSettings'] = "/tmp/checksum/temp.json";
$checksumPaths['Settings']    = "/var/local/emhttp/plugins/$plugin/settings.json";
$checksumPaths['Waiting']     = "/tmp/checksum/waiting";
$checksumPaths['Parity']      = "/tmp/checksum/parity";
$checksumPaths['Running']     = "/tmp/checksum/running";
$checksumPaths['Log']         = "/tmp/checksum/log.txt";

$unRaidPaths['Variables']     = "/var/local/emhttp/var.ini";

$scriptPaths['CreateWatch']   = "/usr/local/emhttp/plugins/$plugin/scripts/checksumInotify.sh";
$scriptPaths['b2sum']         = "/usr/local/emhttp/plugins/$plugin/include/b2sum";
$scriptPaths['MonitorWatch']  = "/usr/local/emhttp/plugins/$plugin/scripts/checksumInotify1.sh";
$scriptPaths['inotifywait']   = "/usr/bin/inotifywait";
$scriptPaths['checksuminotifywait'] = "/tmp/checksum/checksum_inotifywait";

if ( ! is_dir("/var/local/emhttp/plugins/checksum") )
{
  mkdir("/var/local/emhttp/plugins/checksum",0777,true);
}


if ( ! file_exists($scriptPaths['inotifywait']) )
{
  echo "inotify tools not installed";
  return;
}

if ( ! file_exists($scriptPaths['checksuminotifywait']) )
{
  exec("cp ".$scriptPaths['inotifywait']." ".$scriptPaths['checksuminotifywait']);
  file_put_contents("/tmp/checksum/test","whatever");
}

if ( ! file_exists($checksumPaths['Settings']) )
{
  if ( ! file_exists($checksumPaths['usbSettings']) )
  {
    return;
  } else {
    copy($checksumPaths['usbSettings'],$checksumPaths['Settings']);
  }
}

#chmod($scriptPaths['checksuminotifywait'],0777);

$AllSettings = json_decode(file_get_contents($checksumPaths['Settings']),true);
print_r($AllSettings);
foreach ($AllSettings as $Settings)
{
  if ( $Settings['Monitor'] )
  {
    $commandPath = $commandPath." ".escapeshellarg($Settings['Path']);
  }
}

  echo "Creating Watch Directories for: ".$commandPath."\n";

  exec($scriptPaths['CreateWatch']." ".$commandPath." > /dev/null 2>&1");
?>

