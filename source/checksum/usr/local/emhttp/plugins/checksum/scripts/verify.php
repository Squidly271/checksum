#!/usr/bin/php
<?PHP
$checksumPaths['VerifyLog'] = "/tmp/checksum/verifylog.txt";
$checksumPaths['usbGlobal']         = "/boot/config/plugins/checksum/settings/global.json";
$unRaidPaths['Variables']     = "/var/local/emhttp/var.ini";
$checksumPaths['VerifyParity'] = "/tmp/checksum/verifyparity";
$checksumPaths['FailureLog'] = "/tmp/checksum/failurelog.txt";

$testPath = $argv[1];      # path to what to test
$percentage = $argv[2];    # percentage of path to check;
$lastPercentage = $argv[3];  # the last percentage left off at;

$originalPath = $testPath;

$diskOnly = false;

if ( is_numeric($testPath) )
{
  $originalPath = "/mnt/disk".$testPath;
  $testPath = "/mnt/user";
  $disk = (int)$argv[1];
  $diskOnly = true;
}

$globalSettings = json_decode(file_get_contents($checksumPaths['usbGlobal']),true);



function mySort($a, $b) {
  global $sortKey;
  global $sortDir;

  $c = strtolower($a[$sortKey]);
  $d = strtolower($b[$sortKey]);

  $return1 = ($sortDir == "Down") ? -1 : 1;
  $return2 = ($sortDir == "Down") ? 1 : -1;

  if ($c > $d) { return $return1; }
  else if ($c < $d) { return $return2; }
  else { return 0; }
}





function is_parity_running()
{
  global $globalSettings, $unRaidPaths;

  if ( ! $globalSettings['Parity'] ) {
    return false;
  }

  $vars = array();

  $vars = parse_ini_file($unRaidPaths['Variables']);

  return ( $vars['mdResync'] != "0" );
}

function failLog($string)
{
  global $checksumPaths;

  $string = date("M j Y H:i:s  ").$string;
  file_put_contents($checksumPaths['FailureLog'],$string,FILE_APPEND);
}


function logger($string, $newLine = true)
{
  global  $checksumPaths, $globalSettings;
  if ( $newLine )
  {
    $string = date("M j Y H:i:s  ").$string;
  }

  if ( @filesize($checksumPaths['VerifyLog']) > 500000 )
  {
    $string = "Log size > 500,000 bytes.  Restarting\nIf this is the last line displayed on the log window, you will have to close and reopen the log window\n".$string;
    file_put_contents($checksumPaths['VerifyLog'],$string,FILE_APPEND);

    if ( $globalSettings['LogSave'] )
    {
      if ( ! is_dir("/boot/config/plugins/checksum/logs") )
      {
        mkdir("/boot/config/plugins/checksum/logs",0777,true);
      }
      $saveLogName = "/boot/config/plugins/checksum/logs/Verify-".date("Y-m-d H-i-s").".txt";
      $saveLogText = file_get_contents($checksumPaths['VerifyLog']);
      $saveLogText = str_replace("\n","\r\n",$saveLogText);
      file_put_contents($saveLogName,$saveLogText);
    }

    unlink($checksumPaths['VerifyLog']);
  }
  file_put_contents($checksumPaths['VerifyLog'],$string,FILE_APPEND);
}



function corzToTime($corzTime)
{
  $dateTime = explode("@",$corzTime);
  $date = str_replace(".","-",$dateTime[0]);
  $time = str_replace(".",":",$dateTime[1]);

  return strtotime($date." ".$time); global $hashFilesToCheck;
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






function getHashFiles($path)
{
  global $hashFilesToCheck;

  if ($path == "/mnt/user/appdata")
  {
    return;
  }

  $directoryContents = array_diff(scandir($path),array(".",".."));

  foreach ($directoryContents as $file)
  {
    $filePath = $path."/".$file;

    if ( is_dir($filePath) )
    {
      getHashFiles($filePath);
    }
    $extension = pathinfo($filePath, PATHINFO_EXTENSION);

    $extension = strtolower($extension);

    switch ($extension) {
      case "hash":
        $hashFilesToCheck[]['Hash'] = $filePath;
        break;
      case "md5":
        $hashFilesToCheck[]['Hash'] = $filePath;
        break;
      case "sha1":
        $hashFilesToCheck[]['Hash'] = $filePath;
        break;
      case "sha256":
        $hashFilesToCheck[]['Hash'] = $filePath;
        break;
      case "blake2":
        $hashFilesToCheck[]['Hash'] = $filePath;
        break;
    }
  }
}

function parseHash($filename)
{
#  echo $filename."\n";

  $filePath = pathinfo($filename,PATHINFO_DIRNAME);
  $algorithm = pathinfo($filename,PATHINFO_EXTENSION);

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

     $md5Array[$md5Entry]['file'] = $md5Entry;


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
#   $md5Entry = $filePath."/".$md5File;
   $md5Entry = $filePath.$md5File;

   $md5Array[$md5Entry]['time'] = @filemtime($md5Entry);
   $md5Array[$md5Entry][$algorithm] = $md5Calculated;
   $md5Array[$md5Entry]['file'] = $md5Entry;
  }
#  print_r($md5Array);

  return $md5Array;
}










##########################################################################################################################
# MAIN ROUTINE



logger("Searching For Hash Files in $testPath\n");

$hashFilesToCheck = array();

getHashFiles($testPath);

print_r($hashFilesToCheck);


$filesToCheck = array();
foreach ($hashFilesToCheck as $file)
{
  $filesToCheck[$file['Hash']][$file['Hash']] = parseHash($file['Hash']);
}

#print_r($filesToCheck);

# get rid of any invalid entries (parsing errors)

$allFilesToCheck = array();

