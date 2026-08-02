<?php

declare(strict_types=1);

namespace App\Domains\Report\Dto;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class RequestReportDto extends Data
{
    /**
     * RequestReportDto constructor.
     */
    public function __construct(
        public readonly int $user_id,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
        public readonly Carbon $period_from,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
        public readonly Carbon $period_to,
    ) {
        //
    }

    /**
     * Правила валидации входящих данных.
     *
     * @return array<string, mixed>
     */
    public static function rules() : array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'period_from' => ['required', 'date_format:Y-m-d'],
            'period_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_from'],
        ];
    }
}
