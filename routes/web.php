<?php

use App\Http\Controllers\FitToWorkCertificateController;
use App\Http\Controllers\MedicalCertificateController;
use App\Http\Controllers\PdfRecordController;
use App\Http\Controllers\AicsCertificateController;
use Illuminate\Support\Facades\Route;

// Route::get('/', fn () => redirect()->route('pdf-records.index'));
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
Route::delete('/medical-certificates/{medicalCertificate}', [MedicalCertificateController::class, 'destroy'])->name('medical-certificates.destroy');


// fit to work
Route::get('/fit-to-work', [FitToWorkCertificateController::class, 'index'])->name('fit-to-work.index');
Route::post('/fit-to-work', [FitToWorkCertificateController::class, 'store'])->name('fit-to-work.store');
Route::get('/fit-to-work/{fitToWorkCertificate}', [FitToWorkCertificateController::class, 'show'])->name('fit-to-work.show');
Route::get('/fit-to-work/{fitToWorkCertificate}/download', [FitToWorkCertificateController::class, 'download'])->name('fit-to-work.download');
Route::delete('/fit-to-work/{fitToWorkCertificate}', [FitToWorkCertificateController::class, 'destroy'])->name('fit-to-work.destroy');

//aics
Route::get('/aics', [AicsCertificateController::class, 'index'])->name('aics.index');
Route::post('/aics', [AicsCertificateController::class, 'store'])->name('aics.store');
Route::get('/aics/{aicsCertificate}', [AicsCertificateController::class, 'show'])->name('aics.show');
Route::get('/aics/{aicsCertificate}/download', [AicsCertificateController::class, 'download'])->name('aics.download');
Route::delete('/aics/{aicsCertificate}', [AicsCertificateController::class, 'destroy'])->name('aics.destroy');