#!/bin/bash

share=$(cat "/tmp/checksum/verifyShare")
percent=$(cat "/tmp/checksum/verifyPercent");
lastPercent=$(cat "/tmp/checksum/verifyLast");


command="/usr/local/emhttp/plugins/checksum/scripts/verify.php \"$share\" $percent $lastPercent"

echo "$command"
echo "$command" | at NOW >/dev/null 2>&1

