#!/bin/bash

#################################################################
#                                                               #
# Script that reads the pipe and calls the main checksum script #
#                                                               #
#################################################################

pipe=/tmp/checksumPipe

cat $pipe | while read line
do
#  echo $line
  /usr/local/emhttp/plugins/checksum/scripts/checksum.php "$line"
done

