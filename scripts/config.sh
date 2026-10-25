#!/bin/env bash
##
## Initial server configuration for website
##

# ENVIRONMENT
# import variables overriding
[[ -f "$HOME/environment.sh" ]] && source $HOME/environment.sh

ROOT=${ROOT:-$HOME}
LOCAL=${LOCAL:-$ROOT/local}
WEBROOT=${WEBROOT:-$ROOT/www/arcensiel}
MYSQL=${MYSQL:-$LOCAL/mysql/bin/mysql --defaults-file="$HOME/config/mysql/mysql.cnf"}
PHP=${PHP:-$LOCAL/php/bin/php}

PAHEKO=${PAHEKO:-$ROOT/www/paheko}
DEFAULT_PAHEKO=${DEFAULT_PAHEKO:-1.3.24}

ZIPFILE=$ROOT/data/config_backup.zip
KEYFILE=$ROOT/data/keys_backup.zip

# OPTIONS
parameters="--root --activate --start --ignore --keys --composer --sql --populate --set-root --paheko"
named_parameters="--backup --version"

help=$(cat << EOF
This script allows to configure the database for arcensiel website.

Usage: mysql_init.sh [options]
General options
--help          Prints this help and exits.
--root          Also config system-wide settings. Require root privileges.
--unsafe        Do not backup config files before potentially overwriting them.
--activate      Enable sites and services.
--start         Launch services after configuration.
--ignore        Do no not restore configuration files from backup.
--backup=/path  Path to zip archive holding config files. Default is \$ROOT/data/config_backup.zip
--keys          Restore keys (website, paheko, mail, ...). --root is required for dkim keys restore.
--keyfile=/path Path to zip archive for keys. Default is \$ROOT/data/keys_backup.zip

Website options
--composer      Run composer to install external libraries

MySQL options
--sql           Configure website database
--populate      Also insert default data in database
--set-root      Use to set default root password if no password is set

Paheko options
--paheko        Install paheko if missing or not matching version
--version=[]    Paheko version to install. Ignored if --paheko missing

Some variables can be overriden if defined in ~/environment.sh
ROOT            Root user path. Default \$HOME
WEBROOT         Root of arcensiel website. Default ~/www/arcensiel
LOCAL           Path for local binaries. Default ~/local
MYSQL           Command to execute local mysql. Default \$LOCAL/mysql/bin/mysql --defaults-file="\$HOME/config/mysql/mysql.cnf"
PHP             Command to execute local PHP. Default \$LOCAL/php/bin/php
PAHEKO          Root of paheko website. Default ~/www/paheko
DEFAULT_PAHEKO  Paheko version to use if not specified. Default 1.3.24

Debug commands
/usr/sbin/nginx -t -c /path/to/config/file
# Test Nginx configuration

ps -eo pid,user,lstart,args | grep -E '[p]hp-fpm|[n]ginx|[m]ysql' | grep 'arcensiel'
# List active processes for user

sudo ss -xlp | grep -E 'php|nginx|mysql\.sock' | grep 'arcensiel'
# List active sockets for user

systemctl --user list-units --type=service
# List services installed for user

systemd-analyze --user verify [service]
# Verify unit file to debug errors
EOF
)

source $WEBROOT/scripts/parser.sh "$@"

