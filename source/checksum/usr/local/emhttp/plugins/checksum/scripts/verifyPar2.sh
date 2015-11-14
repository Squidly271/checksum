#!/bin/bash

failureLogger()
{
  loggerLine=`date +"%b%e %Y %R:%S"`
  loggerLine="$loggerLine $1"

  echo "$loggerLine" >> /tmp/checksum/failurelog.txt
}


/usr/local/emhttp/plugins/checksum/include/checksumPar2 "$@"
returnValue=$?
if [[ $returnValue != 0 ]]
then
  line=$@

  line=`echo $line | sed 's/\"//g'`


  failureLogger "One or more errors occurred during verification / repair $line"
fi

