<?php

namespace Nails\Housekeeping\Console;

class RunAnnouncement
{
    /**
     * A minute with nothing due stays quiet. Forced and named runs always announce.
     */
    public static function visible(bool $bForce, ?string $sRoutine, bool $bHasDueWork): bool
    {
        return $bForce || $sRoutine !== null || $bHasDueWork;
    }
}
