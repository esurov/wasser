#!/usr/bin/env scotty

# @servers production=gene@wpcodus.com
# @macro deploy pullCode installDeps migrate optimize
# @macro rollback resetHead installDeps migrate optimize

APP_DIR="/home/gene/wasser"
BRANCH="main"

# @before
announce() {
    echo "Deploying wasser.surstar3.com …"
}

# @task on:production
pullCode() {
    cd "$APP_DIR"
    git fetch --prune origin
    git reset --hard "origin/$BRANCH"
}

# @task on:production
installDeps() {
    cd "$APP_DIR"
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
}

# @task on:production
migrate() {
    cd "$APP_DIR"
    php artisan migrate --force
    php artisan storage:link || true
}

# @task on:production
optimize() {
    cd "$APP_DIR"
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
}

# @task on:production
resetHead() {
    cd "$APP_DIR"
    git reset --hard HEAD~1
}

# @after
done() {
    echo "Deploy finished."
}

# @error
failed() {
    echo "Deploy failed — check the output above."
}
