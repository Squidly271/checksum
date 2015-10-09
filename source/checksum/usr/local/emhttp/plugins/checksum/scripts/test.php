#!/usr/bin/php
<?php
# get the settings

$totalBytes = 0;
$timePaused = 0;

$commandArguments = explode("***",$argv[1]);
#print_r($commandArguments);
$commandPath = $commandArguments[2];
#echo $commandPath;
$commandPath = rtrim($commandPath,"/");
$AllSettings = json_decode(file_get_contents("/tmp/GitHub/test.json"),true);


#print_r($AllSettings);

function is_parity_running()
{
  global $md5Settings;

  if ( ! $md5Settings['Parity'] ) {
    return false;
  }

  $vars = array();

  $vars = parse_ini_file("/var/local/emhttp/var.ini");

  return ( $vars['mdResync'] != "0" );
}

function logger($string)
{
  echo $string;
}

function is_open($fullPath)
{
  $directory = pathinfo($fullPath, PATHINFO_DIRNAME);
  $filename = pathinfo($fullPath, PATHINFO_BASENAME);

  $result = exec('lsof +D "'.$directory.'" | grep -c -i "'.$filename.'"');

  return ( $result != "0" );
}


function string_ends_with($string, $ending)
{
    $len = strlen($ending);
    $string_end = substr($string, strlen($string) - $len);

    return $string_end == $ending;
}

##########################################################################
#                                                                        #
# Routine to generate the checksum files from the $files_to_create array #
#                                                                        #
##########################################################################


function generateMD5($files_to_create)
{
  global $md5Settings, $totalBytes;

#  print_r($files_to_create);

  $updateChanged = $md5Settings['Changed'];

  foreach ($files_to_create as $md5FileToCreate)
  {
    if ( is_parity_running() ) {
      logger("Parity check / rebuild currently running.  Pausing until completed\n");
      $timeInPause = time();

      while (1) {
        if ( is_parity_running() ) {
          sleep(600);
        } else {
          logger("Parity check / rebuild finished... Resuming...\n");
          break;
        }
      }
      $timeInPause = time() - $timeInPause;
      $timePaused = $timePause + $timeInPause;
    }

    $md5Filename = $md5FileToCreate['filename'];

    $md5FileText = "#Squid's Checksum\r\n";
    $md5FileText .= "#\r\n";

    $updateFlag = false;

    $filenameLength = 0;
    foreach ($md5FileToCreate['files'] as $file) {
      if ( strlen($file['file']) > $filenameLength )
      {
        $filenameLength = strlen($file['file']);
      }
    }

    foreach ($md5FileToCreate['files'] as $file) {

# check to see if file wound up getting deleted.
      if ( ! file_exists($file['file']) ) {
        continue;
      }

      if ( $file['changed'] )
      {
#        logger($file['file']." Changed!\n");
        if ( $updateChanged )
        {
          $file['update'] = true;
        }
      } else if ( ! $file[$md5Settings['Algorithm']] ) {
        $file['update'] = true;
      }

      if ( $file['update'] )
      {
        $updateFlag = true;

        if ( is_open($file['file']) ) {
          logger($file['file']." is currently open... Skipping\n");
          continue;
        }

        logger("Creating ".$md5Settings['Algorithm']." checksum for ".str_pad($file['file'],$filenameLength));

        $md5FileText .= "#".$md5Settings['Algorithm']."#";

# replace any "#" in filename with "/" (only character not allowed in both windows and linux

        $tempFilename = basename($file['file']);
#        $tempFilename = str_replace("#","/",$tempFilename);
        $md5FileText .= $tempFilename."#".timeToCorz(filemtime($file['file']))."\r\n";

        $fileSize = filesize($file['file']);

        $fileStartTime = microtime(true);


        $totalBytes = $totalBytes + $fileSize;

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
            $hashTemp = exec('b2sum -a blake2s "'.$file['file'].'"');
            $hashTemp1 = explode(" ",$hashTemp);
            $hash = $hashTemp1[0];
            break;
        }

        $fileTotalTime = microtime(true) - $fileStartTime;

        $fileAverageTime = intval($fileSize / $fileTotalTime);
        logger("   Speed: ".str_pad(human_filesize($fileAverageTime),9," ",STR_PAD_LEFT)."/s");
        logger(" ( ".str_pad(human_filesize($fileSize),9," ",STR_PAD_LEFT));
        logger(" / ".date("i:s",$fileTotalTime));
        logger(" )\n");


        $md5FileText .= $hash."  *".basename($file['file'])."\r\n";
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
        $md5FileText .= basename($file['file'])."#".timeToCorz($fileTime)."\r\n";
        $md5FileText .= $file[$md5Settings['Algorithm']]."  *".basename($file['file'])."\r\n";
      }
    }
    if ( $updateFlag )
    {
      file_put_contents($md5Filename,$md5FileText);
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

  $filePath = pathinfo($filename,PATHINFO_DIRNAME);

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
   
   $md5Array[$md5Entry]['time'] = timeToCorz(filemtime($md5Entry));
   $md5Array[$md5Entry]['md5'] = $md5Calculated;

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
  global $video, $md5Settings, $excludedFiles, $includedFiles;

  $files_to_create = array();
  $separate = $md5Settings['Separate'];

  $test = array_diff(scandir($path), array(".",".."));

  $temp = array();

  if ( ! $separate ) {
    if ( file_exists($path."/".basename($path).".hash") ) {
      $md5Array = parseMD5($path."/".basename($path).".hash");
    } else {
      $md5Array = array();
    }
  } 

  foreach ($test as $file)
  {
    $filename = $path."/".$file;
#    $filename = $path.$file;
    if ( is_dir($filename) ) {
      if ( $recursive ) {
#        echo $filename."\n";
        getFiles($filename,true);
      }
      continue;
    }


    if ( $md5Settings['Excluded'][pathinfo($filename, PATHINFO_EXTENSION)] ) {
      continue;
    }

    if ( ! $md5Settings['IncludeAll'] ) {
      if ( ! $md5Settings['Included'][pathinfo($filename, PATHINFO_EXTENSION)] ) {
        continue;
      }
    }

    if ( pathinfo($filename, PATHINFO_EXTENSION) == "hash" ) {
      continue;
    }

    if ( $separate ) {
      $files_to_create[$filename]['filename'] = $filename.".hash";
      $files_temp = array();

      if ( file_exists($filename.".hash") ) {
        $md5Array = parseMD5($filename.".hash");
      } else {
        unset($md5Array);
      }
      if ( is_array($md5Array[$filename]) ) {
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
    $files_to_create[$path]['filename'] = $path.".hash";
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


$foundFlag = false;
foreach ($AllSettings as $Settings)
{
  if ( strpos($commandPath,$Settings['Path']) === 0 )
  {
    $md5Settings = $Settings;
#    print_r($Settings);
    $foundFlag = true;

    $startTime = time();
    getFiles($commandPath,false);
    $totalTime = time() - $startTime;

#$time = secondsToTime($totalTime);

 #   logger("\n");
#    logger("Total Time Elapsed: ");

    $readableTime = readableTime($totalTime);

#    echo $readableTime;

    if ( $timePaused ) {
 #     logger("  Not including parity check time of ".readableTime($timePaused));
    }
  #  logger("\n");


   # logger("Total Calculated: ".human_filesize($totalBytes)."\n");


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


    #logger("Average Speed: $totalDisplayed/s\n");
  }
}


?>
