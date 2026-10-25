<?php

// Copyright (c) Tribufu. All Rights Reserved.
// SPDX-License-Identifier: MIT

namespace Paymenter\Extensions\Others\OpenID\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The user of a provider, by its issuer and subject, bound to a Paymenter account.
 */
class Identity extends Model
{
    protected $table = 'ext_openid_identities';

    protected $fillable = ['user_id', 'issuer', 'subject'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
