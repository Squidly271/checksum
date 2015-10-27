#!/bin/bash

pipe=/tmp/checksumPipe

if [[ -e /boot/config/plugins/checksum/numwatches ]]
then
  numwatches=`cat /boot/config/plugins/checksum/numwatches`
else
  numwatches=524288
fi

echo "Setting maximum number of watches to $numwatches" >> /tmp/checksum/log.txt
echo $numwatches > /proc/sys/fs/inotify/max_user_watches

if [[ -e /boot/config/plugins/checksum/numqueue ]]
then
  numqueue=`cat /boot/config/plugins/checksum/numqueue`
else
  numqueue=16384
fi

echo "Setting maximum number of queued events to $numqueue" >> /tmp/checksum/log.txt

if [[ ! -e /tmp/checksum/checksum_inotifywait ]]
then
  cp /usr/bin/inotifywait /tmp/checksum/checksum_inotifywait >/dev/null 2>&1
fi
chmod +x /tmp/checksum/checksum_inotifywait >/dev/null 2>&1


if [[ ! -p $pipe ]]
then
  mkfifo $pipe
fi

echo "Monitoring $@" >> /tmp/checksum/log.txt

/tmp/checksum/checksum_inotifywait -m -r  -e close_write --timefmt "%s" --format "***%T***%w" "$@" >$pipe 2>>/tmp/checksum/log.txt &

