<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\AppController;
use App\Http\Controllers\TableReservationController;
use App\Http\Controllers\FoodReservationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\DashboardController;

// Public routes
Route::get('/', [HomeController::class, 'index'])->name('welcome');
Route::get('/restaurants', [AppController::class, 'index'])->name('restaurants.index.user');
Route::get('/reservation', [TableReservationController::class, 'reservationPage'])->name('reservation.page');
Route::post('/midtrans/callback', [FoodReservationController::class, 'midtransCallback'])->name('midtrans.callback');

// Authenticated user routes (Customer)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/my-reservations', [TableReservationController::class, 'userIndex'])->name('user.reservations.index'); 
    
    // Customer Table Reservation
    Route::post('/reservation', [TableReservationController::class, 'store'])->name('reservation.store');
    Route::get('/reservation/{id}', [TableReservationController::class, 'show'])->name('reservation.show');
    Route::put('/reservation/{id}', [TableReservationController::class, 'update'])->name('reservation.update');
    Route::delete('/reservation/{id}', [TableReservationController::class, 'destroy'])->name('reservation.destroy');
    
    // Customer Food Reservation
    Route::get('/menu-reservation', [FoodReservationController::class, 'index'])->name('food.reservation.page');
    Route::post('/food-reservation', [FoodReservationController::class, 'store'])->name('food.reservation.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';