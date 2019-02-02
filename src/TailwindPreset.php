<?php
namespace sixlive\LaravelPreset;

use Illuminate\Filesystem\Filesystem;
use sixlive\DotenvEditor\DotenvEditor;
use Illuminate\Foundation\Console\Presets\Preset as BasePreset;

class TailwindPreset extends BasePreset {

    public static function install()
    {
        static::ensureComponentDirectoryExists();
        static::updatePackages();
        static::updateStyles();
        static::updateWebpackConfiguration();
        static::updateTemplates();
        static::removeNodeModules();

        $command->info('Tailwind has installed.');
        $command->info('Create Tailwind config file "./node_modules/.bin/tailwind init [filename]" to compile your tailwindcss installation.');
    }


    protected static function updatePackageArray(array $packages)
    {
        return array_merge([
            'laravel-mix-purgecss' => '^2.2.0',
            'postcss-nesting' => '^5.0.0',
            'postcss-import' => '^11.1.0',
            'tailwindcss' => '>=0.6.1',
        ], $packages);
    }

    protected static function updateStyles()
    {
        tap(new Filesystem, function ($files) {
            $files->deleteDirectory(resource_path('sass'));
            $files->delete(public_path('css/app.css'));

            if (! $files->isDirectory($directory = resource_path('css'))) {
                $files->makeDirectory($directory, 0755, true);
            }
        });

        copy(__DIR__.'/stubs/resources/css/app.css', resource_path('css/app.css'));
    }

    protected static function updateTemplates()
    {
        tap(new Filesystem, function ($files) {
            $files->delete(resource_path('views/home.blade.php'));
            $files->delete(resource_path('views/welcome.blade.php'));
            $files->copyDirectory(__DIR__.'/stubs/resources/views', resource_path('views'));
        });
    }

    protected static function updateWebpackConfiguration()
    {
        copy(__DIR__.'/stubs/webpack.mix.js', base_path('webpack.mix.js'));
    }
}
