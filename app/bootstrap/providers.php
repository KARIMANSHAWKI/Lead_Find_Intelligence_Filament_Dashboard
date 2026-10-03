<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AppPanelProvider;
use App\Providers\Filament\LandlordPanelProvider;

return [
    AppServiceProvider::class,
    AppPanelProvider::class,
    LandlordPanelProvider::class,
];
