<?php

use App\Http\Controllers\HomeController;

use Illuminate\Support\Facades\Route;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Users\UserView;
use App\Livewire\Users\UserForm;
use App\Livewire\RolesPermissions\PermissionView;
use App\Livewire\RolesPermissions\RoleView;
use App\Livewire\Companies\CompanyView;
use App\Livewire\Companies\CompanyForm;
use App\Livewire\Companies\BranchView;

Route::get('/', [HomeController::class, 'index'])->name('home');

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
        Route::get('/{company}/branches', BranchView::class)->name('companies.branches');
    });


});
