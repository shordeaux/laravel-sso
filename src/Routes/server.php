<?php

/**
 * Routes which is neccessary for the SSO server.
 */

use Zefy\LaravelSSO\Middleware\AddTokenToLoginFromBrokers;

Route::middleware('api')->prefix('api/sso')->group(function () {
    Route::post('login', 'Zefy\LaravelSSO\Controllers\ServerController@login');
    Route::post('logout', 'Zefy\LaravelSSO\Controllers\ServerController@logout');
    Route::get('attach', 'Zefy\LaravelSSO\Controllers\ServerController@attach');
    Route::get('userInfo', 'Zefy\LaravelSSO\Controllers\ServerController@userInfo');
});

Route::middleware('web')->prefix('api/sso')->group(function () {
    Route::middleware( AddTokenToLoginFromBrokers::class)->get('brokers/login/{token}',
        'Zefy\LaravelSSO\Controllers\ServerController@loginFromBrokers');


});