chmod -R 700 $WEBROOT/scripts/*

# General restore
if [[ -z $(grep 'ignore' <<< $args) ]]; then
    if [[ -z $(grep 'unsafe' <<< $args) ]]; then
        params=
        if [[ -n $(grep 'root' <<< $args) ]]; then
            params+='--root '
        fi

        $WEBROOT/scripts/backup.sh "$params --config"
    fi
    
    if [[ -n $(grep 'backup' <<< $args) ]]; then
        version=$(echo $args | grep 'version' | cut -d= -f2)
        ZIPFILE=${ZIPFILE:-$(echo $args | grep 'backup' | cut -d= -f2)}
    fi

    if [[ ! -f "$ZIPFILE" ]]; then
        echo "Error: could not find backup archive: $ZIPFILE" && exit 1
    elif [[ $(echo $ZIPFILE | rev | cut -d. -f1 | rev) != "zip" ]]; then
        echo "Error: unexpected archive format: $(echo $ZIPFILE | rev | cut -d. -f1 | rev)" && exit 1
    fi

    unzip "$ZIPFILE" -d "$LOCAL/src/backup"
    mv $LOCAL/src/backup/config $ROOT
    mv $LOCAL/src/backup/.config $ROOT
    mv -n $LOCAL/src/backup/www/libraries $WEBROOT
    
    if [[ -n $(grep 'root' <<< $args) ]]; then
        sudo mv $LOCAL/src/backup/root/etc/nginx /etc/nginx
        sudo mv $LOCAL/src/backup/root/etc/postfix /etc/postfix
        sudo mv $LOCAL/src/backup/root/etc/opendkim /etc/opendkim
    fi

    rm -r "$LOCAL/src/backup"
fi

# Keys
if [[ -n $(grep 'keys' <<< $args) ]]; then
    if [[ -n $(grep 'keyfile' <<< $args) ]]; then
        version=$(echo $args | grep 'version' | cut -d= -f2)
        KEYFILE=${KEYFILE:-$(echo $args | grep 'backup' | cut -d= -f2)}
    fi

    if [[ ! -f "$KEYFILE" ]]; then
        echo "Error: could not find backup archive: $KEYFILE" && exit 1
    elif [[ $(echo $KEYFILE | rev | cut -d. -f1 | rev) != "zip" ]]; then
        echo "Error: unexpected archive format: $(echo $KEYFILE | rev | cut -d. -f1 | rev)" && exit 1
    fi

    unzip "$KEYFILE" -d "$LOCAL/src/key_backup"
    mdkir -p $ROOT/data/keys
    mv $LOCAL/src/key_backup/paheko/* $ROOT/data/keys
    cp $LOCAL/src/key_backup/dkim/* $ROOT/data/keys
    mv -n $LOCAL/src/backup/www/.private $WEBROOT

    if [[ -n $(grep 'root' <<< $args) ]]; then
        sudo mv $LOCAL/src/key_backup/dkim /etc/opendkim/keys/arcensiel.org
        sudo chown -R arcensiel:sites /etc/opendkim/keys/arcensiel.org
    fi

    rm -r "$LOCAL/src/key_backup"
    chown -R arcensiel:sites $ROOT/data/keys $WEBROOT/.private
    chmod 600 $ROOT/data/keys/* $WEBROOT/.private/*
    chmod 700 $ROOT/data/keys $WEBROOT/.private
fi

# Website
if [[ -n $(grep 'composer' <<< $args) ]]; then
    if [[ ! -f $WEBROOT/libraries/composer ]]; then
        echo "Missing composer. Installing from source..."
        mkdir -p $WEBROOT/libraries
        $PHP -r "copy('https://getcomposer.org/installer', '$WEBROOT/libraries/composer-setup.php');"
        $PHP $WEBROOT/libraries/composer-setup.php/composer-setup.php --install-dir=$WEBROOT/libraries --filename=composer
    fi

    echo "Installing libraries with composer..."
    $PHP "$WEBROOT/libraries/composer" install
fi

# Paheko
if [[ -n $(grep 'paheko' <<< $args) ]]; then
    version=$(echo $args | grep 'version' | cut -d= -f2)
    version=${version:-$DEFAULT_PAHEKO}
    
    install=true
    if [[ -d "$PAHEKO" ]]; then
        set_version=$(cat $PAHEKO/VERSION)

        if [[ -z $set_version ]]; then
            echo "Impossible to check installed version of Paheko. Reinstalling from source."
        elif [[ $set_version != $version ]]; then
            echo "Invalid Paheko version detected: $version != $set_version. Reinstalling from source"
        else
            install=false
        fi
    else
        install=false
    fi

    if [[ $install ]]; then
        echo "No Paheko installation detected in '$PAHEKO'. Installing from remote archive."
        
        # Get archive
        echo "Downloading Paheko tarball..."
        mkdir -p $LOCAL/src
        curl -s "https://fossil.kd2.org/paheko/uv/paheko-$version.tar.gz" -o "$LOCAL/src/paheko-1.3.24.tar.gz" --fail || (echo "Error: could not find remote archive for specified version: $version" && exit 1)

        # Extract files and config repo
        echo "Installing Paheko files..."
        mkdir -p $ROOT/www/paheko
        mkdir -p $ROOT/data/paheko
        tar -xf $LOCAL/src/paheko-1.3.24.tar.gz -C $ROOT/www/paheko --strip-components=1
        
        mv $PAHEKO/data/plugins $PAHEKO
        rm -r $PAHEKO/data
        rm $PAHEKO/apache* $PAHEKO/.htaccess*
        rm $LOCAL/src/$LOCAL/src/paheko-1.3.24.tar.gz
    fi
fi

# SQL
if [[ -n $(grep 'sql' <<< $args) ]]; then
    params=
    if [[ -n $(grep 'set-root' <<< $args) ]]; then
        params+='--set-root '
    fi
    if [[ -n $(grep 'sql' <<< $args) ]]; then
        params+='--populate '
    fi

    echo "Configuration MySQL to manage arcensiel database..."
    source $WEBROOT/scripts/mysql_init.sh "$params"
fi


exit

# Global activation
if [[ -n $(grep 'activate' <<< $args) ]]; then
    ln -sf $ROOT/config/nginx/sites-available/arcensiel.conf $ROOT/config/nginx/sites-enabled/arcensiel.conf
    ln -sf $ROOT/config/nginx/sites-available/paheko.conf $ROOT/config/nginx/sites-enabled/paheko.conf
    
    if [[ -n $(grep 'root' <<< $args) ]]; then
        sudo ln -sf /etc/nginx/sites-available/arcensiel.conf /etc/nginx/sites-enabled/arcensiel.conf
    fi
    
    loginctl enable-linger arcensiel
    systemctl --user enable nginx nginx-paheko php-fpm mysql mail-timer
fi

systemctl --user daemon-reload
if [[ -n $(grep 'root' <<< $args) ]]; then
    sudo postfix reload
fi

if [[ -n $(grep 'start' <<< $args) ]]; then
    systemctl --user start nginx
fi