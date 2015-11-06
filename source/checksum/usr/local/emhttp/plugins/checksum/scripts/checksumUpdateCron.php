#!/usr/bin/php
<?PHP

#####################################################
#                                                   #
# Routine that adds any scheduled jobs into crontab #
#                                                   #
#####################################################

  exec("crontab -l",$cron);

  $allSchedule = json_decode(file_get_contents("/boot/config/plugins/checksum/settings/schedule.json"),true);

  if ( is_array($allSchedule['Verify']) )
  {
    $verifySchedule = $allSchedule['Verify'];

    foreach ($verifySchedule as $schedule)
    {
      if ( $schedule['Frequency'] == "custom" )
      {
        $cronTime = $schedule['Custom'];
      } else {
        $cronTime = $schedule['GeneratedCron'];
      }

      $cronEntry = $cronTime.' /usr/local/emhttp/plugins/checksum/scripts/verifyShare.php "'.$schedule['Path'].'" &>/dev/null 2>&1';
      $cron[] = $cronEntry;
    }
  }

  if ( is_array($allSchedule['Disk']) )
  {
    $diskSchedule = $allSchedule['Disk'];

    foreach ($diskSchedule as $schedule)
    {
      if ( $schedule['Frequency'] == "custom" )
      {
        $cronTime = $schedule['Custom'];
      } else {
        $cronTime = $schedule['GeneratedCron'];
      }

      $cronEntry = $cronTime.' /usr/local/emhttp/plugins/checksum/scripts/verifyDisk.php "'.$schedule['Path'].'" &>/dev/null 2>&1';
      $cron[] = $cronEntry;
    }

  }

  if ( is_array($allSchedule['Create']) )
  {
    $shareSchedule = $allSchedule['Create'];

    foreach ($shareSchedule as $schedule)
    {
      if ( $schedule['Frequency'] == "custom" )
      {
        $cronTime = $schedule['Custom'];
      } else {
        $cronTime = $schedule['GeneratedCron'];
      }

      $cronEntry = $cronTime.' /usr/local/emhttp/plugins/checksum/scripts/checksumShare.php "'.$schedule['Path'].'" &>/dev/null 2>&1';
      $cron[] = $cronEntry;
    }
  }
  $cron[] = "";
  $cronFile = implode("\n",$cron);

  file_put_contents("/tmp/checksum/tempCron",$cronFile);

  exec("crontab /tmp/checksum/tempCron");
?>

