#!/bin/bash
SF_DIR=$1

# check the directory exists
if [ ! -d "$SF_DIR" ]; then
	echo "Directory '$SF_DIR' does not exist"
	exit
fi

rm -rf $SF_DIR/var/cache/* $SF_DIR/var/logs/* $SF_DIR/var/sessions/*

HTTPDUSER=`ps axo user,comm | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1`
chmod -R +a "$HTTPDUSER allow delete,write,append,file_inherit,directory_inherit" $SF_DIR/var
chmod -R +a "`whoami` allow delete,write,append,file_inherit,directory_inherit" $SF_DIR/var
