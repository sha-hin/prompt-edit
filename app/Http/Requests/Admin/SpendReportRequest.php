<?php

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SpendReportRequest extends FormRequest
{
    public const int DEFAULT_DAYS = 30;

    public const int MAX_DAYS = 92;                         // GET /v1/usage refuses longer ranges

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'end' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:start'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && $this->start()->diffInDays($this->end()) >= self::MAX_DAYS) {
                    $validator->errors()->add('start', 'Pick a range of at most ' . self::MAX_DAYS . ' days.');
                }
            },
        ];
    }

    /**
     * The last UTC day in the report, today unless asked otherwise.
     */
    public function end(): CarbonImmutable
    {
        return $this->filled('end')
            ? CarbonImmutable::createFromFormat('!Y-m-d', $this->string('end')->value()) ?: CarbonImmutable::today()
            : CarbonImmutable::today();
    }

    /**
     * The first UTC day in the report, 30 days back from the end unless asked otherwise.
     */
    public function start(): CarbonImmutable
    {
        return $this->filled('start')
            ? CarbonImmutable::createFromFormat('!Y-m-d', $this->string('start')->value()) ?: $this->end()
            : $this->end()->subDays(self::DEFAULT_DAYS - 1);
    }
}
