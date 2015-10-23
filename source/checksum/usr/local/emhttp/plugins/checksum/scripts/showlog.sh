#!/bin/bash

exec /usr/bin/tail -n 42 -f "/tmp/checksum/$1" 2>/dev/null


