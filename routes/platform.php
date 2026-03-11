<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ExportController;
use App\Orchid\Screens\Booking\BookingEditScreen;
use App\Orchid\Screens\Booking\BookingListScreen;
use App\Orchid\Screens\Booking\BookingPendingScreen;
use App\Orchid\Screens\Client\ClientEditScreen;
use App\Orchid\Screens\Client\ClientListScreen;
use App\Orchid\Screens\Client\ClientPendingScreen;
use App\Orchid\Screens\DashboardScreen;
use App\Orchid\Screens\Locker\LockerListScreen;
use App\Orchid\Screens\Role\RoleEditScreen;
use App\Orchid\Screens\Role\RoleListScreen;
use App\Orchid\Screens\SettingsScreen;
use App\Orchid\Screens\Subscription\SubscriptionListScreen;
use App\Orchid\Screens\User\UserEditScreen;
use App\Orchid\Screens\User\UserListScreen;
use App\Orchid\Screens\User\UserProfileScreen;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

/*
|--------------------------------------------------------------------------
| Dashboard Routes
|--------------------------------------------------------------------------
*/

// Dashboard
Route::screen('/dashboard', DashboardScreen::class)
    ->name('platform.dashboard');

Route::screen('/main', DashboardScreen::class)
    ->name('platform.main');

// Clients
Route::screen('/clients/pending', ClientPendingScreen::class)
    ->name('platform.clients.pending');

Route::screen('/clients/{client}/edit', ClientEditScreen::class)
    ->name('platform.clients.edit');

Route::screen('/clients', ClientListScreen::class)
    ->name('platform.clients');

// Bookings
Route::screen('/bookings/pending', BookingPendingScreen::class)
    ->name('platform.bookings.pending');

Route::screen('/bookings/{booking}/edit', BookingEditScreen::class)
    ->name('platform.bookings.edit');

Route::screen('/bookings', BookingListScreen::class)
    ->name('platform.bookings');

// Lockers
Route::screen('/lockers', LockerListScreen::class)
    ->name('platform.lockers');

// Subscriptions
Route::screen('/subscriptions', SubscriptionListScreen::class)
    ->name('platform.subscriptions');

// Settings
Route::screen('/settings', SettingsScreen::class)
    ->name('platform.settings');

// Platform > Profile
Route::screen('profile', UserProfileScreen::class)
    ->name('platform.profile')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Profile'), route('platform.profile')));

// Platform > System > Users > User
Route::screen('users/{user}/edit', UserEditScreen::class)
    ->name('platform.systems.users.edit')
    ->breadcrumbs(fn (Trail $trail, $user) => $trail
        ->parent('platform.systems.users')
        ->push($user->name, route('platform.systems.users.edit', $user)));

// Platform > System > Users > Create
Route::screen('users/create', UserEditScreen::class)
    ->name('platform.systems.users.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.users')
        ->push(__('Create'), route('platform.systems.users.create')));

// Platform > System > Users
Route::screen('users', UserListScreen::class)
    ->name('platform.systems.users')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Users'), route('platform.systems.users')));

// Platform > System > Roles > Role
Route::screen('roles/{role}/edit', RoleEditScreen::class)
    ->name('platform.systems.roles.edit')
    ->breadcrumbs(fn (Trail $trail, $role) => $trail
        ->parent('platform.systems.roles')
        ->push($role->name, route('platform.systems.roles.edit', $role)));

// Platform > System > Roles > Create
Route::screen('roles/create', RoleEditScreen::class)
    ->name('platform.systems.roles.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.roles')
        ->push(__('Create'), route('platform.systems.roles.create')));

// Platform > System > Roles
Route::screen('roles', RoleListScreen::class)
    ->name('platform.systems.roles')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Roles'), route('platform.systems.roles')));

/*
|--------------------------------------------------------------------------
| Export Routes
|--------------------------------------------------------------------------
*/

Route::prefix('export')->name('platform.export.')->group(function () {
    Route::get('/clients', [ExportController::class, 'clients'])->name('clients');
    Route::get('/bookings', [ExportController::class, 'bookings'])->name('bookings');
    Route::get('/subscriptions', [ExportController::class, 'subscriptions'])->name('subscriptions');
    Route::get('/lockers', [ExportController::class, 'lockers'])->name('lockers');
    Route::get('/all', [ExportController::class, 'all'])->name('all');
    Route::get('/report', [ExportController::class, 'report'])->name('report');
});
