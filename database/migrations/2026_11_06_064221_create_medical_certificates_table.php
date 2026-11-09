<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_certificates', function (Blueprint $table) {
            $table->id();

            // Patient
            $table->string('full_name', 100);
            $table->unsignedTinyInteger('age');
            $table->string('barangay');

            // Assessment
            $table->string('assessment');
            $table->unsignedSmallInteger('rest_days')->default(1);
            $table->enum('fit_type', ['work', 'study'])->default('work');
            $table->date('fit_date');

            // Doctor (position and license number are set automatically by the controller)
            $table->string('physician_name');
            $table->string('position');
            $table->string('license_number');

            $table->date('certificate_date');
            $table->timestamps();

            $table->index('full_name');
            $table->index('certificate_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_certificates');
    }
};