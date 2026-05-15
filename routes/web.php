<?php

use App\Http\Controllers\GitHubWebhookController;
use App\Http\Middleware\BasicAuth;
use App\Http\Middleware\VerifyGitHubWebhook;
use App\Livewire\OrgOverview;
use App\Livewire\RepoDetail;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/github', [GitHubWebhookController::class, 'handle'])
    ->middleware(VerifyGitHubWebhook::class);

Route::middleware([BasicAuth::class])->group(function () {
    Route::get('/', OrgOverview::class);
    Route::get('/repos/{repository:name}', RepoDetail::class);
});
