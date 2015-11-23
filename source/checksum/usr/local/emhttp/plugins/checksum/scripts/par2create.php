#!/usr/bin/php
<?php
function getDirs($dirPath)
{
  global $directories;

  $current = array_diff(scandir($dirPath),array(".",".."));

  foreach ($current as $dir)
  {
    $tempPath = $dirPath."/".$dir;
    $tempPath = str_replace("//","/",$tempPath);
    if ( is_dir($tempPath) )
    {
      $directories[] = $tempPath;
      getDirs($tempPath);
    }
  }
}

$arguments = $argv[1];
$splitarguments = explode("***",$arguments);


$percent = $splitarguments[0];
$path = $splitarguments[1];
$options = $splitarguments[2];

if ( strpos($options,"verify") )
{
  $par2File = $splitarguments[3];

  exec('/usr/local/emhttp/plugins/checksum/include/checksumPar2 v "'.$par2File.'" >> /tmp/checksum/par2log.txt ');
  return;
}

if ( strpos($options,"repair") )
{
  $par2File = $splitarguments[3];

  exec('/usr/local/emhttp/plugins/checksum/include/checksumPar2 r "'.$par2File.'" >> /tmp/checksum/par2log.txt ');
  return;
}


$allContents = scandir($path);



if ( strpos($options,"recursive") )
{
  getDirs($path); 
} else {
  $directories = array();
  $directories[] = $path;
}
#$test = print_r($directories,true);
#file_put_contents("/tmp/checksum/par2log.txt",$test);

foreach ($directories as $path)
{
  $allContents = scandir($path);

  $files = "";

  if ( ! strpos($options,"overwrite") )
  {
    $filename = $path."/".basename($path).".par2";

    if ( is_file($filename) )
    {
      file_put_contents("/tmp/checksum/par2log.txt","Par2 set $filename already exists... Skipping\n",FILE_APPEND);
      continue;
    }
  }


  foreach ($allContents as $contents)
  {
    if ( fnmatch("*.hash",$contents, FNM_CASEFOLD) )  { continue; }
    if ( fnmatch("*.md5",$contents,FNM_CASEFOLD) )    { continue; }
    if ( fnmatch("*.sha1",$contents,FNM_CASEFOLD) )   { continue; }
    if ( fnmatch("*.sha256",$contents,FNM_CASEFOLD) ) { continue; }
    if ( fnmatch("*.blake2",$contents,FNM_CASEFOLD) ) { continue; }
    if ( fnmatch("*.par2",$contents,FNM_CASEFOLD) )   { continue; }

    if ( is_file("$path/$contents") )
    {
      if ( filesize("$path/$contents") )
      {
        $tempFile = $contents;

        $files .= ' "'.$path.'/'.$tempFile.'"';
      }
    }
  }

  if ( $files == "" )
  {
    file_put_contents("/tmp/checksum/par2log.txt","\n\nSkipping $path - no files\n\n",FILE_APPEND);
    continue;
  }
  file_put_contents("/tmp/checksum/par2log.txt","\n\nProcessing $path\n\n",FILE_APPEND);
  file_put_contents("/tmp/checksum/par2job","$path");
  $commandLine = '/usr/local/emhttp/plugins/checksum/scripts/createPar2.sh '.$percent.' "'.$path.'" '.$files;
  echo $commandLine."\n";
  exec($commandLine);
  unlink("/tmp/checksum/par2job");
}
?>
