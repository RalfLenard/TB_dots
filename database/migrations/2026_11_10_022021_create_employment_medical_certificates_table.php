<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_medical_certificates', function (Blueprint $table) {
            $table->id();

            $table->date('date_examined')->index();

            // For the proposed appointee
            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->string('name_extension', 10)->nullable();
            $table->string('middle_name', 100)->nullable();
            $table->string('address', 150);                       // barangay / street (left cell)
            $table->string('municipality', 100);                  // "CONCEPCION, TARLAC" (right cell)
            $table->string('agency_name', 150);                   // "MUNICIPALITY OF CONCEPCION"
            $table->string('agency_address', 150);                // "CONCEPCION, TARLAC"
            $table->unsignedTinyInteger('age');
            $table->string('sex', 10);
            $table->string('civil_status', 20);
            $table->string('proposed_position', 100);

            // Pre-employment exam results that are attached
            $table->json('exams')->nullable();

            // For the licensed government physician
            $table->string('fit_status', 10)->default('fit');     // fit | unfit
            $table->string('physician_name', 100);
            $table->string('position', 100);                      // official designation
            $table->string('license_number', 50);
            $table->string('affiliation', 150);                   // agency / affiliation

            // Other information about the proposed appointee
            $table->decimal('height', 4, 2);                      // meters
            $table->decimal('weight', 5, 2);                      // kg
            $table->string('blood_type', 5)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_medical_certificates');
    }
};