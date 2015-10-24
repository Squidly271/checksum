#!/usr/bin/php
<?php

#################################################
##                                             ##
##  Checksum.  Copyright 2015, Andrew Zawadzki ##
##                                             ##
#################################################


$plugin="checksum";
$checksumPaths['usbSettings'] = "/boot/config/plugins/$plugin/settings/settings.json";
$checksumPaths['tmpSettings'] = "/tmp/checksum/temp.json";
$checksumPaths['Settings']    = "/var/local/emhttp/plugins/$plugin/settings.json";
$checksumPaths['Waiting']     = "/tmp/checksum/waiting";
$checksumPaths['Parity']      = "/tmp/checksum/parity";
$checksumPaths['Mover']       = "/tmp/checksum/mover";
$checksumPaths['Running']     = "/tmp/checksum/running";
$checksumPaths['Scanning']    = "/tmp/checksum/scanning";
$checksumPaths['Paranoia']    = "/tmp/checksum/paranoia";
$checksumPaths['Global']      = "/var/local/emhttp/plugins/$plugin/global.json";
$checksumPaths['usbGlobal']   = "/boot/config/plugins/$plugin/settings/global.json";
$checksumPaths['OpenQueue']   = "/tmp/checksum/openqueue";

$checksumPaths['Log']         = "/tmp/checksum/log.txt";
$checksumPaths['ChecksumLog'] = "/tmp/checksum/checksumLog.txt";

$unRaidPaths['Variables']     = "/var/local/emhttp/var.ini";

$scriptPaths['CreateWatch']   = "/usr/local/emhttp/plugins/$plugin/scripts/checksumInotify.sh";
$scriptPaths['b2sum']         = "/usr/local/emhttp/plugins/$plugin/include/b2sum";
$scriptPaths['MonitorWatch']  = "/usr/local/emhttp/plugins/$plugin/scripts/checksumInotify1.sh";
$scriptPaths['inotifywait']   = "/usr/bin/inotifywait";

# get the settings

proc_nice(10);

file_put_contents($checksumPaths['Scanning'],"scanning");

$totalBytes = 0;
$timePaused = 0;


if ( ! file_exists($checksumPaths['Settings']) )
{
  copy($checksumPaths['usbSettings'],$checksumPaths['Settings']);
}

if ( file_exists($checksumPaths['usbGlobal']) )
{
  if ( ! file_exists($checksumPaths['Global']) )
  {
    copy($checksumPaths['usbGlobal'],$checksumPaths['Global']);
  }
  $globalSettings = json_decode(file_get_contents($checksumPaths['Global']),true);
} else {
  $globalSettings['Parity'] = true;
  $globalSettings['Pause'] = 3600;
}

$pauseTime = intval($globalSettings['Pause']);

$commandArguments = explode("***",$argv[1]);
#print_r($commandArguments);
$commandPath = $commandArguments[2];
#echo $commandPath;
$commandPath = rtrim($commandPath,"/");
$commandTime = $commandArguments[1];

$recursiveFlag = false;
if ( $commandArguments[3] == "recursive" )
{
  $recursiveFlag = true;
}

$AllSettings = json_decode(file_get_contents($checksumPaths['Settings']),true);

if ( ! $recursiveFlag )
{
  if ( time() < ( $commandTime + $pauseTime ) )
  {
    Mainlogger("Scan command received for $commandPath\n");
    $timeToWait = $commandTime + $pauseTime - time();
    Mainlogger("Waiting $timeToWait seconds before processing.\n");

    file_put_contents($checksumPaths['Waiting'],"waiting");
    @time_sleep_until($commandTime + $pauseTime );
    Mainlogger("Resuming\n");
    unlink($checksumPaths['Waiting']);
  } else {
    Mainlogger("Scan command received for $commandPath\n");
  }
} else {
  Mainlogger("Manual scan of $commandPath started\n");
}

#print_r($AllSettings);


function is_mover_running()
{
  global $globalSettings;

  if ( ! $globalSettings['Mover'] )
  {
    return false;
  }
  $status = exec('ps -A -f | grep -v grep | grep "/usr/local/sbin/mover"');

  return $status;
}

