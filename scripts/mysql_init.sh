#!/bin/bash
##
## Initial configuration of database
## Assumes root user has already been set with correct password
##

# OPTIONS
parameters="--populate --set-root"
help=$(cat << EOF
This script allows to configure the database for arcensiel website.

Usage: is not intended for direct execution. Use config.sh instead.
--help      Prints this help and exits
--populate  Also insert default data in database
--set-root  Use to set default root password if no password is set
EOF
)

# IMPORTS
export parameters help
source $WEBROOT/scripts/decoder.sh
source $WEBROOT/scripts/parser.sh "$@"

root_pwd=$(decode mysql_root_secret)
main_pwd=$(decode database_arcensiel_secret)
mail_pwd=$(decode database_mail_secret)

# SETUP DB
# root user
if [[ -n $(grep 'set-root' <<< $args) ]]; then
    $MYSQL -u root -e "ALTER USER root IDENTIFIED BY '$root_pwd';"
fi

# users and permissions
$MYSQL -u root -p$root_pwd -e   "
                                CREATE DATABASE IF NOT EXISTS arcensiel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                                
                                CREATE USER IF NOT EXISTS 'arcensiel'@'localhost';
                                ALTER USER  'arcensiel'@'localhost' IDENTIFIED BY '$main_pwd';
                                GRANT ALL PRIVILEGES ON arcensiel.* TO 'arcensiel'@'localhost';
                                
                                CREATE USER IF NOT EXISTS 'mailer'@'localhost';
                                ALTER USER 'mailer'@'localhost' IDENTIFIED BY '$mail_pwd';
                                GRANT SELECT, INSERT ON arcensiel.* TO 'arcensiel'@'localhost';
                                
                                FLUSH PRIVILEGES;
                                "

# database structure
$MYSQL -u arcensiel -p$main_pwd -D arcensiel < $WEBROOT/scripts/arcensiel_generator.sql

# database content
if [[ -n $(grep 'populate' <<< $args) ]]; then
    if [[ -f "$WEBROOT/scripts/arcensiel_populator.sql" ]]; then
        $MYSQL -u arcensiel -p$main_pwd -D arcensiel < $WEBROOT/scripts/arcensiel_populator.sql
    else 
        echo "ERROR: Missing 'scripts/arcensiel_populator.sql' to populate database."
        exit 1
    fi
fi