<?php

// Copyright (c) Tribufu. All Rights Reserved.
// SPDX-License-Identifier: MIT

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_openid_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('issuer');
            $table->string('subject');
            $table->timestamps();

            $table->unique(['issuer', 'subject']);
            $table->unique(['issuer', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_openid_identities');
    }
};
