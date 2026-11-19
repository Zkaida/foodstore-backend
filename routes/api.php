<?php

use App\Http\Controllers\Api\Customer\LoginController;
use App\Http\Controllers\Api\Customer\LogoutController;
use App\Http\Controllers\Api\Customer\MyOrderController;
use App\Http\Controllers\Api\Customer\MyProfileController;
use App\Http\Controllers\Api\Customer\RatingController;
use App\Http\Controllers\Api\Customer\RegisterController;
use App\Http\Controllers\Api\Public\CallbackController;
use App\Http\Controllers\Api\Public\CartController;
use App\Http\Controllers\Api\Public\CategoryController;
use App\Http\Controllers\Api\Public\CheckoutController;
use App\Http\Controllers\Api\Public\ProductController;
use App\Http\Controllers\Api\Public\RajaOngkirController;
use App\Http\Controllers\Api\Public\SliderController;
use Illuminate\Support\Facades\Route;

Route::prefix('customer')->group(function () {
    Route::post('/register', RegisterController::class);
    Route::post('/login', LoginController::class);
    Route::post('/logout', LogoutController::class);

    Route::get('/my-orders', [MyOrderController::class, 'index']);
    Route::get('/my-orders/{snap_token}', [MyOrderController::class, 'show']);

    Route::get('/profile', [MyProfileController::class, 'index']);
    Route::post('/profile', [MyProfileController::class, 'update']);

    Route::post('/ratings', RatingController::class);
});

Route::prefix('public')->group(function () {
    Route::get('/sliders', SliderController::class);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products-populer', [ProductController::class, 'productPopular']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);

    Route::get('/carts', [CartController::class, 'index']);
    Route::post('/carts', [CartController::class, 'store']);
    Route::post('/carts/increment', [CartController::class, 'incrementCart']);
    Route::post('/carts/decrement', [CartController::class, 'decrementCart']);
    Route::delete('/carts/{id}', [CartController::class, 'destroy']);

    Route::get('/search-destination', [RajaOngkirController::class, 'searchDestination']);
    Route::post('/check-ongkir', [RajaOngkirController::class, 'checkOngkir']);

    Route::post('/checkout', [CheckoutController::class, 'store']);
});

Route::post('/callback', CallbackController::class);
