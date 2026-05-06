<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;
use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class BaseRequest extends LaravelFormRequest
{
    use HasFluentRules;

    protected function failedValidation(Validator $validator)
    {
        $messages = $validator->errors()->toArray();

        throw new HttpResponseException(
            response()->json([
                'message' => $messages
            ], 422)
        );
    }
}