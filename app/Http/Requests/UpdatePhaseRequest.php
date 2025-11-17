<?php

namespace App\Http\Requests;

use App\Models\Phase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class UpdatePhaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Phase $phase */
        $phase = $this->route('phase');

        return [
            'name' => 'sometimes|required|string|max:255',
            'start_date' => ['sometimes', 'required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'start_date.required' => 'The start date is required.',
            'start_date.date' => 'The start date must be a valid date.',
            'start_date.date_format' => 'The start date must be in format YYYY-MM-DD.',
            'end_date.required' => 'The end date is required.',
            'end_date.date' => 'The end date must be a valid date.',
            'end_date.date_format' => 'The end date must be in format YYYY-MM-DD.',
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }

    /**
     * Get custom validation attributes.
     */
    public function attributes(): array
    {
        return [
            'start_date' => 'start date',
            'end_date' => 'end date',
        ];
    }

    /**
     * Perform additional validation after standard validation.
     */
    protected function passedValidation(): void
    {
        /** @var Phase $phase */
        $phase = $this->route('phase');

        // Only validate dates if at least one date field is being updated
        if ($this->filled('start_date') || $this->filled('end_date')) {
            $startDate = $this->date('start_date') ?? $phase->start_date;
            $endDate = $this->date('end_date') ?? $phase->end_date;

            $errors = [];

            // Validate dates don't exceed the academic period
            if ($phase->period) {
                if ($startDate < $phase->period->start_date) {
                    $errors['start_date'] = [
                        'The start date cannot be before the academic period start date ('.
                        $phase->period->start_date->format('Y-m-d').').',
                    ];
                }

                if ($endDate > $phase->period->end_date) {
                    $errors['end_date'] = [
                        'The end date cannot be after the academic period end date ('.
                        $phase->period->end_date->format('Y-m-d').').',
                    ];
                }
            }

            // Validate no overlaps with other phases in the same period
            $overlappingPhases = Phase::query()
                ->where('period_id', $phase->period_id)
                ->where('id', '!=', $phase->id)
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function ($q) use ($startDate, $endDate) {
                            $q->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                        });
                })
                ->exists();

            if ($overlappingPhases) {
                $errors['start_date'] = [
                    'The phase dates overlap with another phase in the same academic period.',
                ];
            }

            if (! empty($errors)) {
                throw ValidationException::withMessages($errors);
            }
        }
    }
}
