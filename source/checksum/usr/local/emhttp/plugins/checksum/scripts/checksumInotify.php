#!/usr/bin/php
<?php

$AllSettings = json_decode(file_get_contents("/tmp/GitHub/test.json"),true);

foreach ($AllSettings as $Settings)
{
  $commandPath = $commandPath." ".escapeshellarg($Settings['Path']);

}

  echo "Creating Watch Directories for: ".$commandPath."\n";

  system("/tmp/GitHub/checksumInotify.sh ".$commandPath);
?>

