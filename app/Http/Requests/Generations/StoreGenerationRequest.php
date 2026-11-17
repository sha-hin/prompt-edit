<?php

namespace App\Http\Requests\Generations;

use Illuminate\Contracts\Validation\ValidationRule;

class StoreGenerationRequest extends EstimateGenerationRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'prompt' => ['required', 'string', 'max:4000'],    // Pixelforge requires one to generate
        ];
    }
}
