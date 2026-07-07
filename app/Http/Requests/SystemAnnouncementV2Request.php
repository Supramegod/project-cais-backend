<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class SystemAnnouncementV2Request extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category'      => FluentRule::field('Category')->required()->rule('in:Fitur Baru,Bug Fix,Aturan,Update'),
            'title'         => FluentRule::string('Title')->required()->max(255),
            'version'       => FluentRule::string('Version')->nullable()->max(50),
            'release_date'  => FluentRule::date('Release date')->nullable(),
            'description'   => FluentRule::string('Description')->nullable(),
            'details'       => FluentRule::string('Details')->nullable(),
            'is_active'     => FluentRule::field('Is active')->nullable()->rule('in:0,1,true,false'),
            'attachments'   => FluentRule::array()->nullable(),
            'attachments.*' => FluentRule::file()->nullable()->max(10240),
        ];
    }
}
