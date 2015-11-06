#!/bin/bash

####################################################
#                                                  #
# Script that sets up the inotify watches and pipe #
#                                                  #
####################################################



pipe=/tmp/checksumPipe

if [[ -e /boot/config/plugins/checksum/settings/numwatches ]]
then
  numwatches=`cat /boot/config/plugins/checksum/settings/numwatches`
else
  numwatches=524288
fi

echo $numwatches > /proc/sys/fs/inotify/max_user_watches

if [[ -e /boot/config/plugins/checksum/settings/numqueue ]]
then
  numqueue=`cat /boot/config/plugins/checksum/settings/numqueue`
else
  numqueue=16384
fi

echo $numqueue > /proc/sys/fs/inotify/max_queued_events

echo "Set maximum number of watches to $numwatches, maximum queued events to $numqueue" >> /tmp/checksum/log.txt

if [[ ! -p $pipe ]]
then
  mkfifo $pipe
fi

echo "Monitoring $@" >> /tmp/checksum/log.txt

/tmp/checksum/checksum_inotifywait -m -r  -e close_write --timefmt "%s" --format "***%T***%w" "$@" >$pipe 2>>/tmp/checksum/log.txt &

