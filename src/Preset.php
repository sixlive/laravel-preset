<?php

namespace sixlive\LaravelPreset;

use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;
use sixlive\DotenvEditor\DotenvEditor;
use Illuminate\Foundation\Console\Presets\Preset as BasePreset;

class Preset extends BasePreset
{
    protected $command;
    protected $options = [];

    public function __construct($command)
    {
        $this->command = $command;
    }

    public static function install($command)
    {
        $preset = new static($command);

        $preset->run();
    }

    public function run()
    {
        $this->gatherOptions();

        if (!empty($this->options['packages'])) {
            $this->command->task('Install composer dependencies', function () {
                return $this->updateComposerPackages();
            });
        }

        $this->command->task('Install composer dev-dependencies', function () {
            return $this->updateComposerDevPackages();
        });

        $this->command->task('Publish stubs', function () {
            $this->publishStubs();
        });

        $this->command->task('Update ENV files', function () {
            $this->updateEnvFile();
        });

        $this->command->task('Regenerate composer autoload file', function () {
            $this->runCommand('composer dumpautoload');
        });

        if ($this->options['remove_after_install']) {
            $this->command->task('Remove sixlive/laravel-preset', function () {
                $this->runCommand('composer remove sixlive/laravel-preset');
                $this->runCommand('composer dumpautoload');
            });
        }
    }

    private function gatherOptions()
    {
        $this->options = [
            'packages' => $this->promptForPackagesToInstall(),
            'remove_after_install' => $this->command->confirm('Remove sixlive/laravel-preset after install?'),
        ];
    }

    private function promptForPackagesToInstall()
    {
        $possiblePackages = [
            'bensampo/laravel-enum',
            'silber/bouncer:v1.0.0-rc.4',
            'sentry/sentry-laravel',
            'dyrynda/laravel-model-uuid',
        ];

        return Collection::make($possiblePackages)
            ->filter(function ($package) {
                return $this->command->confirm("Install {$package}?", true);
            })
            ->toArray();
    }

    private function updateComposerPackages()
    {
        $this->runCommand(
            'composer require'.implode(' ', $this->options['packages'])
        );
    }

    private function updateComposerDevPackages()
    {
        $packages = [
            'sempro/phpunit-pretty-print',
            'sensiolabs/security-checker',
        ];

        $this->runCommand('composer require --dev '. implode(' ', $packages));
    }

    private function publishStubs()
    {
        copy(__DIR__.'/stubs/Model.php', app_path('Model.php'));
        copy(__DIR__.'/stubs/phpunit.xml', base_path('phpunit.xml'));
        copy(__DIR__.'/stubs/.php_cs', base_path('.php_cs'));
        copy(__DIR__.'/stubs/docker-compose.yml', base_path('docker-compose.yml'));

        if (in_array('silber/bouncer:v1.0.0-rc.4', $this->options['packages'])) {
            copy(__DIR__.'/stubs/BouncerSeeder.php', database_path('seeds/BouncerSeeder.php'));
        }

        tap(new Filesystem, function ($files) {
            $files->copyDirectory(__DIR__.'/stubs/.docker', base_path('.docker'));
        });
    }

    private function updateEnvFile()
    {
        tap(new DotenvEditor, function ($editor) {
            $editor->load(base_path('.env'));
            $editor->set('DB_PORT', '3307');
            $editor->heading('Docker');
            $editor->set('DOCKER_WEB_PORT', '8080');
            $editor->set('DOCKER_MYSQL_PORT', '3307');
            $editor->save();
        });

        tap(new DotenvEditor, function ($editor) {
            $editor = new DotenvEditor;
            $editor->load(base_path('.env.example'));
            $editor->set('DB_PORT', '3307');
            $editor->set('SENTRY_DSN', '');
            $editor->addEmptyLine();
            $editor->heading('Docker');
            $editor->set('DOCKER_WEB_PORT', '8080');
            $editor->set('DOCKER_MYSQL_PORT', '3307');
            $editor->save();
        });
    }

    private function runCommand($command)
    {
        return exec(sprintf('%s 2>&1', $command));
    }
}
