#!/usr/bin/php
<?PHP

  exec("crontab -l",$cron);

  $numLines = sizeof($cron);

# remove all existing checksum entries

  for ($i=0; $i<$numLines; $i++)
  {
    if ( strpos($cron[$i],"/usr/local/emhttp/plugins/checksum/") )
    {
      unset($cron[$i]);
    }
  }
  $cron[] = "";
  $cronFile = implode("\n",$cron);

  file_put_contents("/tmp/checksum/tempCron",$cronFile);

  exec("crontab /tmp/checksum/tempCron");

?>
