#!/bin/bash
##
## Backup config files on server
##

# ENVIRONMENT
# import variables overriding
[[ -f "$HOME/environment.sh" ]] && source $HOME/environment.sh

ROOT=${ROOT:-$HOME}
LOCAL=${LOCAL:-$ROOT/local}
WEBROOT=${WEBROOT:-$ROOT/www/arcensiel}
MYSQLD=${MYSQLD:-$LOCAL/mysql/bin/mysqldump --defaults-file="$HOME/config/mysql/mysql.cnf"}

DIR=$ROOT/data
DBDIR=$DIR/db

# OPTIONS
parameters="--root --config --keys --db"
named_parameters="--dir --db-dir"

help=$(cat << EOF
This script allows to configure the database for arcensiel website.

Usage: mysql_init.sh [options]
General options
--help          Prints this help and exits.
--root          Also backup system-wide settings. Require root privileges.
--config        Backup config files.
--keys          Backup keys.
--dir=/path     Path where to create backups. Default is \$ROOT/data
--db            Also create a dump of arcensiel and paheko databases
--db-dir=/path  Path where to create dumps. Default is \$ROOT/data/db

Some variables can be overriden if defined in ~/environment.sh
ROOT            Root user path. Default \$HOME
WEBROOT         Root of arcensiel website. Default ~/www/arcensiel
LOCAL           Path for local binaries. Default ~/local
MYSQLD          Command to execute local mysql. Default \$LOCAL/mysql/bin/mysqldump --defaults-file="\$HOME/config/mysql/mysql.cnf"
EOF
)

source $WEBROOT/scripts/parser.sh "$@"

if [[ -z $(grep -E 'config|keys|db' <<< $args) ]]; then
    echo "Choose at least one option to backup. Use --help to view options." && exit 0
fi

if [[ -n $(grep 'dir' <<< $args) ]]; then
    $DIR=$(echo $args | grep 'dir' -d= -f2)
fi

TMPC=$DIR/tmp_cbackup
TMPK=$DIR/tmp_kbackup

# Config
if [[ -n $(grep 'config' <<< $args) ]]; then
    echo "Creating backup of config files..."
    mkdir -p $TMPC/{config,.config}
    mkdir -p $TMPC/www/libraries
    mkdir -p $TMPC/local/php/etc

    cp -r $ROOT/config/nginx $TMPC/config
    cp -r $ROOT/config/paheko $TMPC/config
    cp -r $ROOT/config/mysql $TMPC/config
    cp -r $ROOT/.config/systemd $TMPC/.config
    cp    $ROOT/local/php/etc/php.ini $TMPC/local/php/etc
    rm -rf $TMPC/config/nginx/sites-enabled
    rm -rf $TMPC/.config/systemd/user/default.target.wants
    rm -rf $TMPC/.config/systemd/user/timers.target.wants
    [[ -f $WEBROOT/libraries/composer.json ]] && cp $WEBROOT/libraries/composer.json $TMPC/www/libraries

    if [[ -n $(grep 'root' <<< $args) ]]; then
        mkdir -p $TMPC/root/etc/nginx/sites-available
        mkdir -p $TMPC/root/etc/{postfix,opendkim}

        sudo cp /etc/nginx/sites-available/arcensiel.conf $TMPC/root/etc/nginx/sites-available
        sudo cp /etc/postfix/main.cf $TMPC/root/etc/postfix
        sudo cp -r /etc/postfix/aliases $TMPC/root/etc/postfix
        sudo cp -r /etc/opendkim/*.table $TMPC/root/etc/opendkim
        sudo chown -R arcensiel $TMPC
    fi

    echo "Storing files into archive..."
    archive=$DIR/config_backup.zip
    [[ -f "$archive" ]] && rm $archive
    
    cd $TMPC
    zip -qr "$archive" . && echo "Backup created at $archive!" && echo
    rm -rf $TMPC
fi

# Keys
if [[ -n $(grep 'keys' <<< $args) ]]; then
    echo "Creating backup of keys..."
    mkdir -p $TMPK/{www,paheko}

    cp -r $WEBROOT/.private $TMPK/www
    rm -f $TMPK/www/.private/token.json
    cp -r $ROOT/data/keys/paheko* $TMPK/paheko

    if [[ -n $(grep 'root' <<< $args) ]]; then
        mkdir -p $TMPK/{www,paheko,dkim}

        sudo cp -r /etc/opendkim/keys/arcensiel.org/* $TMPK/dkim
        sudo chown arcensiel:sites $TMPK/dkim/*
    fi

    echo "Storing files into archive..."
    archive=$DIR/keys_backup.zip
    [[ -f "$archive" ]] && rm $archive
    
    cd $TMPK
    zip -qr "$archive" . && echo "Backup created at $archive!" && echo
    chmod 600 $archive
    rm -rf $TMPK
fi

# Databases
if [[ -n $(grep 'db' <<< $args) ]]; then
    echo "Creating backup of databases..."
    
    if [[ $(grep 'db-dir' <<< $args) ]]; then
        $DBDIR=$(echo $args | grep 'dir' -d= -f2)
    fi

    # paheko
    if [[ -f "$ROOT/data/paheko/association.sqlite" ]]; then
        cp $ROOT/data/paheko/association.sqlite $DBDIR/paheko_dump.sqlite
    else
        echo "No database to backup found for Paheko."
    fi
    
    # arcensiel
    source $WEBROOT/scripts/decoder.sh
    secret=$(decode database_arcensiel_secret)

    $MYSQLD -u arcensiel -p$secret arcensiel --no-tablespaces > $DBDIR/arcensiel_dump.sql
    
    chmod 600 $DBDIR/*_dump.sql*
    
    echo "Backups created in $DBDIR!\n"
fi