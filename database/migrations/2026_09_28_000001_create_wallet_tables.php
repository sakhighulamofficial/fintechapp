<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('wallets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('balance_paisa')->default(0);
            $t->timestamps();
        });
        Schema::create('transfers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sender_id')->constrained('users');
            $t->foreignId('recipient_id')->constrained('users');
            $t->unsignedBigInteger('amount_paisa');
            $t->uuid('request_id')->unique();
            $t->string('status')->default('completed');
            $t->timestamps();
            $t->index(['sender_id', 'created_at']);
            $t->index(['recipient_id', 'created_at']);
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('event');
            $t->string('ip_address', 45)->nullable();
            $t->json('details')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('wallets');
    }
};