foreach ($filesToCheck as $hashFile)
{
  foreach ($hashFile as $file)
  {
    foreach ($file as $isFile)
    {
      if ( is_file($isFile['file']) )
      {
        $allFilesToCheck[] = $isFile;
      }
    }
  }
}

if ( $diskOnly )
{
  logger("Searching for files contained on disk$disk\n");
  $testFiles = $allFilesToCheck;
  $allFilesToCheck = array();

  foreach ($testFiles as $file)
  {
    $userFilename = $file['file'];

    $diskFilename = str_replace("/mnt/user","/mnt/disk".$disk,$userFilename);
    $file['file'] = $diskFilename;

    if ( is_file($diskFilename) )
    {
      $allFilesToCheck[] = $file;
    }
  }
}


$sortKey = 'time';
$sortDir = 'Up';

usort($allFilesToCheck,"mySort");


$testFiles = array();

if ( $percentage != 100 )
{
  $totalFiles = count($allFilesToCheck);

  $startingIndex = intval( $totalFiles * $lastPercentage / 100 -1 );
  $endingIndex = intval($totalFiles * ($lastPercentage + $percentage) /100 -1 );

  if ($endingIndex >= $totalFiles)
  {
    $endingIndex = $totalFiles - 1;
  }

  $testFiles = $allFilesToCheck;
  unset($allFilesToCheck);
  for ( $index = $startingIndex; $index <= $endingIndex; $index++ )
  {
    $allFilesToCheck[] = $testFiles[$index];
  }
}

$lastPercentage = $percentage + $lastPercentage;

$failedFiles = array();
$totalCount = 0;

foreach ($allFilesToCheck as $file)
{
  if ( is_parity_running() )
  {
    logger("Parity check / rebuild running.  Pausing until completed\n");
    file_put_contents($checksumPaths['VerifyParity'],"parity running");
    while ( true )
    {
      if ( is_parity_running() )
      {
        sleep(600);
        continue;
      } else {
        logger("Parity check / rebuild finished.  Resuming\n");
        @unlink($checksumPaths['VerifyParity']);
        break;
      }
    }
  }

  if ( is_file($file['file']) )
  {
    $failFlag = false;

    $fileSize = filesize($file['file']);
    $loggerLine = $file['file'];
    $startTime = microtime(true);

    if ( $file['md5'] )
    {
      $storedChecksum = $file['md5'];
      $calculatedChecksum = md5_file($file['file']);
    }
    if ( $file['sha1'] )
    {
      $storedChecksum = $file['sha1'];
      $calculatedChecksum = sha1_file($file['file']);
    }
    if ( $file['sha256'] )
    {
      $storedChecksum = $file['sha256'];
      $tempcalculatedChecksum = exec('sha256sum "'.$file['file'].'"');
      $tempcalculatedArray = explode(" ",$tempcalculatedChecksum);
      $calculatedChecksum = $tempcalculatedArray[0];
    }
    if ( $file['blake2'] )
    {
      $storedChecksum = $file['blake2'];
      $tempcalculatedChecksum = exec('/usr/local/emhttp/plugins/checksum/include/b2sum -a blake2s "'.$file['file'].'"');
      $tempcalculatedArray = explode(" ",$tempcalculatedChecksum);
      $calculatedChecksum = $tempcalculatedArray[0];
    }

    if ( $storedChecksum == $calculatedChecksum )
    {
      $loggerLine = "Passed ".$file['file'];
    } else {
      $failFlag = true;

      $loggerLine = "**** Failed ****".$file['file']." ";
      $loggerLine .= "Failure Cause: ";

      if ( filemtime($file['file']) != $file['time'] )
      {
        $loggerLine .= "File Updated.";
        $failed['file'] = $file['file'];
        $failed['corrupt'] = false;
      } else {
        $loggerLine .= "File Corrupt.";
        $failed['file'] = $file['file'];
        $failed['corrupt'] = true;
      }
      $failedFiles[] = $failed;
    }
    $totalTime = microtime(true) - $startTime;
    $averageSpeed = intval($fileSize / $totalTime);

    $loggerLine .= "   ".human_filesize($fileSize)."   ".human_filesize($averageSpeed)."/s\n";
    logger($loggerLine);
    if ( $failFlag )
    {
      failLog($loggerLine);
    }

    $totalCount = $totalCount + 1;
  }

}

$loggerLine = "Results for $originalPath: ";
$loggerLine .= "Total Files: $totalCount ";
$totalPass = $totalCount - count($failedFiles);
$loggerLine .= "Total Passed: ".$totalPass." ";

if ( count($failedFiles)  )
{
  $loggerLine .= "Total Failed: ".count($failedFiles);
  $loggerLine .= "\n\nFailure Analysis\n\n";

  foreach ( $failedFiles as $failed )
  {
    if ( $failed['corrupt'] )
    {
      $loggerLine .= "CORRUPTED ".$failed['file']."\n";
    } else {
      $loggerLine .= "UPDATED ".$failed['file']."\n";
    }
  }

  if ( ! is_dir("/boot/config/plugins/checksum/logs/failure") )
  {
    mkdir("/boot/config/plugins/checksum/logs/failure",0777,true);
  }

  $failureText = str_replace("\n","\r\n",$loggerLine);
  $failureFile = "/boot/config/plugins/checksum/logs/failure/Failed-".date("Y-m-d h-i-s").".txt";
  file_put_contents($failureFile,$failureText);


  if ( $globalSettings['Notify'] )
  {
    exec('/usr/local/emhttp/plugins/dynamix/scripts/notify -e "Checksum Verifier" -s "Hash Verification Failure" -d "One or more files failed verification" -i "warning" -m "'.$loggerLine.'"');
  }
}
$loggerLine .= "\n\n";

logger($loggerLine);










?>
