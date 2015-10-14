#!/bin/bash

pipe=/tmp/checksumPipe

cp /usr/bin/inotifywait /tmp/checksum/checksum_inotifywait >/dev/null 2>&1
chmod +x /tmp/checksum/checksum_inotifywait >/dev/null 2>&1

if [[ ! -p $pipe ]]
then
  mkfifo $pipe
fi

echo "Monitoring $@" >> /tmp/checksum/log.txt

/tmp/checksum/checksum_inotifywait -m -r  -e close_write --timefmt "%s" --format "***%T***%w" "$@" >$pipe 2>>/tmp/checksum/log.txt &

