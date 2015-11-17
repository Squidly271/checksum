#!/bin/bash

/usr/local/emhttp/plugins/checksum/include/b2sum /usr/local/emhttp/plugins/checksum/README.md > /dev/null 2>&1
returnValue=$?

if [[ $returnValue ]]
then
  echo "incompatible" > /tmp/checksum/NotBlakeCompatible
fi

