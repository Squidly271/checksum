<?



function status()
{
  $status = exec('ps -A -f | grep -v grep | grep checksumPar2');

  return $status;
}

switch ($_POST['action']) {

case 'status':
  $status = status();


  $t = "<font size='2'><b>Par2 Creation / Verification / Repair Status: ";
  if ( $status )
  {
    $t .= "<font color='red'>Running</font>";
    $s = "<script>$('#createButton').prop('disabled',true);$('#verifyButton').prop('disabled',true);$('#repairButton').prop('disabled',true);";
    $job = file_get_contents("/tmp/checksum/par2job");
    $s .= "$('#job').html('.$job.');</script>";
  } else {
    $t .= "<font color='green'>Idle</font>";
    $s = "<script>$('#createButton').prop('disabled',false);$('#job').html('');</script>";
  }
  $t .= "</b></font>";

  echo "$t$s";
  break;

case 'clear_log':
  if ( file_exists("/tmp/checksum/par2log.txt") )
  {
    file_put_contents("/tmp/checksum/par2log.txt","\n\nLog Cleared.  You will need to close and reopen this windows\n\n",FILE_APPEND);
    unlink("/tmp/checksum/par2log.txt");
  }
  break;

case 'display_path':
  $path = urldecode(($_POST['path']));
  $path = str_replace("***",'"',$path);
  $path = str_replace("**","'",$path);


  if ( !  is_dir($path) )
  {
    $path = "/mnt";
  }

  $pathContents = array_diff(scandir($path),array(".",".."));

  foreach ($pathContents as $contents)
  {
    if ( is_dir($path."/".$contents) )
    {
      $dirContents[] = $contents;
    } else {
      $fileContents[] = $contents;
    }
  }
  if ( is_array($dirContents) )
  {
    natcasesort($dirContents);
  } else {
    $dirContents = array();
  }

  if ( is_array($fileContents) )
  {
    natcasesort($fileContents);
  } else {
    $fileContents = array();
  }


  array_unshift($dirContents,"..");

  $elementCount = count($dirContents);

  $t = "<center>";

  $t .= "<table>";
  $t .= "<tr>";

  $t .= "<td width='20%'><center><b>Folder List</b><br>$path</center>";
  $t .= "</td><td width='20%'><center><b>File List</b></center></td>";
  $t .= "<td></td>";
  $t .= "</tr><tr>";


  $t .= "<td>";

  $t .= "<b><select id='directory' size='20' style='width:500px' onchange='changePath();'>";

  foreach ($dirContents as $dir)
  {
    $fixedPath = $path."/".$dir;
    $fixedPath = str_replace("'","**",$fixedPath);
    $fixedPath = str_replace('"',"***",$fixedPath);
    $fixedPath = str_replace("//","/",$fixedPath);


    if ( $dir == ".." )
    {
      if ( $path != "/" )
      {
        $t .= "<option value='".dirname($path)."'>$dir (Parent Directory)</option>";
      }
    } else {
      $t .= "<option value='$fixedPath'>$dir</option>";
    }
  }

  for ( $i = count($dirContents); $i < 20; $i++ )
  {
    $t .= "<option disabled> </option>";
  }
  $t .= "</select>";

  $t .= "</td><td>";


  $t .= "<select size='20' style='width:500px' onchange='enableVerify();' id='par2select'>";

  foreach ($fileContents as $file)
  {
    if ( fnmatch("*.par2",$file,FNM_CASEFOLD) )
    {
      $fixedPath = $path."/".$file;
      $fixedPath = str_replace("'","**",$fixedPath);
      $fixedPath = str_replace('"',"***",$fixedPath);
      $fixedPath = str_replace("//","/",$fixedPath);

      $t .= "<option value='$fixedPath'>$file</option>";
    } else {
      $t .= "<option disabled>$file</font></option>";
    }
  }
  for ( $i = count($fileContents); $i <= 20; $i++ )
  {
    $t .= "<option disabled> </option>";
  }
  $t .= "</select>";


  $t .= "</td>";
  $t .= "<td>";
  $t .= "<table>
           <tr>
             <td>
               <b>Overwrite existing par2 files?</b>  <input type='checkbox' id='overwrite'></input>
             </td>
           </tr>
           <tr>
             <td>
               <b>Recursive (follow subdirectories)?</b>  <input type='checkbox' id='recursive'></input>
             </td>
           </tr>
           <tr>
             <td>
               <b>Redundancy % </b> <input type='number' class='narrow' value='10' id='redundancy'></input>
             </td>
           </tr>
           <tr>
             <td>
               <input type='button' id='createButton' value='Create Par2' onclick='createPar();'></input>
             </td>
           </tr>
           <tr>
             <td>
               <input type='button' id='verifyButton' value='Verify Files' onclick='verifyPar(&quot;verify&quot;);' disabled></input>
             </td>
           </tr>
           <tr>
             <td>
               <input type='button' id='repairButton' value='Repair Files' onclick='verifyPar(&quot;repair&quot;);' disabled></input>
         </table>";
  $t .= "</td></tr></table>";

  $t .= "<script>$('#folder').html('$path');</script>";

  if ( status() )
  {
    $t .= "<script>$('#createButton').prop('disabled',true);</script>";
    $t .= "<script>$('#verifyButton').prop('disabled',true);</script>";
  } else {
    $t .= "<script>$('#createButton').prop('disabled',false);</script>";
    $t .= "<script>$('#verifyButton').prop('disabled',false);</script>";
  }

  echo $t;
  break;

case 'create_par':
  $path = urldecode(($_POST['path']));
  $percent = urldecode(($_POST['percent']));
  $overwrite = urldecode(($_POST['overwrite']));
  $recursive = urldecode(($_POST['recursive']));

  $options = "blank$overwrite$recursive";

  $job = "Current Job: Create $path, $overwrite";
  file_put_contents("/tmp/checksum/par2job",$job);

  $commandLine = 'echo "'.$percent.'***'.$path.'***'.$options.'" >> /tmp/checksum/par2pipe';
  exec($commandLine.' | AT NOW -M > /dev/null 2>&1');

  echo "done";
  break;

case 'verify_par':
  $path = urldecode(($_POST['path']));
  $par = urldecode(($_POST['par']));
  $verify = urldecode(($_POST['verify']));

  $options = "blank$verify";
  $job = "Current Job: Verify $path";
  file_put_contents("/tmp/checksum/par2job",$job);

  $commandLine = 'echo "'.$percent.'***'.$path.'***'.$options.'***'.$par.'" >> /tmp/checksum/par2pipe';
  file_put_contents("/tmp/command",$commandLine);
  exec($commandLine.' | AT NOW -M > /dev/null 2>&1');

  echo "done";
  break;

}

?>
