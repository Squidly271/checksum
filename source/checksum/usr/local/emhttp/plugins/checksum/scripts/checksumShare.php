#!/usr/bin/php
<?PHP
$checksumPaths['Log']               = "/tmp/checksum/log.txt";



function logger($string, $newLine = true)
{
  global  $checksumPaths, $globalSettings;
  if ( $newLine )
  {
    $string = date("M j Y H:i:s  ").$string;
  }

  file_put_contents($checksumPaths['Log'],$string,FILE_APPEND);
}



$path = $argv[1];

$status = exec('ps -A -f | grep -v grep | grep "checksum_inotifywait"');

if ( ! $status )
{
  logger("Scheduled creation for $path skipped.  Monitor not running\n");
  return;
}

$commandLine = 'echo "***'.time().'***'.$path.'***recursive" >> /tmp/checksumPipe';

logger("Adding scheduled creation scan for $path\n");

exec($commandLine);

?>
