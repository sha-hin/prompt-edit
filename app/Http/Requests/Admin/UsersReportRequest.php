<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;

class UsersReportRequest extends SpendReportRequest
{
    public const array SORTS = ['spend', 'joined', 'balance'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'search' => ['nullable', 'string', 'max:200'],
            'sort' => ['nullable', 'in:' . implode(',', self::SORTS)],
        ];
    }

    public function search(): ?string
    {
        return $this->filled('search') ? $this->string('search')->value() : null;
    }

    public function sort(): string
    {
        return $this->filled('sort') ? $this->string('sort')->value() : 'spend';
    }
}
