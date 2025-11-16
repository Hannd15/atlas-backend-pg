<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        Schema::create('user_project_eligibilities', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_position_id')->constrained()->onDelete('cascade');
            $table->primary(['user_id', 'project_position_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_project_eligibilities');
    }
};
