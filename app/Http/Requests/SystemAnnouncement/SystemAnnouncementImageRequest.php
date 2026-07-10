<?php

namespace App\Http\Requests\SystemAnnouncement;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class SystemAnnouncementImageRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => FluentRule::image('Image')->required()->mimes('jpg', 'jpeg', 'png', 'gif', 'webp')->max(5120),
        ];
    }
}
