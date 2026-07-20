<?php

namespace App\Http\Requests\Pks;

use App\Http\Requests\BaseRequest;
use App\Models\PksItemFulfillment;
use Illuminate\Contracts\Validation\Validator;
use SanderMuller\FluentValidation\FluentRule;

class ItemFulfillmentEditRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_qty' => FluentRule::integer()->required()->min(0),
            'catatan' => FluentRule::string()->required()->min(10),
        ];
    }

    public function messages(): array
    {
        return [
            'new_qty.required' => 'Qty baru wajib diisi.',
            'new_qty.min'      => 'Qty baru minimal 0.',
            'catatan.required' => 'Catatan wajib diisi.',
            'catatan.min'      => 'Catatan minimal 10 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'new_qty' => 'Qty Baru',
            'catatan' => 'Catatan',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $fulfillment = $this->route('fulfillment');

            if (!$fulfillment instanceof PksItemFulfillment) {
                return;
            }

            $newQty = (int) $this->new_qty;

            if ($newQty > $fulfillment->qty_diminta) {
                $validator->errors()->add(
                    'new_qty',
                    "Qty tidak boleh melebihi qty_diminta ({$fulfillment->qty_diminta})."
                );
            }
        });
    }
}
