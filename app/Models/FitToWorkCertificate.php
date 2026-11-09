<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FitToWorkCertificate extends Model
{
    use HasFactory;

    /** Same doctors (position + license number) as the medical certificates. */
    public const PHYSICIANS = MedicalCertificate::PHYSICIANS;

    /** Shown in the form and the table. */
    public const FIT_TYPES = [
        'work'  => 'Work',
        'study' => 'Study',
        'ojt'   => 'On-the-job training',
    ];

    protected $fillable = [
        'full_name',
        'age',
        'barangay',
        'fit_type',
        'physician_name',
        'position',
        'license_number',
        'certificate_date',
    ];

    protected $casts = [
        'age'              => 'integer',
        'certificate_date' => 'date',
    ];
}