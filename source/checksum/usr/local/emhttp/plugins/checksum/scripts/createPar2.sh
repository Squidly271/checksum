#!/bin/bash

percent=$1
shift
path=$1
shift

par2file=`basename "$path"`

filename="$path/$par2file.par2"
/usr/local/emhttp/plugins/checksum/include/checksumPar2 c -m128 -n1 -r$percent "$filename" "$@" >> /tmp/checksum/par2log.txt
