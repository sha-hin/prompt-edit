<?php

namespace App\Http\Requests\Generations;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class GenerationHistoryRequest extends FormRequest
{
    public const array STATUSES = ['succeeded', 'running', 'failed'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:' . implode(',', self::STATUSES)],
            'search' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * Narrow $query to the chosen status and prompt search.
     *
     * @param  Builder<Generation>  $query
     * @return Builder<Generation>
     */
    public function filter(Builder $query): Builder
    {
        return $query
            ->when($this->validated('status'), fn (Builder $query, string $status) => match ($status) {
                'running' => $query->whereIn('status', GenerationStatus::inFlight()),
                default => $query->where('status', $status),
            })
            ->when($this->validated('search'), fn (Builder $query, string $search) => $query
                ->where('prompt', 'like', '%' . addcslashes($search, '%_\\') . '%'));
    }

    /**
     * @return array{status: string|null, search: string|null}
     */
    public function filters(): array
    {
        return [
            'status' => $this->validated('status'),
            'search' => $this->validated('search'),
        ];
    }
}
