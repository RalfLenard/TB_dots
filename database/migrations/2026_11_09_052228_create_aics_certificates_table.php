<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aics_certificates', function (Blueprint $table) {
            $table->id();

            // Patient
            $table->string('full_name', 100);
            $table->unsignedTinyInteger('age');
            $table->string('barangay');

            // Changes every time
            $table->string('diagnosis', 255);
            $table->json('recommendations')->nullable();   // list of lines

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
        Schema::dropIfExists('aics_certificates');
    }
};