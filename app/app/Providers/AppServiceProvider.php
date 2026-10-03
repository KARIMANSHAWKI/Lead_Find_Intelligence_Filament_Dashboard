<?php

namespace App\Providers;

use App\Console\Commands\MakeOrganizationUser;
use Filament\Commands\MakeUserCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MakeUserCommand::class, MakeOrganizationUser::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
