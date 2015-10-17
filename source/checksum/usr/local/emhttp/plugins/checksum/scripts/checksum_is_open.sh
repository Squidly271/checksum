#!/bin/bash

if [[ -e /tmp/checksum/openqueue ]]
then
  awk '!a[$0]++' /tmp/checksum/openqueue > /tmp/checksum/openqueue1
  rm /tmp/checksum/openqueue

  cat /tmp/checksum/openqueue1 | while read line
  do
    fullPath=$line
    filename=$(basename "$fullPath")
    dirname=$(dirname "$fullPath")

    lsof +D "$dirname" | grep "$filename" > /tmp/checksum/is_open_tempfile
    filesize=$(stat -c%s "/tmp/checksum/is_open_tempfile")

    if [[ $filesize == 0 ]]
    then
      echo "$fullPath no longer open.  Adding to queue" >> /tmp/checksum/log.txt
      echo "***$(date +%s)***$dirname" >> /tmp/checksumPipe
    else
      echo "$fullPath still open.  Rescheduling" >> /tmp/checksum/log.txt
      echo "$fullPath" >> /tmp/checksum/openqueue
    fi
  done
fi


