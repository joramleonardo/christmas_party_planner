<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ExchangedGiftController;
use App\Http\Controllers\FoodPlanningController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Landing page
Route::get('/', [ExchangedGiftController::class, 'landing'])
    ->name('exchange.landing');

// Group dashboard / menu
Route::get('/{project:slug}/menu', [ExchangedGiftController::class, 'menu'])
    ->name('exchange.menu');

// Exchange Gift Ideas
Route::get('/{project:slug}/exchange-gift-ideas/small-gifts', [ExchangedGiftController::class, 'index'])
    ->name('exchange.ideas.small-gifts');

Route::get('/{project:slug}/exchange-gift-ideas/something', [ExchangedGiftController::class, 'ideas'])
    ->name('exchange.ideas.something');

Route::get('/{project:slug}/exchange-gift-ideas/secret-santa', [ExchangedGiftController::class, 'secretSanta'])
    ->name('exchange.ideas.secret-santa');

// Awards (page)
Route::get('/{project:slug}/awards', [ExchangedGiftController::class, 'awards'])
    ->name('exchange.awards');

// Food Planning (page)
Route::get('/{project:slug}/food-planning', [FoodPlanningController::class, 'show'])
    ->name('exchange.food-planning');

// Wishlist (page)
Route::get('/{project:slug}/wishlist', [ExchangedGiftController::class, 'wishlist'])
    ->name('exchange.wishlist');

// Awards vote submit
Route::post('/exchange/awards/vote', [ExchangedGiftController::class, 'submitAwardsVotes'])
    ->name('exchange.awards.vote');

// Santa code verification
Route::post('/exchange/verify-santa-code', [ExchangedGiftController::class, 'verifySantaCode'])
    ->name('exchange.verify-santa-code');

// Wishlist (by code)
Route::post('/exchange/wishlist/lookup-by-code', [ExchangedGiftController::class, 'wishlistLookupByCode'])
    ->name('exchange.wishlist.lookup-by-code');

Route::post('/exchange/wishlist/lookup', [ExchangedGiftController::class, 'wishlistLookupByCode'])
    ->name('exchange.wishlist.lookup');

Route::post('/exchange/wishlist/update-by-code', [ExchangedGiftController::class, 'wishlistUpdateByCode'])
    ->name('exchange.wishlist.update-by-code');

Route::post('/exchange/wishlist/update', [ExchangedGiftController::class, 'wishlistUpdateByCode'])
    ->name('exchange.wishlist.update');

Route::get('/exchange/awards/status', [ExchangedGiftController::class, 'awardsStatus']);
Route::post('/exchange/awards/verify', [ExchangedGiftController::class, 'verifyAwardsCode']);
Route::get('/exchange/awards/results', [ExchangedGiftController::class, 'awardsResults']);

/*
|--------------------------------------------------------------------------
| Exchange API (prefixed)
|--------------------------------------------------------------------------
*/
Route::prefix('exchange')->group(function () {

    // Shared participants + budget poll data
    Route::get('/data', [ExchangedGiftController::class, 'data'])
        ->name('exchange.data');

    // Projects
    Route::post('/projects', [ExchangedGiftController::class, 'storeProject'])
        ->name('exchange.projects.store');

    Route::post('/join', [ExchangedGiftController::class, 'joinGroup'])
        ->name('exchange.join');

    // Participants
    Route::post('/participants', [ExchangedGiftController::class, 'storeParticipant'])
        ->name('exchange.participants.store');

    Route::delete('/participants/{participant}', [ExchangedGiftController::class, 'deleteParticipant'])
        ->name('exchange.participants.delete');

    // Assignments / generation (existing)
    Route::post('/generate', [ExchangedGiftController::class, 'generateAssignments'])
        ->name('exchange.generate');

    // Ideas
    Route::get('/ideas', [ExchangedGiftController::class, 'ideasData'])
        ->name('exchange.ideas.data');

    Route::post('/ideas/save', [ExchangedGiftController::class, 'saveIdeas'])
        ->name('exchange.ideas.save');

    Route::post('/ideas/send', [ExchangedGiftController::class, 'sendIdeaEmail'])
        ->name('exchange.ideas.send');

    Route::post('/ideas/send-all', [ExchangedGiftController::class, 'sendAllIdeaEmails'])
        ->name('exchange.ideas.send-all');

    // Assignments email
    Route::post('/assignments/email', [ExchangedGiftController::class, 'sendAssignmentsEmail'])
        ->name('exchange.assignments.email');

    // Secret Santa data + generate + email
    Route::get('/secret-santa', [ExchangedGiftController::class, 'secretSantaData'])
        ->name('exchange.secret-santa.data');

    Route::post('/secret-santa/generate', [ExchangedGiftController::class, 'generateSecretSanta'])
        ->name('exchange.secret-santa.generate');

    Route::post('/secret-santa/send', [ExchangedGiftController::class, 'sendSecretSantaEmail'])
        ->name('exchange.secret-santa.send');

    Route::post('/secret-santa/send-all', [ExchangedGiftController::class, 'sendAllSecretSantaEmails'])
        ->name('exchange.secret-santa.send-all');

    // Codenames
    Route::post('/codenames/generate', [ExchangedGiftController::class, 'generateCodenames'])
        ->name('exchange.codenames.generate');

    Route::post('/codenames/set', [ExchangedGiftController::class, 'setMyCodename'])
        ->name('exchange.codenames.set');

    Route::post('/secret-santa/toggle-bought', [ExchangedGiftController::class, 'toggleBoughtStatus'])
        ->name('exchange.secret-santa.toggle-bought');

    /*
    |--------------------------------------------------------------------------
    | ✅ Food Planning API (Option A)
    |--------------------------------------------------------------------------
    */
    Route::get('/food-planning/data', [FoodPlanningController::class, 'data'])
        ->name('exchange.food-planning.data');

    Route::post('/food-planning/items', [FoodPlanningController::class, 'storeItem'])
        ->name('exchange.food-planning.items.store');

    Route::put('/food-planning/items/{item}', [FoodPlanningController::class, 'updateItem'])
        ->name('exchange.food-planning.items.update');

    Route::delete('/food-planning/items/{item}', [FoodPlanningController::class, 'destroyItem'])
        ->name('exchange.food-planning.items.delete');

    Route::post('/food-planning/payers', [FoodPlanningController::class, 'savePayers'])
        ->name('exchange.food-planning.payers.save');

    Route::post('/food-planning/payment-status', [FoodPlanningController::class, 'updatePaymentStatus'])
        ->name('exchange.food-planning.payment-status');

    Route::post('/food-planning/gcash', [FoodPlanningController::class, 'saveGcash'])
        ->name('exchange.food-planning.gcash');
});

require __DIR__ . '/auth.php';
