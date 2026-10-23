<?php

use App\Models\McpSession;
use App\Models\OAuthAccessToken;
use App\Models\OAuthAuthCode;
use App\Models\OAuthRefreshToken;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('model:prune', ['--model' => [
    OAuthAuthCode::class,
    OAuthAccessToken::class,
    OAuthRefreshToken::class,
    McpSession::class,
]])
    ->dailyAt('03:45')
    ->withoutOverlapping()
    ->name('prune-oauth');
