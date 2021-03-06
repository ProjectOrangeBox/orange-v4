#!/bin/bash

# if must be root / sudoer
if [ "$EUID" -ne 0 ]
	then echo "Please run using sudo or as root"
  exit
fi

# This Folder
SCRIPTFOLDER="$( cd "$(dirname "$0")" ; pwd -P )"

FILES="$( cd "$(dirname "$0")" ; pwd -P )"
FILES="$FILES/files"

# Where is the application root folder? this Folder -1
ROOT="`dirname $SCRIPTFOLDER`"
ROOT="`dirname $ROOT`"
ROOT="`dirname $ROOT`"
ROOT="`dirname $ROOT`"

CODEIGNITER="$ROOT/vendor/codeigniter/framework";

echo "Application Root Path $ROOT"
echo "Files Folder $FILES"

mkdir -p $ROOT/assets
mkdir -p $ROOT/packages
mkdir -p $ROOT/public
mkdir -p $ROOT/var

mkdir -p $ROOT/var/cache
mkdir -p $ROOT/var/downloads
mkdir -p $ROOT/var/emails
mkdir -p $ROOT/var/gulp
mkdir -p $ROOT/var/logs
mkdir -p $ROOT/var/sessions
mkdir -p $ROOT/var/tmp
mkdir -p $ROOT/var/uploads
mkdir -p $ROOT/var/xdebug

cp -R $FILES/public $ROOT
cp -R $FILES/bin $ROOT

cp -R $FILES/deploy.json $ROOT/deploy.json
cp -R $FILES/gulpfile.js $ROOT/gulpfile.js
cp -R $FILES/package.json $ROOT/package.json

cp -R $CODEIGNITER/application $ROOT

cp $FILES/configs/* $ROOT/application/config

rm -fdr $ROOT/application/cache
rm -fdr $ROOT/application/core
rm -fdr $ROOT/application/hooks
rm -fdr $ROOT/application/logs
rm -fdr $ROOT/application/third_party
rm -fdr $ROOT/application/language

rm $ROOT/application/index.html

rm $ROOT/application/config/index.html
rm $ROOT/application/controllers/index.html
rm $ROOT/application/helpers/index.html
rm $ROOT/application/libraries/index.html
rm $ROOT/application/models/index.html

rm $ROOT/application/views/index.html
rm $ROOT/application/views/errors/index.html
rm $ROOT/application/views/errors/cli/index.html
rm $ROOT/application/views/errors/html/index.html

find $ROOT -type f | xargs chmod 664
find $ROOT -type d | xargs chmod 775

# shell scripts
find $ROOT/bin/* -type f | xargs chmod 775

# var folders
find $ROOT/var/* -type d | xargs chmod 777
