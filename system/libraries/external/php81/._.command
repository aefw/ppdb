#!/bin/bash
#._.command
export CURRENT="$(dirname "$0")"
cd $CURRENT

cd PHPMailer/
/usr/bin/php8.1 ~/bin/composer require phpmailer/phpmailer
cd ../
cd PHPspreadsheet/
/usr/bin/php8.1 ~/bin/composer require phpoffice/phpspreadsheet
cd ../
cd PHPWord/
/usr/bin/php8.1 ~/bin/composer require phpoffice/phpword
cd ../
#cd google/apiclient
#/usr/bin/php8.1 ~/bin/composer require google/apiclient
#cd ../../
