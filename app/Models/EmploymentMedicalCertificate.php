<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmploymentMedicalCertificate extends Model
{
    protected $fillable = [
        'date_examined',
        'last_name', 'first_name', 'name_extension', 'middle_name',
        'address', 'municipality', 'agency_name', 'agency_address',
        'age', 'sex', 'civil_status', 'proposed_position',
        'exams', 'fit_status',
        'physician_name', 'position', 'license_number', 'affiliation',
        'height', 'weight', 'blood_type',
    ];

    protected $casts = [
        'date_examined' => 'date',
        'exams'         => 'array',
        'age'           => 'integer',
    ];

    public const SEXES = ['Male' => 'Male', 'Female' => 'Female'];

    public const CIVIL_STATUSES = [
        'Single'    => 'Single',
        'Married'   => 'Married',
        'Widowed'   => 'Widowed',
        'Separated' => 'Separated',
    ];

    public const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    public const FIT_STATUSES = ['fit' => 'Fit', 'unfit' => 'Unfit'];

    /** Attached pre-employment exam results (key => label shown in the form). */
    public const EXAMS = [
        'blood'         => 'Blood Test',
        'urinalysis'    => 'Urinalysis',
        'chest_xray'    => 'Chest X-Ray',
        'drug_test'     => 'Drug Test',
        'psychological' => 'Psychological Test',
        'neuro'         => 'Neuro-Psychiatric Examination (if applicable)',
    ];

    /** Pre-filled values for a new certificate. */
    public const DEFAULTS = [
        'municipality'      => 'CONCEPCION, TARLAC',
        'agency_name'       => 'MUNICIPALITY OF CONCEPCION',
        'agency_address'    => 'CONCEPCION, TARLAC',
        'proposed_position' => 'JOB ORDER',
        'exams'             => ['blood', 'urinalysis', 'chest_xray', 'drug_test'],
    ];

    /**
     * Physicians: name => position, license, affiliation. Add more here.
     * The name is printed as "NAME, M.D."
     */
    public const PHYSICIANS = [
        'Gladys Lourdes B. Bengco' => [
            'position'    => 'Rural Health Physician',
            'license'     => '122363',
            'affiliation' => 'LGU-CONCEPCION',
        ],
         'Benjamin Q. Bengco' => [
            'position'    => 'Rural Health Physician',
            'license'     => '0085070',
            'affiliation' => 'LGU-CONCEPCION',
        ],
        'Leilani D. Fernandez' => [
            'position'    => 'Rural Health Physician',
            'license'     => '92152',
            'affiliation' => 'LGU-CONCEPCION',
        ],
       
    ];

    /** "LAST, FIRST EXT MIDDLE" (the order printed on the form). */
    public function getFullNameAttribute(): string
    {
        $rest = trim(implode(' ', array_filter([$this->first_name, $this->name_extension, $this->middle_name])));

        return trim($this->last_name . ', ' . $rest, ' ,');
    }
}