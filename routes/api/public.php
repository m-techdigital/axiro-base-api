<?php

use App\Http\Controllers\MarketplaceContentController;
use App\Http\Controllers\MarketplaceContractController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\RuntimeController;
use Illuminate\Support\Facades\Route;

Route::get('runtime', RuntimeController::class);
Route::get('marketplace-contract', MarketplaceContractController::class);
Route::get('marketplace/products', [MarketplaceController::class, 'index']);
Route::get('marketplace/products/{productCode}', [MarketplaceController::class, 'show']);
Route::get('marketplace/products/{product}/reviews', [MarketplaceContentController::class, 'productReviews']);
Route::get('content', [MarketplaceContentController::class, 'index']);
Route::get('content/slug/{slug}', [MarketplaceContentController::class, 'bySlug']);
Route::get('content/{contentEntry}', [MarketplaceContentController::class, 'show']);
