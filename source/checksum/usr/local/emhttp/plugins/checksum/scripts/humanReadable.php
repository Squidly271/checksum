#!/usr/bin/php
<?php

#################################################
#                                               #
# Converts Seconds to a string a human can read #
#                                               #
#################################################

function readableTime($seconds)
{

  $time = secondsToTime($seconds);

  $t = "";

  if ( $time['d'] )
  {
    $t .= $time['d'];
    if ( $time['d'] == 1 )
    {
      $t .= " Day, ";
    } else {
      $t .= " Days, ";
    }
  }
  if ( $time['h'] )
  {
    $t .= $time['h'];
    if ( $time['h'] == 1 )
    {
      $t .= " Hour, ";
    } else {
      $t .= " Hours, ";
    }
  }

  if ( $time['m'] )
  {
    $t .= $time['m'];
    if ( $time['m'] == 1 )
    {
      $t .= " Minute, ";
    } else {
      $t .= " Minutes, ";
    }
  }
  $t .= $time['s'];
  if ( $time['s'] == 1 )
  {
    $t .= " Second.";
  } else {
    $t .= " Seconds.";
  }

  return $t;

}

function secondsToTime($inputSeconds) {

    $secondsInAMinute = 60;
    $secondsInAnHour  = 60 * $secondsInAMinute;
    $secondsInADay    = 24 * $secondsInAnHour;

    // extract days
    $days = floor($inputSeconds / $secondsInADay);

    // extract hours
    $hourSeconds = $inputSeconds % $secondsInADay;
    $hours = floor($hourSeconds / $secondsInAnHour);

    // extract minutes
    $minuteSeconds = $hourSeconds % $secondsInAnHour;
    $minutes = floor($minuteSeconds / $secondsInAMinute);

    // extract the remaining seconds
    $remainingSeconds = $minuteSeconds % $secondsInAMinute;
    $seconds = ceil($remainingSeconds);
#    $seconds = $remainingSeconds;

    // return the final array
    $obj = array(
        'd' => (int) $days,
        'h' => (int) $hours,
        'm' => (int) $minutes,
        's' => (int) $seconds,
    );
    return $obj;
}


###################################################
#                                                 #
# Converts a file size to a human readable string #
#                                                 #
###################################################

function human_filesize($bytes, $decimals = 2) {
  $size = array(' B',' kB',' MB',' GB',' TB',' PB',' EB',' ZB',' YB');
  $factor = floor((strlen($bytes) - 1) / 3);
  return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

$startTime = $argv[1] / 1000;
$endTime = $argv[2] / 1000;
$filesize = $argv[3];
$totalTime = $endTime - $startTime;

$average = intval($filesize / $totalTime);

$string = human_filesize($filesize)."  ".human_filesize($average)."/s";
echo $string;




?>
