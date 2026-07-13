<?php

namespace App\Http\Requests\Consultation;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

/**
 * Validasi pembuatan jadwal konsultasi.
 *
 * Endpoint POST /admin-panel/consultations bersifat PUBLIC (tanpa auth),
 * karena itu authorize() selalu mengembalikan true dan tidak boleh
 * bergantung pada user yang terautentikasi.
 */
class StoreConsultationRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_lengkap'      => FluentRule::string()->required()->max(255),
            'perusahaan'        => FluentRule::string()->required()->max(255),
            'aplikasi'          => FluentRule::string()->required()->max(255),
            'no_whatsapp'       => FluentRule::string()->required()->max(20),
            'alamat_email'      => FluentRule::email()->required()->max(255),
            'jadwal_konsultasi' => FluentRule::string()->required()->dateFormat('Y-m-d'),
        ];
    }
}
