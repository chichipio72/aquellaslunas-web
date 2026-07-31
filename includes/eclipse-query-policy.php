<?php

const ECLIPSES_MAX_YEARS = 5;

function eclipsesRangeIsAllowed(DateTimeImmutable $startDate, DateTimeImmutable $endDate): bool
{
    return $endDate >= $startDate
        && $endDate <= $startDate->modify('+' . ECLIPSES_MAX_YEARS . ' years');
}
