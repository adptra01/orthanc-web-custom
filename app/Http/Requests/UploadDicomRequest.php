<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDicomRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi level route sudah menggunakan middleware
        // `permission:upload dicom`. Di sini cukup pastikan user login.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $maxKb = (int) config('orthanc.upload.max_size_kb', 512000);

        return [
            'file' => ['required', 'file', 'max:'.$maxKb],
            // Ekstensi tidak wajib (.dcm sering tanpa ekstensi); cek mime
            // longgar karena DICOM sering tidak memiliki mime standar.
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File DICOM wajib diunggah.',
            'file.file' => 'Upload tidak valid.',
            'file.max' => 'Ukuran file melebihi batas yang diizinkan.',
        ];
    }
}
