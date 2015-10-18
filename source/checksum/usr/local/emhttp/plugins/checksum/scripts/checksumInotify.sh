#!/bin/bash

pipe=/tmp/checksumPipe

if [[ -e /boot/config/checksum/numwatches ]]
then
  numwatches=`cat /boot/config/checksum/numwatches`
else
  numwatches=524288
fi

echo "Setting maximum number of watches to $numwatches" >> /tmp/checksum/log.txt
echo $numwatches > /proc/sys/fs/inotify/max_user_watches

if [[ ! -e /tmp/checksum/checksum_inotifywait ]]
then
  cp /usr/bin/inotifywait /tmp/checksum/checksum_inotifywait >/dev/null 2>&1
  chmod +x /tmp/checksum/checksum_inotifywait >/dev/null 2>&1
fi

if [[ ! -p $pipe ]]
then
  mkfifo $pipe
fi

echo "Monitoring $@" >> /tmp/checksum/log.txt

/tmp/checksum/checksum_inotifywait -m -r  -e close_write --timefmt "%s" --format "***%T***%w" "$@" >$pipe 2>>/tmp/checksum/log.txt &

