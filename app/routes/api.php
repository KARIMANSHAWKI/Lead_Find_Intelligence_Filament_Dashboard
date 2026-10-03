<?php

use App\Http\Controllers\Api\AgentRunResultController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/agent/runs/{run}/result', AgentRunResultController::class)
    ->whereNumber('run')->middleware('throttle:60,1')->name('agent-runs.result');
