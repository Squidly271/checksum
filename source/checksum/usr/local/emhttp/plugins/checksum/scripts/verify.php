#!/usr/bin/php
<?PHP

################################################
#                                              #
# Main verification routine.                   #
# Parameters: 1 - share or if is a number disk #
#             2 - percent to verify            #
#             3 - starting position            #
#                                              #
################################################


########################
#                      #
# Initialization Stuff #
#                      #
########################

$randomFile = mt_rand();

ini_set('memory_limit',-1);


$checksumPaths['VerifyLog'] = "/tmp/checksum/verifylog.txt";
$checksumPaths['usbGlobal']         = "/boot/config/plugins/checksum/settings/global.json";
$unRaidPaths['Variables']     = "/var/local/emhttp/var.ini";
$checksumPaths['VerifyParity'] = "/tmp/checksum/verifyparity";
$checksumPaths['FailureLog'] = "/tmp/checksum/failurelog.txt";

$testPath = $argv[1];      # path to what to test
$percentage = $argv[2];    # percentage of path to check;
$lastPercentage = $argv[3];  # the last percentage left off at;
$originalLastPercentage = $lastPercentage;

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

##############################################################################
#                                                                            #
# Parses the line created in the toVerify file                               #
# Note: by using files instead of an array, a HUGE amount of memory is saved #
#                                                                            #
##############################################################################


function parseLine($line)
{
  $line = trim($line);
  $parsed = explode("*",$line);

  $file['time'] = $parsed[0];
  $temp = $parsed[1];
  $checksum = $parsed[2];
  $file['file'] = $parsed[3];

  $file[$temp] = $checksum;

  return $file;
}

###############################
#                             #
# Checks if parity is running #
#                             #
###############################

function is_parity_running()
{
  global $globalSettings, $unRaidPaths;

  if ( ! $globalSettings['Parity'] ) {
    return false;
  }

  $vars = array();

  $vars = my_parse_ini_file($unRaidPaths['Variables']);

  return ( $vars['mdResync'] != "0" );
}

##########################################
#                                        #
# Various functions to write to the logs #
#                                        #
##########################################

function failLog($string)
{
  global $checksumPaths;

  $string = date("M j Y H:i:s  ").$string;
  file_put_contents($checksumPaths['FailureLog'],$string,FILE_APPEND);
}


function logger($string, $newLine = true)
{
  global  $checksumPaths, $globalSettings, $randomFile;
  if ( $newLine )
  {
    $string = date("M j Y H:i:s  ")."*$randomFile* ".$string;
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

#######################################
#                                     #
# Converts corz timestamps to seconds #
#                                     #
#######################################

function corzToTime($corzTime)
{
  $dateTime = explode("@",$corzTime);
  $date = str_replace(".","-",$dateTime[0]);
  $time = str_replace(".",":",$dateTime[1]);

  return strtotime($date." ".$time); global $hashFilesToCheck;
}


###########################################
#                                         #
# Converts seconds to an array of d,h,m,s #
#                                         #
###########################################

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

    // return the final array
    $obj = array(
        'd' => (int) $days,
        'h' => (int) $hours,
        'm' => (int) $minutes,
        's' => (int) $seconds,
    );
    return $obj;
}

###########################################
#                                         #
# Returns a string of human readable time #
#                                         #
###########################################

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

######################################################
#                                                    #
# Converts bytes to something a human can understand #
#                                                    #
######################################################
function human_filesize($bytes, $decimals = 2) {
  $size = array(' B',' kB',' MB',' GB',' TB',' PB',' EB',' ZB',' YB');
  $factor = floor((strlen($bytes) - 1) / 3);
  return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

#################################################
#                                               #
# Recursive function to find all the hash files #
#                                               #
#################################################

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

####################################
#                                  #
# Function to parse the hash files #
#                                  #
####################################

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
   $md5Entry = $filePath.$md5File;

   $md5Array[$md5Entry]['time'] = @filemtime($md5Entry);
   $md5Array[$md5Entry][$algorithm] = $md5Calculated;
   $md5Array[$md5Entry]['file'] = $md5Entry;
  }

  return $md5Array;
}
####################################################################################################
#                                                                                                  #
# 2 Functions because unRaid includes comments in .cfg files starting with # in violation of PHP 7 #
#                                                                                                  #
####################################################################################################

function my_parse_ini_file($file,$mode=false,$scanner_mode=INI_SCANNER_NORMAL) {
  return parse_ini_string(preg_replace('/^#.*\\n/m', "", @file_get_contents($file)),$mode,$scanner_mode);
}

function my_parse_ini_string($string, $mode=false,$scanner_mode=INI_SCANNER_NORMAL) {
  return parse_ini_string(preg_replace('/^#.*\\n/m', "", $string),$mode,$scanner_mode);
}


##########################################################################################################################
# MAIN ROUTINE



logger("Searching For Hash Files in $testPath.  Depending upon your file structure this may take a few minutes\n");

$hashFilesToCheck = array();

getHashFiles($testPath);

#print_r($hashFilesToCheck);


$filesToCheck = array();
logger("Parsing Hash Files.  This may take take a few minutes.\n");
foreach ($hashFilesToCheck as $file)
{
  $filesToCheck[$file['Hash']][$file['Hash']] = parseHash($file['Hash']);
}

