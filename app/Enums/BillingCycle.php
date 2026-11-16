<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum BillingCycle: string
{
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Weekly',
            self::Biweekly => 'Every two weeks',
            self::Monthly => 'Calendar month',
        };
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable}|null */
    public function periodContaining(CarbonImmutable $day, ?CarbonImmutable $startsOn): ?array
    {
        $timezone = $day->getTimezone();
        $day = CarbonImmutable::parse($day->toDateString(), $timezone);

        if ($this === self::Monthly) {
            return [$day->startOfMonth(), $day->endOfMonth()->startOfDay()];
        }

        if ($startsOn === null) {
            return null;
        }

        $startsOn = CarbonImmutable::parse($startsOn->toDateString(), $timezone);

        if ($day->lt($startsOn)) {
            return null;
        }

        $length = $this === self::Weekly ? 7 : 14;
        $start = $startsOn->addDays(intdiv((int) round($startsOn->diffInDays($day)), $length) * $length);

        return [$start, $start->addDays($length - 1)];
    }
}
