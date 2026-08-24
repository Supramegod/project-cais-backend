<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;

/**
 * Memastikan `user_id` pada request memang anggota role di route `{id}`.
 *
 * Tanpa ini, override per-user bisa ditulis atau dibaca untuk pasangan
 * role/user yang tidak nyambung: barisnya tersimpan tapi tidak pernah terpakai,
 * dan pembacaannya mengembalikan seluruh override bernilai false — tidak bisa
 * dibedakan dari "user ini memang belum punya override".
 *
 * @mixin \Illuminate\Foundation\Http\FormRequest
 */
trait ValidatesRoleUser
{
    protected function validateUserBelongsToRole(Validator $validator): void
    {
        $userId = $this->input('user_id');

        if ($userId === null || $validator->errors()->has('user_id')) {
            return;
        }

        $user = User::query()->find($userId);

        if (! $user) {
            return;
        }

        if ((int) $user->cais_role_id !== (int) $this->route('id')) {
            $validator->errors()->add('user_id', 'User tidak berada pada role ini.');
        }
    }
}
