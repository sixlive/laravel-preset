<?php

namespace sixlive\LaravelPreset;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Console\PresetCommand;

class LaravelPresetServiceProvider extends ServiceProvider
{
    public function boot()
    {
        PresetCommand::macro('sixlive', function ($command) {
            Preset::install($command);
            // $command->info('Preset installed successfully.');
            // $command->info('Please run "npm install && npm run dev" to compile your fresh scaffolding.');
        });
    }
}
