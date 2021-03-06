# Mod talkpoint

A Moodle activity plugin that allows discussion with text, audio and webcam.

Commands are relative to the directory in which Moodle is installed.

## Dependencies

Moodle 2.9 or Moodle 3.1

The following packages must be added to `composer.json`:

    "require": {
        "silex/silex": "1.3.*",
        "twig/twig": "1.24.*",
        "symfony/browser-kit": "2.8.*",
        "symfony/config": "2.8.*",
        "symfony/css-selector": "2.8.*",
        "symfony/debug": "2.8.*",
        "symfony/dom-crawler": "2.8.*",
        "symfony/event-dispatcher": "2.8.*",
        "symfony/filesystem": "2.8.*",
        "symfony/form": "2.8.*",
        "symfony/http-foundation": "2.8.*",
        "symfony/http-kernel": "2.8.*",
        "symfony/intl": "2.8.*",
        "symfony/locale": "2.8.*",
        "symfony/options-resolver": "2.8.*",
        "symfony/property-access": "2.8.*",
        "symfony/routing": "2.8.*",
        "symfony/security-core": "2.8.*",
        "symfony/security-csrf": "2.8.*",
        "symfony/translation": "2.8.*",
        "symfony/twig-bridge": "2.8.*",
        "symfony/validator": "2.8.*",
        "symfony/yaml": "2.8.*",
        "imagine/imagine": "0.6.*",
        "neutron/silex-imagine-provider": "0.1.*",
        "lstrojny/functional-php": "1.0.0"
    },
    "require-dev": {
        "mockery/mockery": "0.9.4"
    }

# Installation

Install [Composer](https://getcomposer.org/download/) if it isn't already.

    ./composer.phar self-update
    ./composer.phar update
    cd mod
    git clone https://github.com/INTO-University-Partnerships/mod-talkpoint talkpoint
    cd ..
    php admin/cli/upgrade.php

## Apache rewrite rule

Add the following Apache rewrite rule:

    RewriteRule ^(/talkpoints) /mod/talkpoint/bootstrap.php?slug=$1 [QSA,L]

## Bower components

    cd mod/talkpoint
    bower install

## Tests

### PHPUnit

Comment-out line `173` of `lib/phpunit/bootstrap.php`, then:

    php admin/tool/phpunit/cli/util.php --buildcomponentconfigs
    vendor/bin/phpunit -c mod/talkpoint

### Jasmine

    cd mod/talkpoint
    npm install
    node_modules/karma/bin/karma start

## Gulp

There are four [Gulp](http://gulpjs.com/) tasks:

* `gulp clean` deletes the build directory `static/js/build`
* `gulp build` compiles the minified JavaScript app to the build directory `static/js/build`
* `gulp watch` compiles the unminified JavaScript app to the build directory `static/js/build` (and recompiles when necessary)
* `gulp lint` lints the JavaScript app with [ESLint](http://eslint.org/)

## Cron job

A cron job is required to execute the `convert_videos.php` script.

No less frequently than every five minutes is recommended.

    */5 * * * * /path/to/php /path/to/moodle/mod/talkpoint/convert_videos.php >/dev/null 2>&1
