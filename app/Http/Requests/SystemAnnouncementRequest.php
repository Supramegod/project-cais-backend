<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class SystemAnnouncementRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category'     => FluentRule::string('Category')->required()
                ->in(['Fitur Baru', 'Bug Fix', 'Aturan', 'Update']),
            'title'        => FluentRule::string('Title')->required()->max(255),
            'version'      => FluentRule::string('Version')->nullable()->max(50),
            'release_date' => FluentRule::date('Release Date')->nullable(),
            'description'  => FluentRule::string('Description')->nullable(),
            'details'      => FluentRule::string('Details')->nullable(),
            'is_active'    => FluentRule::boolean('Is Active')->nullable(),
        ];
    }
}
