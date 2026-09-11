<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();           // custom, e.g. 'starter-2026'
            $table->string('name');
            $table->decimal('price_monthly', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('limits')->nullable();          // {max_features,max_members,max_roles,max_permissions,max_storage_mb,allowed_permissions[]}
            $table->json('features')->nullable();        // ["kanban","audit",...]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
