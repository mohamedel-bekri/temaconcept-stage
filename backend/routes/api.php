<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContentController;
use Illuminate\Support\Facades\Route;

Route::get('/site', [ContentController::class, 'index']);

Route::post('/chat', ChatController::class)->middleware('throttle:20,1');

Route::post('/contact', [ContactController::class, 'submit'])->middleware('throttle:5,1');
