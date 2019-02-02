<?php

namespace sixlive\LaravelPreset;

use Illuminate\Filesystem\Filesystem;
use sixlive\DotenvEditor\DotenvEditor;
use Illuminate\Foundation\Console\Presets\Preset as BasePreset;

class Preset extends BasePreset
{
    public static function install($command)
    {
        $command->task('Install composer dependencies', function () {
            return static::updateComposerPackages();
        });

        $command->task('Install composer dev-dependencies', function () {
            return static::updateComposerDevPackages();
        });

        $command->task('Publish stubs', function () {
            static::publishStubs();
        });

        $command->task('Update ENV files', function () {
            static::updateEnvFile();
        });

        $command->task('Regenerate composer autoload file', function () {
            static::runCommand('composer dumpautoload');
        });

        if($command->confirm('Install Tailwindcss', true)) {
            static::addTailwindcss($command);
        }
    }

    public static function updateComposerPackages()
    {
        $packages = [
            'bensampo/laravel-enum',
            'silber/bouncer:v1.0.0-rc.4',
            'sentry/sentry-laravel',
            'dyrynda/laravel-model-uuid',
        ];

        static::runCommand('composer require '. implode(' ', $packages));
    }


    public static function updateComposerDevPackages()
    {
        $packages = [
            'sempro/phpunit-pretty-print',
            'sensiolabs/security-checker',
        ];

        static::runCommand('composer require --dev '. implode(' ', $packages));
    }

    public static function publishStubs()
    {
        copy(__DIR__.'/stubs/Model.php', app_path('Model.php'));
        copy(__DIR__.'/stubs/phpunit.xml', base_path('phpunit.xml'));
        copy(__DIR__.'/stubs/docker-compose.yml', base_path('docker-compose.yml'));
        copy(__DIR__.'/stubs/BouncerSeeder.php', database_path('seeds/BouncerSeeder.php'));
        tap(new Filesystem, function ($files) {
            $files->copyDirectory(__DIR__.'/stubs/.docker', base_path('.docker'));
        });
    }

    public static function updateEnvFile()
    {
        $editor = new DotenvEditor;
        $editor->load(base_path('.env'));
        $editor->set('DB_PORT', '3307');
        $editor->heading('Docker');
        $editor->set('DOCKER_WEB_PORT', '8080');
        $editor->set('DOCKER_MYSQL_PORT', '3307');
        $editor->save();

        $editor = new DotenvEditor;
        $editor->load(base_path('.env.example'));
        $editor->set('DB_PORT', '3307');
        $editor->set('SENTRY_DSN', '');
        $editor->addEmptyLine();
        $editor->heading('Docker');
        $editor->set('DOCKER_WEB_PORT', '8080');
        $editor->set('DOCKER_MYSQL_PORT', '3307');
        $editor->save();
    }

    private static function runCommand($command)
    {
        return exec(sprintf('%s 2>&1', $command));
    }

    public static function addTailwindcss($command)
    {
        TailwindPreset::install();

        $command->info('Please run "yarn && yarn dev" to compile your fresh scaffolding.');
    }
}
