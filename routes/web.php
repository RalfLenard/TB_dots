<?php

use App\Http\Controllers\FitToWorkCertificateController;
use App\Http\Controllers\MedicalCertificateController;
use App\Http\Controllers\PdfRecordController;
use App\Http\Controllers\AicsCertificateController;
use App\Http\Controllers\EmploymentMedicalCertificateController;
use Illuminate\Support\Facades\Route;

 Route::get('/', fn () => redirect()->route('pdf-records.index'));
// csf 
Route::get('/pdf-records', [PdfRecordController::class, 'index'])->name('pdf-records.index');
Route::post('/pdf-records', [PdfRecordController::class, 'store'])->name('pdf-records.store');
Route::get('/pdf-records/{record}/edit', [PdfRecordController::class, 'edit'])->name('pdf-records.edit');
Route::put('/pdf-records/{record}', [PdfRecordController::class, 'update'])->name('pdf-records.update');
Route::get('/pdf-records/{record}/preview', [PdfRecordController::class, 'preview'])->name('pdf-records.preview');


// med cert
Route::get('/medical-certificates', [MedicalCertificateController::class, 'index'])->name('medical-certificates.index');
Route::post('/medical-certificates', [MedicalCertificateController::class, 'store'])->name('medical-certificates.store');
Route::get('/medical-certificates/{medicalCertificate}', [MedicalCertificateController::class, 'show'])->name('medical-certificates.show');
Route::get('/medical-certificates/{medicalCertificate}/download', [MedicalCertificateController::class, 'download'])->name('medical-certificates.download');
Route::put('/medical-certificates/{medicalCertificate}', [MedicalCertificateController::class, 'update'])->name('medical-certificates.update');
Route::delete('/medical-certificates/{medicalCertificate}', [MedicalCertificateController::class, 'destroy'])->name('medical-certificates.destroy');

// fit to work
Route::get('/fit-to-work', [FitToWorkCertificateController::class, 'index'])->name('fit-to-work.index');
Route::post('/fit-to-work', [FitToWorkCertificateController::class, 'store'])->name('fit-to-work.store');
Route::get('/fit-to-work/{fitToWorkCertificate}', [FitToWorkCertificateController::class, 'show'])->name('fit-to-work.show');
Route::get('/fit-to-work/{fitToWorkCertificate}/download', [FitToWorkCertificateController::class, 'download'])->name('fit-to-work.download');
Route::put('/fit-to-work/{fitToWorkCertificate}', [FitToWorkCertificateController::class, 'update'])->name('fit-to-work.update');
Route::delete('/fit-to-work/{fitToWorkCertificate}', [FitToWorkCertificateController::class, 'destroy'])->name('fit-to-work.destroy');

//aics
Route::get('/aics', [AicsCertificateController::class, 'index'])->name('aics.index');
Route::post('/aics', [AicsCertificateController::class, 'store'])->name('aics.store');
Route::get('/aics/{aicsCertificate}', [AicsCertificateController::class, 'show'])->name('aics.show');
Route::get('/aics/{aicsCertificate}/download', [AicsCertificateController::class, 'download'])->name('aics.download');
Route::put('/aics/{aicsCertificate}', [AicsCertificateController::class, 'update'])->name('aics.update');
Route::delete('/aics/{aicsCertificate}', [AicsCertificateController::class, 'destroy'])->name('aics.destroy');

// employment medical certificate (CS Form No. 211)
Route::get('/employment-medical-certificates', [EmploymentMedicalCertificateController::class, 'index'])->name('employment-medical-certificates.index');
Route::post('/employment-medical-certificates', [EmploymentMedicalCertificateController::class, 'store'])->name('employment-medical-certificates.store');
Route::get('/employment-medical-certificates/{employmentMedicalCertificate}', [EmploymentMedicalCertificateController::class, 'show'])->name('employment-medical-certificates.show');
Route::get('/employment-medical-certificates/{employmentMedicalCertificate}/download', [EmploymentMedicalCertificateController::class, 'download'])->name('employment-medical-certificates.download');
Route::put('/employment-medical-certificates/{employmentMedicalCertificate}', [EmploymentMedicalCertificateController::class, 'update'])->name('employment-medical-certificates.update');
Route::delete('/employment-medical-certificates/{employmentMedicalCertificate}', [EmploymentMedicalCertificateController::class, 'destroy'])->name('employment-medical-certificates.destroy');