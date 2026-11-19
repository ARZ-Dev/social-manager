<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Users\UserView;
use App\Livewire\Users\UserForm;
use App\Livewire\RolesPermissions\PermissionView;
use App\Livewire\RolesPermissions\RoleView;
use App\Livewire\Companies\CompanyView;
use App\Livewire\Companies\CompanyForm;
use App\Livewire\Social\SocialMediaView;
use App\Livewire\Social\SocialConnectSelect;
use App\Http\Controllers\SocialConnectController;
use App\Http\Controllers\GoogleAdsConnectController;
use App\Livewire\Social\GoogleAdsConnectSelect;

// The root opens the admin; guests are sent on to the login page by the auth middleware.
Route::redirect('/', '/dashboard')->name('home');

///////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////

Route::get('/login', Login::class)->name('login');

Route::middleware(['auth'])->group(function () { 
    
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/permissions', PermissionView::class)->name('permissions');
    Route::get('/roles', RoleView::class)->name('roles');

    // |--------------------------------------------------------------------------
    // |Users
    // |--------------------------------------------------------------------------
    
    Route::group(['prefix' => 'users'], function () {
        Route::get('/', UserView::class)->name('users');
        Route::get('/create', UserForm::class)->name('users.create');
        Route::get('/edit/{id}', UserForm::class)->name('users.edit');
        Route::get('/view/{id}/{status}', UserForm::class)->name('users.view');
    });

    // |--------------------------------------------------------------------------
    // |Companies & Branches
    // |--------------------------------------------------------------------------

    Route::group(['prefix' => 'companies'], function () {
        Route::get('/', CompanyView::class)->name('companies');
        Route::get('/create', CompanyForm::class)->name('companies.create');
        Route::get('/edit/{id}', CompanyForm::class)->name('companies.edit');
    });

    // |--------------------------------------------------------------------------
    // |Social Media (Facebook, Instagram, Meta ads, Google Ads, BSB)
    // |--------------------------------------------------------------------------

    Route::group(['prefix' => 'social'], function () {
        Route::get('/', SocialMediaView::class)->name('social');
        Route::get('/connect', [SocialConnectController::class, 'redirect'])->name('social.connect');
        Route::get('/callback', [SocialConnectController::class, 'callback'])->name('social.callback');
        Route::get('/connect/select', SocialConnectSelect::class)->name('social.select');

        // Google Ads
        Route::get('/google/connect', [GoogleAdsConnectController::class, 'redirect'])->name('social.google.connect');
        Route::get('/google/callback', [GoogleAdsConnectController::class, 'callback'])->name('social.google.callback');
        Route::get('/google/select', GoogleAdsConnectSelect::class)->name('social.google.select');
    });


});
