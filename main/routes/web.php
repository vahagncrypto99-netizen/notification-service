<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Главная страница ведёт на Swagger-документацию API.
 */
Route::redirect('/', '/api/documentation');
