<?php

namespace App\Http\Requests\Menu;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class MenuAssignRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group_id' => FluentRule::integer('Grup')->required()
                ->exists('sysmenu_group', 'id', message: 'Grup tidak ditemukan.'),
            'menu_ids' => FluentRule::array(label: 'Menu')->required()->min(1)
                ->each(
                    FluentRule::integer()->required()
                        ->exists('sysmenu', 'id', message: 'Salah satu ID menu tidak ditemukan.')
                ),
        ];
    }
}
