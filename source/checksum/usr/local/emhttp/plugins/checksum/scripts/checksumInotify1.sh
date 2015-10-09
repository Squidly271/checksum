#!/bin/bash

pipe=/tmp/checksumPipe

cat $pipe | while read line
do
#  echo "Line returned: $line"
  /tmp/GitHub/test.php "$line"
done

