<?php

namespace Nails\Housekeeping\Log;

class Threshold
{
    public function __construct(
        public readonly string $channel,
        public readonly int $days,
    ) {
    }
}
