#!/bin/sh

##
# Display the text for the given step
# $1 the text for the step heading
##
step() {
    printf "$1\n"
}

step "Migrating the database"
cd /var/www/html && php artisan migrate --force

if [ -f /var/www/html/database/seeds/BouncerSeeder.php ]; then
    step "Seeding permissions"
    cd /var/www/html && php artisan db:seed --class=BouncerSeeder
fi

step "Starting Apache"
authbind --deep /usr/local/bin/apache2-foreground
