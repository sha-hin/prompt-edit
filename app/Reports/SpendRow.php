<?php

namespace App\Reports;

use App\Money\Money;

/**
 * Finished generations in one group (a day, a user or the whole range) and what they made us.
 */
final readonly class SpendRow
{
    public function __construct(
        public int $succeeded,
        public int $failed,
        public Money $spend,        // what users were charged
        public Money $cost,         // what Pixelforge charged us
    ) {}

    public static function empty(): self
    {
        return new self(0, 0, Money::zero(), Money::zero());
    }

    /**
     * One row of the aggregate query in BuildSpendReport. MySQL returns SUM() as a decimal string.
     */
    public static function fromAggregate(object $row): self
    {
        return new self(
            (int) ($row->succeeded ?? 0),
            (int) ($row->failed ?? 0),
            Money::nanos((int) ($row->spend ?? 0)),
            Money::nanos((int) ($row->cost ?? 0)),
        );
    }

    public function profit(): Money
    {
        return $this->spend->subtract($this->cost);
    }
}
