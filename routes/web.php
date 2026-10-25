<?php

// Copyright (c) Tribufu. All Rights Reserved.
// SPDX-License-Identifier: MIT

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\OpenID\OpenID;

Route::group(['middleware' => ['web', 'guest']], function () {
    Route::get('/openid/login', [OpenID::class, 'redirect'])->name('extensions.others.openid.redirect');
    Route::get('/openid/callback', [OpenID::class, 'callback'])->name('extensions.others.openid.callback');
});
