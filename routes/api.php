<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TableReservationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\FoodReservationController;

// Payment Webhooks
Route::post('/payment/create-transaction', [PaymentController::class, 'createTransaction']);
Route::post('/payment/callback', [PaymentController::class, 'callback']);

// Customer Food Reservation API (for cart / async loading)
Route::post('/food-reservation', [FoodReservationController::class, 'store']);
Route::get('/food-reservation/{reservationId}', [FoodReservationController::class, 'getByReservation']);
Route::put('/food-reservation/{foodReservationId}', [FoodReservationController::class, 'update']);
Route::delete('/food-reservation/{foodReservationId}', [FoodReservationController::class, 'delete']);