function is_parity_running()
{
  global $md5Settings, $checksumPaths, $scriptPaths, $unRaidPaths, $globalSettings;

  if ( ! $globalSettings['Parity'] ) {
    return false;
  }

  $vars = array();

  $vars = parse_ini_file($unRaidPaths['Variables']);

  return ( $vars['mdResync'] != "0" );
}

function logger($string, $newLine = true)
{
  global  $checksumPaths, $scriptPaths, $unRaidPaths, $globalSettings;
  if ( $newLine )
  {
    $string = date("M j Y H:i:s  ").$string;
  }

  if ( @filesize($checksumPaths['Log']) > 500000 )
  {
    $string = "Log size > 500,000 bytes.  Restarting\nIf this is the last line displayed on the log window, you will have to close and reopen the log window".$string;
    file_put_contents($checksumPaths['ChecksumLog'],$string,FILE_APPEND);

    if ( $globalSettings['LogSave'] )
    {
      $saveLogName = "/boot/config/plugins/checksum/logs/Checksum-".date("Y-m-d H-i-s").".txt";
      $saveLogText = file_get_contents($checksumPaths['ChecksumLog']);
      $saveLogText = str_replace("\n","\r\n",$saveLogText);
      file_put_contents($saveLogName,$saveLogText);
    }

    unlink($checksumPaths['ChecksumLog']);
  }
  file_put_contents($checksumPaths['ChecksumLog'],$string,FILE_APPEND);
}

