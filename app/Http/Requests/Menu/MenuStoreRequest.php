<?php

namespace App\Http\Requests\Menu;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class MenuStoreRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama'      => FluentRule::string('Nama')->required()->max(100),
            'parent_id' => FluentRule::field('Parent')->nullable()->exists('sysmenu', 'id'),
            'url'       => FluentRule::string('URL')->required()->max(255),
            'icon'      => FluentRule::string('Icon')->nullable()->max(100),
        ];
    }
}
