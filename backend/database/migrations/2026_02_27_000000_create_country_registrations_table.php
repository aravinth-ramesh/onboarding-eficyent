<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_registrations', function (Blueprint $table) {
            $table->id();
            // ISO 3166-1 alpha-2 code, or '*' for the generic default applied
            // to any country without its own specific fields.
            $table->string('country_code', 2)->index();
            $table->string('field_key');
            $table->string('label');
            $table->boolean('required')->default(false);
            // 'both' | 'fi' | 'corporate'
            $table->string('applies_to', 16)->default('both');
            $table->string('pattern')->nullable();
            $table->string('pattern_message')->nullable();
            $table->string('placeholder')->nullable();
            $table->text('help')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['country_code', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_registrations');
    }
};
