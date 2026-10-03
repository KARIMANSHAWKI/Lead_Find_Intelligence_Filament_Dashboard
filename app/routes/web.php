<?php

use App\Filament\Pages\Overview;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(Overview::getUrl(panel: 'app')));
