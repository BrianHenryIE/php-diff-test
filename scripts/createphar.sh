#!/bin/bash

# chmod +x scripts/createphar.sh
# ./scripts/createphar.sh

rm -rf build
composer install --no-dev
wget -O phar-composer.phar https://github.com/clue/phar-composer/releases/download/v1.4.0/phar-composer-1.4.0.phar
mkdir build
cp -R vendor build/vendor
cp -R src build/src
cp -R bin build/bin
cp -R composer.json build
php -d phar.readonly=off phar-composer.phar build ./build/ php-diff-test.phar

rm phar-composer.phar
rm -rf build
composer install

php php-diff-test.phar --version