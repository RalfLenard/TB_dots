<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicalCertificate extends Model
{
    use HasFactory;

    /**
     * Physicians selectable in the form: name => [position, license number].
     * Add more doctors here when needed.
     */
    public const PHYSICIANS = [
        'Gladys Lourdes B. Bengco' => [
            'position' => 'Rural Health Physician',
            'license'  => '122363',
        ],
        'Benjamin Q. Bengco' => [
            'position' => 'Municipal Health Officer',
            'license'  => '0085070',
        ],
    ];

    public const FIT_TYPES = ['work' => 'Work', 'study' => 'Study'];

    protected $fillable = [
        'full_name',
        'age',
        'barangay',
        'assessment',
        'rest_days',
        'fit_type',
        'fit_date',
        'physician_name',
        'position',
        'license_number',
        'certificate_date',
    ];

    protected $casts = [
        'age'              => 'integer',
        'rest_days'        => 'integer',
        'fit_date'         => 'date',
        'certificate_date' => 'date',
    ];
}