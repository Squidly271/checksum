#!/bin/bash

pipe=/tmp/checksumPipe

if [[ ! -p $pipe ]]
then
  mkfifo $pipe
fi

echo $@

/tmp/GitHub/checksum_inotifywait -m -r @*.hash -e close_write --timefmt "%s" --format "***%T***%w" $@ >$pipe

