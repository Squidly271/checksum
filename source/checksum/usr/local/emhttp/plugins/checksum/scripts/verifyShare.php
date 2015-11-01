#!/usr/bin/php
<?PHP

$checksumPaths['Log']         = "/tmp/checksum/log.txt";
$checksumPaths['Schedule']    = "/boot/config/plugins/checksum/settings/schedule.json";
$checksumPaths['Settings']    = "/boot/config/plugins/checksum/settings/settings.json";

function Mainlogger($string, $newLine = true)
{
  global $checksumPaths;
  if ( $newLine )
  {
    $string = date("M j Y H:i:s  ").$string;
  }
  file_put_contents($checksumPaths['Log'],$string,FILE_APPEND);
}

if ( ! is_file($checksumPaths['Schedule']) )
{
  return;
}

if ( ! is_file($checksumPaths['Settings']) )
{
  return;
}

$allSettings = json_decode(file_get_contents($checksumPaths['Settings']),true);
$allSchedule = json_decode(file_get_contents($checksumPaths['Schedule']),true);

$sharePassed = $argv[1];

$percent = $allSchedule['Verify'][$sharePassed]['PercentToCheck'];
if ( ! $percent )
{
  $percent = 10;
}

$lastPercent = $allSchedule['Verify'][$sharePassed]['LastChecked'];
if ( ! $lastPercent )
{
  $lastPercent = 0;
}

if ( $lastPercent > 99 )
{
  $lastPercent = 0;
}


MainLogger("Starting Scheduled Verification of $sharePassed ($percent% at $lastPercent%)\n");

$commandLine = '/usr/local/emhttp/plugins/checksum/scripts/verify.php "'.$sharePassed.'" '.$percent.' '.$lastPercent;

exec($commandLine,$dummyOutput,$returnFlag);

$allSchedule = json_decode(file_get_contents($checksumPaths['Schedule']),true);

$newLastPercent = $lastPercent + $percent;
if ( $newLastPercent > 99 )
{
  $newLastPercent = 100;
}

$allSchedule['Verify'][$sharePassed]['LastStatus'] = $returnFlag;
$allSchedule['Verify'][$sharePassed]['LastChecked'] = $newLastPercent;
$allSchedule['Verify'][$sharePassed]['LastCheckedDate'] = date("M d Y");

file_put_contents($checksumPaths['Schedule'],json_encode($allSchedule,JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

$loggerLine = "Finished Scheduled Verification of $sharePassed.";

if ( $returnFlag )
{
  $loggerLine .= "  One or more failures\n";
} else {
  $loggerLine .= "  Passed\n";
}

MainLogger($loggerLine)



?>
