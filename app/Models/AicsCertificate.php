<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AicsCertificate extends Model
{
    use HasFactory;

    /** Same doctors (position + license number) as the medical certificates. */
    public const PHYSICIANS = MedicalCertificate::PHYSICIANS;

    protected $fillable = [
        'full_name',
        'age',
        'barangay',
        'diagnosis',
        'recommendations',
        'physician_name',
        'position',
        'license_number',
        'certificate_date',
    ];

    protected $casts = [
        'age'              => 'integer',
        'recommendations'  => 'array',
        'certificate_date' => 'date',
    ];
}