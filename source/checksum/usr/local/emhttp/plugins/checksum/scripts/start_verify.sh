#!/bin/bash

####################################################################
#                                                                  #
# Routine that actually starts the background verification process #
#                                                                  #
####################################################################


share=$(cat "/tmp/checksum/verifyShare")
percent=$(cat "/tmp/checksum/verifyPercent");
lastPercent=$(cat "/tmp/checksum/verifyLast");


command="/usr/local/emhttp/plugins/checksum/scripts/verify.php \"$share\" $percent $lastPercent >/dev/null 2>&1"

#echo "$command"
echo "$command" | at NOW >/dev/null 2>&1

