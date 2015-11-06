#!/bin/bash

instance=$1
toVerify="/tmp/checksum/toVerifysh$1"
failAnalysisCorrupt="/tmp/checksum/failCorrupt$1"
failAnalysisUpdated="/tmp/checksum/failUpdated$1"

verifyLogger()
{
  loggerLine=`date +"%b%e %Y %R:%S"`
  loggerLine="$loggerLine $1"

  logSize=`stat --printf="%s" /tmp/checksum/verifylog.txt`
  echo $logSize

  if [[ $logSize -gt 500000 ]]
  then
    echo "Log Size > 500000 bytes.  Rotated.  You need to close and reopen this window" >> "/tmp/checksum/verifylog.txt"
    if [ -e "/boot/config/plugins/checksum/settings/savelogs" ]
    then
      echo "here"
      logDate=`date +"%x %X" | tr "/" "-" | tr ":" "-"`

      logFile="/boot/config/plugins/checksum/logs/Verify-$logDate.txt"
      cp "/tmp/checksum/verifylog.txt" "$logFile"
      sed -i 's/$/\r/' "$logFile"
    fi
    rm "/tmp/checksum/verifylog.txt" > /dev/null 2>&1
  fi
  echo "$loggerLine" >> /tmp/checksum/verifylog.txt
}

failureLogger()
{
  loggerLine=`date +"%b%e %Y %R:%S"`
  loggerLine="$loggerLine $1"

  echo "$loggerLine" >> /tmp/checksum/failurelog.txt
}

checkParity()
{
  if [ -e "/boot/config/plugins/checksum/settings/PauseDuringParity" ]
  then
    if [ $(grep mdResync= /var/local/emhttp/var.ini | awk '{print $3}' FS='[="]') -gt 0 ]
    then
      verifyLogger "Pausing For Parity Check / Rebuild"
      echo "Parity Check Running" > /tmp/checksum/parity
      for (( ; ; ))
      do
        if [ -e "/boot/config/plugins/checksum/settings/PauseDuringParity" ]
        then
          if [ $(grep mdResync= /var/local/emhttp/var.ini | awk '{print $3}' FS='[="]') -gt 0 ]
          then
            sleep 1
          else
            verifyLogger "Parity Check / Rebuild Finished.  Resuming"
            rm "/tmp/checksum/parity" > /dev/null 2>&1
            break
          fi
        fi
      done
    fi
  fi
}

totalFiles=0
totalCorrupt=0

cat $toVerify | while read LINE
do
  checkParity

  algorithm=$LINE
  read LINE
  checksum=$LINE
  read LINE
  fileTime=$LINE
  read LINE
  fileName=$LINE

  if [ ! -e $fileName ]
  then
    continue
  fi

  startTime=$(($(date +%s%N)/1000000))

  if [ "$algorithm" == "md5" ]
  then
    md5Calculated=`md5sum "$fileName"`
    md5Calculated=`echo "$md5Calculated" | cut -f1 -d" "`
  fi

  if [ "$algorithm" == "sha1" ]
  then
    md5Calculated=`sha1sum "$fileName"`
    md5Calculated=`echo "$md5Calculated" | cut -f1 -d" "`
  fi

  if [ "$algorithm" == "sha256" ]
  then
    md5Calculated=`sha256sum "$fileName"`
    md5Calculated=`echo "$md5Calculated" | cut -f1 -d" "`
  fi

  if [ "$algorithm" == "blake2" ]
  then
    md5Calculated=`/usr/local/emhttp/plugins/checksum/include/b2sum -a blake2s "$fileName"`
    md5Calculated=`echo "$md5Calculated" | cut -f1 -d" "`
  fi
  endTime=$(($(date +%s%N)/1000000))
  fileSize=`stat --printf="%s" "$fileName"`

  humanReadable=`/usr/local/emhttp/plugins/checksum/scripts/humanReadable.php $startTime $endTime $fileSize`

  totalFiles=$[$totalFiles + 1]
  echo $totalFiles > "/tmp/checksum/totalFiles$1"

  if [ "$md5Calculated" == "$checksum" ]
  then
    verifyLogger "$algorithm Passed $fileName  ( $humanReadable )"
  else
    logLine="$algorithm *** FAILED *** $fileName"

    totalCorrupt=$[$totalCorrupt + 1]
    echo $totalCorrupt > "/tmp/checksum/totalCorrupt$1"

    fileModificationTime=`stat --printf "%Y" "$fileName"`

    if [ "$fileModificationTime" == "$fileTime" ]
    then
      echo "$fileName" >> $failAnalysisCorrupt
      verifyLogger "$logLine  CORRUPTED"
      failureLogger "$logLine  CORRUPTED"
    else
      echo "$fileName" >> $failAnalysisUpdated
      verifyLogger "$logLine  CORRUPTED"
      failureLogger "$logLine  CORRUPTED"
    fi
  fi

done

totalFiles=`cat "/tmp/checksum/totalFiles$1"`

if [[ -e "/tmp/checksum/totalCorrupt$1" ]]
then
  totalCorrupt=`cat "/tmp/checksum/totalCorrupt$1"`
else
  totalCorrupt=0
fi

verifyLogger "Total Files: $totalFiles  Total Corrupt: $totalCorrupt"

if [[ $totalCorrupt != "0" ]]
then
  verifyLogger "Failure Analysis:"
fi
if [[ -e $failAnalysisCorrupt ]]
then
  verifyLogger "Corrupted Files:"
  verifyLogger ""
  cat $failAnalysisCorrupt | while read LINE
  do
    verifyLogger "$LINE"
  done
fi
if [[ -e "$failAnalysisUpdated" ]]
then
  verifyLogger "Updated Files:"
  verifyLogger ""

  cat $failAnalysisUpdated | while read LINE
  do
    verifyLogger "$LINE"
  done
fi

rm "/tmp/checksum/totalFiles$1" >/dev/null 2>&1
rm "/tmp/checksum/totalCorrupt$1" >/dev/null 2>&1


exit $totalCorrupt
