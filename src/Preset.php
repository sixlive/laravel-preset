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
    protected $packages = [
        'bensampo/laravel-enum' => [
            'repo' => 'https://github.com/BenSampo/laravel-enum',
        ],
        'silber/bouncer' => [
            'repo' => 'https://github.com/JosephSilber/bouncer',
            'version' => 'v1.0.0-rc.4',
        ],
        'sentry/sentry-laravel' => [
            'repo' => 'https://github.com/getsentry/sentry-laravel',
        ],
        'dyrynda/laravel-model-uuid' => [
            'repo' => 'https://github.com/michaeldyrynda/laravel-model-uuid',
        ],
        'sempro/phpunit-pretty-print' => [
            'repo' => 'https://github.com/Sempro/phpunit-pretty-print',
            'dev' => true,
        ],
        'sensiolabs/security-checker' => [
            'repo' => 'https://github.com/sensiolabs/security-checker',
            'dev' => true,
        ],
    ];

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
        $this->options = $this->gatherOptions();

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

        if ($this->command->confirm('Install Tailwindcss?', true)) {
            $this->command->task('Install Tailwindcss', function () {
                TailwindPreset::install();
            });

            $this->command->task('Install node dependencies with Yarn', function () {
                $this->runCommand('yarn install');
            });

            $this->command->task('Setup Tailwindcss', function () {
                $this->runCommand('yarn tailwind init');
            });

            $this->command->task('Run node dev build with Yarn', function () {
                $this->runCommand('yarn dev');
            });
        }

        if ($this->options['remove_after_install']) {
            $this->command->task('Remove sixlive/laravel-preset', function () {
                $this->runCommand('composer remove sixlive/laravel-preset');
                $this->runCommand('composer dumpautoload');
            });
        }

        $this->outputSuccessMessage();
    }

    private function gatherOptions()
    {
        return [
            'packages' => $this->promptForPackagesToInstall(),
            'remove_after_install' => $this->command->confirm('Remove sixlive/laravel-preset after install?', true),
        ];
    }

    private function promptForPackagesToInstall()
    {
        $possiblePackages = $this->packages();

        $choices = $this->command->choice(
            'Which optional packages should be installed? (e.x. 1,2)',
            ['all'] + $possiblePackages,
            '0',
            null,
            true
        );

        return in_array('all', $choices)
            ? $possiblePackages
            : $choices;
    }

    private function updateComposerPackages()
    {
        $this->runCommand(sprintf(
            'composer require %s',
            $this->resolveForComposer($this->options['packages'])
        ));
    }

    private function packages()
    {
        return Collection::make($this->packages)
            ->where('dev', false)
            ->keys()
            ->toArray();
    }

    private function devPackages()
    {
        return Collection::make($this->packages)
            ->where('dev', true)
            ->keys()
            ->toArray();
    }

    private function resolveForComposer($packages)
    {
        return Collection::make($packages)
            ->transform(function ($package) {
                return isset($this->packages[$package]['version'])
                    ? $package . ':' . $this->packages[$package]['version']
                    : $package;
            })
            ->implode(' ');
    }

    private function updateComposerDevPackages()
    {
        $this->runCommand(sprintf(
            'composer require --dev %s',
            $this->resolveForComposer($this->devPackages())
        ));
    }

    private function publishStubs()
    {
        copy(__DIR__ . '/stubs/Model.php', app_path('Model.php'));
        copy(__DIR__ . '/stubs/phpunit.xml', base_path('phpunit.xml'));
        copy(__DIR__ . '/stubs/.php_cs', base_path('.php_cs'));
        copy(__DIR__ . '/stubs/.editorconfig', base_path('.editorconfig'));
        copy(__DIR__ . '/stubs/docker-compose.yml', base_path('docker-compose.yml'));

        if (in_array('silber/bouncer:v1.0.0-rc.4', $this->options['packages'])) {
            copy(__DIR__ . '/stubs/BouncerSeeder.php', database_path('seeds/BouncerSeeder.php'));
        }

        tap(new Filesystem, function ($files) {
            $files->copyDirectory(__DIR__ . '/stubs/.docker', base_path('.docker'));
        });
    }

    private function updateEnvFile()
    {
        tap(new DotenvEditor, function ($editor) {
            $editor->load(base_path('.env'));
            $editor->set('DB_PORT', '3307');
            if (in_array('sentry/sentry-laravel', $this->options['packages'])) {
                $editor->set('SENTRY_DSN', '');
                $editor->addEmptyLine();
            }
            $editor->heading('Docker');
            $editor->set('DOCKER_WEB_PORT', '8080');
            $editor->set('DOCKER_MYSQL_PORT', '3307');
            $editor->save();
        });

        tap(new DotenvEditor, function ($editor) {
            $editor = new DotenvEditor;
            $editor->load(base_path('.env.example'));
            $editor->set('DB_PORT', '3307');
            if (in_array('sentry/sentry-laravel', $this->options['packages'])) {
                $editor->set('SENTRY_DSN', '');
                $editor->addEmptyLine();
            }
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

    private function getInstalledPackages()
    {
        return Collection::make($this->packages)
            ->filter(function ($data, $package) {
                return in_array($package, $this->options['packages'])
                    || ($data['dev'] ?? false);
            })
            ->toArray();
    }

    private function outputSuccessMessage()
    {
        $this->command->line('');
        $this->command->info('Preset installation complete. The packages that were installed may require additional installation steps.');
        $this->command->line('');

        foreach ($this->getInstalledPackages() as $package => $packageData) {
            $this->command->getOutput()->writeln(vsprintf('- %s: <comment>%s</comment>', [
                $package,
                $packageData['repo'],
            ]));
        }
    }
}
