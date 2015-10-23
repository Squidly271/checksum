#!/bin/bash

sharePath=$(cat "/tmp/checksum/sharePath")
percent=$(cat "/tmp/checksum/percent")
lastPercent=$(cat "/tmp/check/lastPercent")

echo /usr/local/emhttp/plugins/checksum/scripts/verify.php "$sharePath" $percent $lastPercent | at -M NOW >dev/null 2>&1
