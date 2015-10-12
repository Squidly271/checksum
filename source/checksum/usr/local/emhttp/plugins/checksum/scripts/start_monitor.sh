#!/bin/bash

echo "/usr/local/emhttp/plugins/checksum/scripts/checksumInotify.php > /dev/null" | at NOW -M >/dev/null 2>&1
sleep 10
echo "/usr/local/emhttp/plugins/checksum/scripts/checksumInotify1.sh > /dev/null" | at NOW -M >/dev/null 2>&1
