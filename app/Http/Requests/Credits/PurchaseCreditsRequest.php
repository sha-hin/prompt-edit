<?php

namespace App\Http\Requests\Credits;

use App\Enums\CreditPack;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseCreditsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pack' => ['required', Rule::enum(CreditPack::class)],
            'idempotency_key' => ['required', 'uuid'],          // made by the dialog, so a double click buys once
        ];
    }

    public function pack(): CreditPack
    {
        return $this->enum('pack', CreditPack::class) ?? CreditPack::Five;    // never the fallback: rules require a valid pack
    }
}
