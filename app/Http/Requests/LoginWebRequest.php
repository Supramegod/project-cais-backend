<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

/**
 * Validasi field login web (form welcome).
 *
 * Menggantikan inline `$request->validate([...])` di WebAuthController::login.
 * Hanya memvalidasi keberadaan field — pengecekan kredensial (username/password
 * cocok) tetap ditangani di controller lewat scope User::checkLogin.
 */
class LoginWebRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => FluentRule::string('Username')->required(),
            'password' => FluentRule::string('Password')->required(),
        ];
    }
}
