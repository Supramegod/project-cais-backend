<?php

namespace App\Http\Requests\UserEmailConfig;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class UserEmailConfigSaveRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email_host'         => FluentRule::string('SMTP host')->required(),
            'email_port'         => FluentRule::integer('SMTP port')->required()->min(1)->max(65535),
            'email_username'     => FluentRule::email('Username/email')->required(),
            'email_password'     => FluentRule::string('Password SMTP')->required(),
            'email_encryption'   => FluentRule::string('Enkripsi')->nullable()->in(['tls', 'ssl']),
            'email_from_address' => FluentRule::email('From address')->nullable(),
            'email_from_name'    => FluentRule::string('From name')->nullable()->max(255),
            'is_active'          => FluentRule::boolean('Is active')->nullable(),
        ];
    }

    public function messages(): array
    {
        return [
            'email_host.required'     => 'SMTP host wajib diisi',
            'email_port.required'     => 'SMTP port wajib diisi',
            'email_username.required' => 'Username/email wajib diisi',
            'email_username.email'    => 'Format username/email tidak valid',
            'email_password.required' => 'Password SMTP wajib diisi',
        ];
    }
}
