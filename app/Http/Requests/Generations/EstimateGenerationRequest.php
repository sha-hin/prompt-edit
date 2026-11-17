<?php

namespace App\Http\Requests\Generations;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Generations\GenerationSpec;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EstimateGenerationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['nullable', 'string', 'max:4000'],    // empty while the user is still typing
            'size' => ['nullable', Rule::enum(ImageSize::class)],
            'quality' => ['nullable', Rule::enum(ImageQuality::class)],
            'reference_images' => ['nullable', 'array', 'max:4'],
            'reference_images.*' => ['required', 'string', 'url:http,https'],
        ];
    }

    public function spec(): GenerationSpec
    {
        /** @var list<string> $referenceImages */
        $referenceImages = array_values($this->validated('reference_images') ?? []);

        return new GenerationSpec(
            prompt: $this->string('prompt')->value(),
            size: $this->enum('size', ImageSize::class) ?? ImageSize::Square,
            quality: $this->enum('quality', ImageQuality::class) ?? ImageQuality::Medium,
            referenceImages: $referenceImages,
        );
    }
}
