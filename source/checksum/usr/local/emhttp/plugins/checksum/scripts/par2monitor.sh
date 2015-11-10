#!/bin/bash

trap "rm -f "/tmp/checksum/par2pipe"; exit" INT TERM EXIT

if [[ ! -p "/tmp/checksum/par2pipe" ]]
then
  mkfifo "/tmp/checksum/par2pipe"
fi

while true
do
  if read LINE < "/tmp/checksum/par2pipe"
  then
    echo $LINE
    /usr/local/emhttp/plugins/checksum/scripts/par2create.php "$LINE"
  fi
done
