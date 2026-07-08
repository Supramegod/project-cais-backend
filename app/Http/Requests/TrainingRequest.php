<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class TrainingRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => FluentRule::string('Nama')->required()->max(255),
            'jenis' => FluentRule::string('Jenis')->required()->max(255),
            'jp' => FluentRule::integer('JP')->required()->min(1),
            'menit' => FluentRule::integer('Menit')->required()->min(1),
        ];
    }
}
