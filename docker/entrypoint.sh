#!/bin/sh
set -eu

php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-demo

exec php -S 0.0.0.0:8000 -t public public/router.php