function Mainlogger($string, $newLine = true)
{
  global  $checksumPaths, $scriptPaths, $unRaidPaths, $globalSettings;
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

function is_open($fullPath)
{
  $directory = pathinfo($fullPath,PATHINFO_DIRNAME);
  $filename = pathinfo($fullPath, PATHINFO_BASENAME);

  $command = 'lsof +d "'.$directory.'" | grep "'.$filename.'"';
  $result = exec($command);

  return $result;
}


function string_ends_with($string, $ending)
{
    $len = strlen($ending);
    $string_end = substr($string, strlen($string) - $len);

    return $string_end == $ending;
}

function fileMatch($filename,$matchArray)
{
  foreach ($matchArray as $testfile)
  {
    if ( fnmatch($testfile,$filename, FNM_CASEFOLD) )
    {
      return true;
    }
  }
  return false;
}

function paranoiaCheck($filename)
{
  global $md5Settings, $globalSettings, $files_to_create, $md5FileToCreate, $checksumPaths;

  $extensionGiven = pathinfo($filename,PATHINFO_EXTENSION);

  switch ( $extensionGiven ) {
    case "md5":
      break;
    case "hash":
      break;
    case "sha1":
      break;
    case "sha256":
      break;
    case "blake2":
      break;
    default:
      Mainlogger("Paranoia check failed!!  Execution halted\n");
      Mainlogger("Read / Write of hash file's extension.  Extension != {hash|md5|sha1|sha256|blake2}.  Possible data corruption could follow if contiunued.\n");
      Mainlogger("Relevant variables below.  See support thread for more assistance\n");
      Mainlogger("filename: ".$filename."\n");
      Mainlogger("md5Settings: ".print_r($md5Settings,true)."\n");
      Mainlogger("globalSettings: ".print_r($globalSettings,true)."\n");
      Mainlogger("files_to_create: ".print_r($files_to_create,true)."\n");
      Mainlogger("md5FilesToCreate: ".print_r($md5FileToCreate,true)."\n");

      file_put_contents($checksumPaths['Paranoia'],"check failed");

      exec('/usr/local/emhttp/plugins/dynamix/scripts/notify -e "Checksum failed paranoia check" -s "Checksum failed paranoia check" -i "alert" -m "See logs for details" -d "See logs for details"');
      while ( true )
      {
        sleep(999999);
      }
  }
  return true;
}

##########################################################################
#                                                                        #
# Routine to generate the checksum files from the $files_to_create array #
#                                                                        #
##########################################################################


function generateMD5($files_to_create)
{
  global $md5Settings, $totalBytes;
  global  $checksumPaths, $scriptPaths, $unRaidPaths;

#  print_r($files_to_create);

  $updateChanged = $md5Settings['Changed'];

  foreach ($files_to_create as $md5FileToCreate)
  {
    $md5Filename = $md5FileToCreate['filename'];

    $md5FileText = "#Squid's Checksum\n";
    $md5FileText .= "#\n";

    $updateFlag = false;

    $filenameLength = 0;
    foreach ($md5FileToCreate['files'] as $file) {
      if ( strlen($file['file']) > $filenameLength )
      {
        $filenameLength = strlen($file['file']);
      }
    }

    foreach ($md5FileToCreate['files'] as $file) {
      if ( is_parity_running() ) {
        Mainlogger("Parity check / rebuild currently running.  Pausing until completed\n");
        $timeInPause = time();

        file_put_contents($checksumPaths['Parity'],"running");

        while (1) {
          if ( is_parity_running() ) {
            sleep(600);
          } else {
            Mainlogger("Parity check / rebuild finished... Resuming...\n");
            unlink($checksumPaths['Parity']);
            break;
          }
        }
        $timeInPause = time() - $timeInPause;
        $timePaused = $timePause + $timeInPause;
      }

# check to see if file wound up getting deleted.
      if ( ! file_exists($file['file']) ) {
        continue;
      }
      $updateText = "Creating ";

      if ( $file['changed'] )
      {
        $updateText = "Updating ";
        if ( $updateChanged )
        {
          $file['update'] = true;
        }
      } else if ( ! $file[$md5Settings['Algorithm']] ) {
        $file['update'] = true;
      }

      if ( $file['update'] )
      {
        if ( is_open($file['file']) ) {
          logger("Warning: ".$file['file']." is currently open... Skipping.\n");
          file_put_contents($checksumPaths['OpenQueue'],$file['file']."\n",FILE_APPEND);
          continue;
        }

        $updateFlag = true;

        logger($updateText.$md5Settings['Algorithm']." checksum for ".str_pad($file['file'],$filenameLength));

        $md5FileText .= "#".$md5Settings['Algorithm']."#";

# replace any "#" in filename with "/" (only character not allowed in both windows and linux

        $tempFilename = basename($file['file']);
#        $tempFilename = str_replace("#","/",$tempFilename);
        $md5FileText .= $tempFilename."#".timeToCorz(filemtime($file['file']))."\n";

        $fileSize = filesize($file['file']);

        $fileStartTime = microtime(true);


        $totalBytes = $totalBytes + $fileSize;

        if ( file_exists($file['file']) ) {
          switch ( $md5Settings['Algorithm'] ) {
            case "md5":
              $hash = md5_file($file['file']);
              break;
            case "sha1":
              $hash = sha1_file($file['file']);
              break;
            case "sha256":
              $hashTemp = exec('sha256sum "'.$file['file'].'"');
              $hashTemp1 = explode(" ",$hashTemp);
              $hash = $hashTemp1[0];
              break;
            case "blake2":
              $hashTemp = exec('/usr/local/emhttp/plugins/checksum/include/b2sum -a blake2s "'.$file['file'].'"');
              $hashTemp1 = explode(" ",$hashTemp);
              $hash = $hashTemp1[0];
              break;
          }
        }
        $fileTotalTime = microtime(true) - $fileStartTime;

        $fileAverageTime = intval($fileSize / $fileTotalTime);
        logger("   Speed: ".str_pad(human_filesize($fileAverageTime),9," ",STR_PAD_LEFT)."/s",false);
        logger(" ( ".str_pad(human_filesize($fileSize),9," ",STR_PAD_LEFT),false);
        logger(" / ".date("i:s",$fileTotalTime),false);
        logger(" )\n",false);


        $md5FileText .= $hash."  ".basename($file['file'])."\n";
      } else {
        if ( $file['time'] )
        {
          $fileTime = $file['time'];
        } else {
          $fileTime = filemtime($file['file']);
        }

        switch ( $md5Settings['Algorithm'] ) {
          case "md5":
            $md5FileText .= "#md5#";
            break;
          case "sha":
            $md5FileText .= "#sha1#";
            break;
          case "sha256":
            $md5FileText .= "#sha256#";
            break;
          case "blake2":
            $md5FileText .= "#blake2#";
            break;
        }
        $md5FileText .= basename($file['file'])."#".timeToCorz($fileTime)."\n";
        $md5FileText .= $file[$md5Settings['Algorithm']]."  ".basename($file['file'])."\n";
      }
    }
    if ( $updateFlag )
    {
        paranoiaCheck($md5Filename);
        file_put_contents($md5Filename,$md5FileText);
        chown($md5Filename,"nobody");
        chgrp($md5Filename,"users");
    }
  }
}

#######################################################################################
#                                                                                     #
# Routine to parse an existing .md5 file and create an array with the file times, etc #
#                                                                                     #
#######################################################################################

function parseMD5($filename)
{
  global $md5Settings;
  global  $checksumPaths, $scriptPaths, $unRaidPaths;

  $filePath = pathinfo($filename,PATHINFO_DIRNAME);

  paranoiaCheck($filename);

  $md5 = file_get_contents($filename);

  $md5 = trim($md5);

  $md5 = str_replace("\r\n","\n",$md5);
  $md5Contents = explode("\n",$md5);
  $md5Array = array();

  for ($i = 0; $i < count($md5Contents); $i++)
  {
    $md5Entry = $filePath."/".$md5file;

# parse if the comment line begins with #md5

    if ( ( strpos($md5Contents[$i],"#md5") === 0 ) || ( strpos($md5Contents[$i],"#sha1") === 0 ) || ( strpos($md5Contents[$i],"#sha256") === 0 ) || ( strpos($md5Contents[$i],"#blake2") === 0 ) ) {
     $md5Comment = array();

     $nextMD5line = $md5Contents[$i+1];
     $md5Comment = explode("#",$md5Contents[$i]);
     $tempMD5 = $md5Comment;
     unset($tempMD5[sizeof($tempMD5)-1]);
     unset($tempMD5[0]);
     unset($tempMD5[1]);
     $md5File = implode("#",$tempMD5);

     $md5Time = end($md5Comment);
     $md5Line = explode(" ",$nextMD5line);
     $md5Calculated = $md5Line[0];

     $md5Entry = $filePath."/".$md5File;

     $md5Array[$md5Entry]['time'] = corzToTime($md5Time);

     if ( strpos($md5Contents[$i],"#md5") === 0 )     { $md5Array[$md5Entry]['md5'] = $md5Calculated; }
     if ( strpos($md5Contents[$i],"#sha1") === 0 )    { $md5Array[$md5Entry]['sha1'] = $md5Calculated; }
     if ( strpos($md5Contents[$i],"#sha256") === 0 )  { $md5Array[$md5Entry]['sha256'] = $md5Calculated; }
     if ( strpos($md5Contents[$i],"#blake2") === 0 )  { $md5Array[$md5Entry]['blake2'] = $md5Calculated; }



#     $md5Array[$md5Entry][$algorithm] = $md5Calculated;

     $i = ++$i;
     continue;
   }

# skip the line if it begins with #

   if ( strpos($md5Contents[$i],"#") === 0 ) {
     continue;
   }

# Since we're here, there is no comments with timestamps, so just parse the line

   $md5Line = array();
   $md5Line = explode(" ",$md5Contents[$i]);
   $md5Calculated = $md5Line[0];
   unset($md5Line[0]);
   $md5File = implode(" ",$md5Line);
   $md5File = trim($md5File);
   if ( $md5File[0] == "*" ) {
     $md5File = ltrim($md5File,"*");
   }
   $md5Entry = $filePath."/".$md5File;

   $md5Array[$md5Entry]['time'] = filemtime($md5Entry);
   $md5Array[$md5Entry][$md5Settings['Algorithm']] = $md5Calculated;
  }
#  print_r($md5Array);
  return $md5Array;
}

##############################################################################################################################################################
#                                                                                                                                                            #
# This is the main routine.  Recursive function to gather all the files required in every folder.  Creates the .md5's either by folder, or one for each file #
#                                                                                                                                                            #
##############################################################################################################################################################

function getFiles($path, $recursive = false)
{
  global  $checksumPaths, $scriptPaths, $unRaidPaths;

  global $video, $md5Settings, $globalSettings;

  $files_to_create = array();
  $separate = $md5Settings['Separate'];

  if ( $md5Settings['Include'] )
  {
    $includedFiles = explode(" ",$md5Settings['Include']);
  } else {
    $includedFiles = array();
  }

  if ( $md5Settings['Exclude'] )
  {
    $excludedFiles = explode(" ",$md5Settings['Exclude']);
  } else {
    $excludedFiles = array();
  }

  if ( ! is_dir($path) )
  { return; }

  $test = array_diff(scandir($path), array(".",".."));

  $temp = array();

  if ( ! $separate ) {
    if ( file_exists($path."/".basename($path).$md5Settings['Extension']) ) {
      $md5Array = parseMD5($path."/".basename($path).$md5Settings['Extension']);
    } else {
      $md5Array = array();
    }
  } 

  foreach ($test as $file)
  {
    $filename = $path."/".$file;
    if ( is_dir($filename) ) {
      if ( $recursive ) {
        getFiles($filename,true);
      }
      continue;
    }

    if ( ! $md5Settings['IncludeAll'] ) {
      if ( sizeof($includedFiles) )
      {
        if ( ! fileMatch($filename,$includedFiles) )
        {
          continue;
        }
      }
      if ( sizeof($excludedFiles) )
      {
        if ( fileMatch($filename,$excludedFiles) )
        {
          continue;
        }
      }
    }

    if ( pathinfo($filename, PATHINFO_EXTENSION) == ltrim($md5Settings['Extension'],".") ) {
      continue;
    }

    if ( $separate ) {
      $files_to_create[$filename]['filename'] = $filename.$md5Settings['Extension'];
      $files_temp = array();

      if ( file_exists($filename.$md5Settings['Extension']) ) {
        $md5Array = parseMD5($filename.$md5Settings['Extension']);
      } else {
        unset($md5Array);
      }
      if ( is_array($md5Array[$filename]) ) {
        $timeDifference = abs(filemtime($filename) - $md5Array[$filename]['time']);

        if ( ($timeDifference == 3600) || ($timeDifference == 3599) || ($timeDifference == 3601) || ($timeDifference == 1) )
        {
          if ( $globalSettings['IgnoreHour'] )
          {
            if ( $timeDifference == 1)
            {
              $timeWarning = "Windows <-> Linux timestamp issue?";
            } else {
              $timeWarning = "Corz timestamp bug?";
            }

            logger("Warning: $filename's timestamp differs by exactly ".readableTime($timeDifference).".  $timeWarning ?\n");
            $md5Array[$filename]['time'] = filemtime($filename);
          }
        }

        if ( filemtime($filename) != $md5Array[$filename]['time'] )
        {
          $files_temp['changed'] = true;
          $files_temp['update'] = false;
          $files_temp['time'] = $md5Array[$filename]['time'];
          $files_temp['md5'] = $md5Array[$filename]['md5'];
          $files_temp['sha1'] = $md5Array[$filename]['sha1'];
          $files_temp['sha256'] = $md5Array[$filename]['sha256'];
          $files_temp['blake2'] = $md5Array[$filename]['blake2'];
        } else {
          $files_temp['update'] = false;
          $files_temp['changed'] = false;
          $files_temp['time'] = $md5Array[$filename]['time'];
          $files_temp['md5'] = $md5Array[$filename]['md5'];
          $files_temp['sha1'] = $md5Array[$filename]['sha1'];
          $files_temp['sha256'] = $md5Array[$filename]['sha256'];
          $files_temp['blake2'] = $md5Array[$filename]['blake2'];
        }
      } else {
        $files_temp['update'] = true;
      }


      $files_temp['file'] = $filename;
      $files_to_create[$filename]['files'][] = $files_temp;
    } else {
#      print_r($md5Array);
      if ( is_array($md5Array[$filename]) ) {
        $timeDifference = abs(filemtime($filename) - $md5Array[$filename]['time']);

        if ( ( $timeDifference == 3600 ) || ($timeDifference == 3599) || ($timeDifference == 3601) || ($timeDifference == 1) )
        {
          if ( $globalSettings['IgnoreHour'] )
          {
            if ( $timeDifference == 1 )
            {
              $timeWarning = "Windows <-> Linux timestamp issue";
            } else {
              $timeWarning = "Corz timestamp bug";
            }

            logger("Warning: $filename's timestamp differs by exactly ".readableTime($timeDifference).".  $timeWarning ?\n");
            $md5Array[$filename]['time'] = filemtime($filename);
          }
        }

        if ( filemtime($filename) != $md5Array[$filename]['time'] )
        {
          $temp1['changed'] = true;
          $temp1['update'] = false;
          $temp1['time'] = $md5Array[$filename]['time'];
          $temp1['md5'] = $md5Array[$filename]['md5'];
          $temp1['sha1'] = $md5Array[$filename]['sha1'];
          $temp1['sha256'] = $md5Array[$filename]['sha256'];
          $temp1['blake2'] = $md5Array[$filename]['blake2'];
        } else {
          $temp1['update'] = false;
          $temp1['changed'] = false;
          $temp1['time'] = $md5Array[$filename]['time'];
          $temp1['md5'] = $md5Array[$filename]['md5'];
          $temp1['sha1'] = $md5Array[$filename]['sha1'];
          $temp1['sha256'] = $md5Array[$filename]['sha256'];
          $temp1['blake2'] = $md5Array[$filename]['blake2'];
        }
      } else {
        $temp1['update'] = true;
      }
      $temp1['file'] = $filename;
      $temp[] = $temp1;
    }
  }
  if ( is_dir($path) ) {
    $path = $path."/".basename($path);
  }
  if ( ! $separate ) {
    $files_to_create[$path]['filename'] = $path.$md5Settings['Extension'];
    $files_to_create[$path]['files'] = $temp;
  }


#  print_r($files_to_create);
  generateMD5($files_to_create);
}

################################################################################
#                                                                              #
# Function to convert linux date stamp to string format used by corz' checksum #
#                                                                              #
################################################################################

function timeToCorz($time)
{
  return date("Y.m.d@H.i:s",$time);
}

####################################################################################
#                                                                                  #
# Function to convert string time stamp used by corz' checksum to linux date stamp #
#                                                                                  #
####################################################################################

function corzToTime($corzTime)
{
  $dateTime = explode("@",$corzTime);
  $date = str_replace(".","-",$dateTime[0]);
  $time = str_replace(".",":",$dateTime[1]);

  return strtotime($date." ".$time);
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

function human_filesize($bytes, $decimals = 2) {
  $size = array(' B',' kB',' MB',' GB',' TB',' PB',' EB',' ZB',' YB');
  $factor = floor((strlen($bytes) - 1) / 3);
  return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}


########################
########################
##                    ##
## Start Main program ##
##                    ##
########################
########################

file_put_contents($checksumPaths['Running'],"running");
$foundFlag = false;
foreach ($AllSettings as $Settings)
{
  if ( strpos($commandPath,$Settings['Path']) === 0 )
  {
    $md5Settings = $Settings;
    $foundFlag = true;

    $startTime = time();
    getFiles($commandPath,$recursiveFlag);
    $totalTime = time() - $startTime;

    $readableTime = readableTime($totalTime);

    if ( $totalBytes )
    {
      if ( $totalTime == 0 ) {
        $totalTime = 1;
      }
      $bytesPerSec = intval($totalBytes / $totalTime);
      $totalDisplayed = human_filesize($bytesPerSec);
    } else {
      $totalDisplayed = "0.00 B";
    }
  }
}
Mainlogger("Job Finished.  Total Time: $readableTime  Total Size: ".human_filesize($totalBytes)."  Average Speed: $totalDisplayed/s\n");

unlink($checksumPaths['Running']);
unlink($checksumPaths['Scanning']);
?>
