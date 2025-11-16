<?php

namespace App\Http\Requests\AcademicPeriod;

use App\Models\AcademicPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAcademicPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var AcademicPeriod $academicPeriod */
            $academicPeriod = $this->route('academic_period');

            $startInput = $this->input('start_date');
            $endInput = $this->input('end_date');

            $start = $startInput !== null
                ? CarbonImmutable::parse($startInput)
                : ($academicPeriod->start_date?->toImmutable());
            $end = $endInput !== null
                ? CarbonImmutable::parse($endInput)
                : ($academicPeriod->end_date?->toImmutable());

            if ($start === null || $end === null) {
                return;
            }

            if ($start->gt($end)) {
                $validator->errors()->add('end_date', 'The end date must be on or after the start date.');

                return;
            }

            if ($this->datesOverlap($start, $end, $academicPeriod->id)) {
                $validator->errors()->add('start_date', 'The academic period dates overlap with an existing academic period.');
            }
        });
    }

    private function datesOverlap(CarbonImmutable $start, CarbonImmutable $end, ?int $ignoreId = null): bool
    {
        return AcademicPeriod::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('end_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($inner) use ($start, $end) {
                        $inner->where('start_date', '<=', $start->toDateString())
                            ->where('end_date', '>=', $end->toDateString());
                    });
            })
            ->exists();
    }
}