# $hashFilesToCheck is a huge memory pig and no longer needed anymore.
$hashFilesToCheck = array();


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
        if ($isFile['md5'])
        {
          file_put_contents("/tmp/checksum/toVerify".$randomFile,$isFile['time']."*md5*".$isFile['md5']."*".$isFile['file']."\n",FILE_APPEND);
        }
        if ($isFile['sha1'])
        {
          file_put_contents("/tmp/checksum/toVerify".$randomFile,$isFile['time']."*sha1*".$isFile['sha1']."*".$isFile['file']."\n",FILE_APPEND);
        }
        if ($isFile['sha256'])
        {
          file_put_contents("/tmp/checksum/toVerify".$randomFile,$isFile['time']."*sha256*".$isFile['sha256']."*".$isFile['file']."\n",FILE_APPEND);
        }
        if ($isFile['blake2'])
        {
          file_put_contents("/tmp/checksum/toVerify".$randomFile,$isFile['time']."*blake2*".$isFile['blake2']."*".$isFile['file']."\n",FILE_APPEND);
        }
      }
    }
  }
}


if ( $diskOnly )
{
  $handle = fopen("/tmp/checksum/toVerify".$randomFile,"r");
  logger("Searching for files contained on disk$disk.  This may take a few minutes.\n");

  while (($line = fgets($handle)) !== false )
  {
    $file = parseLine($line);

    $userFilename = $file['file'];

    $diskFilename = str_replace("/mnt/user","/mnt/disk".$disk,$userFilename);
    $file['file'] = $diskFilename;

    if ( is_file($diskFilename) )
    {
      $line = str_replace("/mnt/user","/mnt/disk".$disk,$line);
      file_put_contents("/tmp/checksum/onDisk".$randomFile,$line,FILE_APPEND);
    }
  }
  fclose($handle);
  unlink("/tmp/checksum/toVerify".$randomFile);
  rename("/tmp/checksum/onDisk".$randomFile,"/tmp/checksum/toVerify".$randomFile);
}

exec("sort -n /tmp/checksum/toVerify$randomFile > /tmp/checksum/sorted$randomFile");
unlink("/tmp/checksum/toVerify".$randomFile);
rename("/tmp/checksum/sorted".$randomFile,"/tmp/checksum/toVerify".$randomFile);


$testFiles = array();

if ( $percentage != 100 )
{
  $totalFiles = 0;
  $handle = fopen("/tmp/checksum/toVerify".$randomFile,"r");
  while(!feof($handle))
  {
    $line=fgets($handle);
    $totalFiles = ++$totalFiles;
  }
  fclose($handle);

  $startingIndex = intval( $totalFiles * $lastPercentage / 100 -1 );
  $endingIndex = intval($totalFiles * ($lastPercentage + $percentage) /100 -1 );

  if ($endingIndex >= $totalFiles)
  {
    $endingIndex = $totalFiles;
  }

  $handle = fopen("/tmp/checksum/toVerify".$randomFile,"r");

  if ( $startingIndex != 0 );
  {
    for ($index = 0; $index < $startingIndex; $index++)
    {
      $line = fgets($handle);
    }
  }


  for ( $index = $startingIndex; $index <= $endingIndex; $index++ )
  {
    $line = fgets($handle);
    file_put_contents("/tmp/checksum/toVerifyShort".$randomFile,$line,FILE_APPEND);
  }
  fclose($handle);

  unlink("/tmp/checksum/toVerify".$randomFile);
  rename("/tmp/checksum/toVerifyShort".$randomFile,"/tmp/checksum/toVerify".$randomFile);
}

$lastPercentage = $percentage + $lastPercentage;

$failedFiles = array();
$totalCount = 0;

$handle = fopen("/tmp/checksum/toVerify".$randomFile,"r");

while (( $line = fgets($handle)) != false)
{
  $file = parseLine($line);


  if ( $file['md5'] ) $testAlgorithm = "md5";
  if ( $file['sha1'] ) $testAlgorithm = "sha1";
  if ( $file['sha256'] ) $testAlgorithm = "sha256";
  if ( $file['blake2'] ) $testAlgorithm = "blake2";

  file_put_contents("/tmp/checksum/toVerifysh$randomFile",$testAlgorithm."\n".$file[$testAlgorithm]."\n".$file['time']."\n".$file['file']."\n",FILE_APPEND);

}
fclose($handle);

exec("/usr/local/emhttp/plugins/checksum/scripts/verify.sh $randomFile",$dummyOutput,$returnValue);


if ( $returnValue )
{
  $failLine = "";

  if ( file_exists("/tmp/checksum/failCorrupt$randomFile") )
  {
    $failLine .= "Corrupted Files:\n\n";
    $failLine .= file_get_contents("/tmp/checksum/failCorrupt$randomFile");
  }
  if ( file_exists("/tmp/checksum/failUpdated$randomFile") )
  {
    $failLine .= "Updated Files:\n\n";
    $failLine .= file_get_contents("/tmp/checksum/failUpdated$randomFile");
  }

  if ( $globalSettings['Notify'] )
  {
    exec('/usr/local/emhttp/plugins/dynamix/scripts/notify -e "Checksum Verifier" -s "Hash Verification Failure" -d "One or more files failed verification" -i "warning" -m "'.$failLine.'"');
  }
} else {
  if ( $globalSettings['Success'] )
  {
    exec('/usr/local/emhttp/plugins/dynamix/scripts/notify -e "ChecksumVerifier" -s "Hash Verification Success" -d "All Files from '.$originalPath.' ( '.$percentage.'% starting at '.$originalLastPercentage.'% ) Passed Verification" -i "normal" -m "'.$loggerLine.'"');
  }

  @unlink("/tmp/checksum/failCorrupt$randomFile");
  @unlink("/tmp/checksum/failUpdated$randomFile");
  @unlink("/tmp/checksum/toVerify$randomFile");
}

exit($returnValue);

?>
