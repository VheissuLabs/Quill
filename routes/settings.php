<?php

use App\Http\Controllers\Organizations\CurrentOrganizationController;
use App\Http\Controllers\Organizations\LeaveOrganizationController;
use App\Http\Controllers\Organizations\OrganizationController;
use App\Http\Controllers\Settings\EmailAddressController;
use App\Http\Controllers\Settings\EmailAddressVerificationNotificationController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\VerifyEmailAddressController;
use App\Http\Middleware\EnsureOrganizationMembership;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::post('settings/email-addresses', [EmailAddressController::class, 'store'])->name('email-addresses.store');
    Route::delete('settings/email-addresses/{emailAddress}', [EmailAddressController::class, 'destroy'])->can('manage', 'emailAddress')->name('email-addresses.destroy');
    Route::post('settings/email-addresses/{emailAddress}/verification-notification', [EmailAddressVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->can('manage', 'emailAddress')
        ->name('email-addresses.verification.send');
    Route::get('settings/email-addresses/{emailAddress}/verify', VerifyEmailAddressController::class)
        ->middleware('signed')
        ->can('manage', 'emailAddress')
        ->name('email-addresses.verify');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('settings/organizations', [OrganizationController::class, 'store'])->name('organizations.store');

    Route::middleware(EnsureOrganizationMembership::class)->group(function () {
        Route::post('settings/organizations/{organization}/switch', [CurrentOrganizationController::class, 'store'])->name('organizations.switch');
        Route::delete('settings/organizations/{organization}/leave', LeaveOrganizationController::class)->can('leave', 'organization')->name('organizations.leave');
    });
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
