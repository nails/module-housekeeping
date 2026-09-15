<?php

namespace Nails\Housekeeping;

use Nails\Common\Events\Base;

class Events extends Base
{
    /**
     * Fired when the housekeeping runner starts
     */
    const HOUSEKEEPING_START = 'HOUSEKEEPING:START';

    /**
     * Fired after routines have been discovered
     *
     * @param \Nails\Housekeeping\Interfaces\Routine[] $aRoutines The discovered routines
     */
    const HOUSEKEEPING_READY = 'HOUSEKEEPING:READY';

    /**
     * Fired before each routine
     *
     * @param \Nails\Housekeeping\Interfaces\Routine $oRoutine The routine about to be executed
     */
    const HOUSEKEEPING_ROUTINE_BEFORE = 'HOUSEKEEPING:ROUTINE:BEFORE';

    /**
     * Fired when a routine errors
     *
     * @param \Nails\Housekeeping\Interfaces\Routine $oRoutine   The routine which errored
     * @param \Exception                             $oException The exception which was caught
     */
    const HOUSEKEEPING_ROUTINE_ERROR = 'HOUSEKEEPING:ROUTINE:ERROR';

    /**
     * Fired after each routine
     *
     * @param \Nails\Housekeeping\Interfaces\Routine $oRoutine The routine which was just executed
     * @param \Nails\Housekeeping\Routine\Result     $oResult  The result of the routine
     */
    const HOUSEKEEPING_ROUTINE_AFTER = 'HOUSEKEEPING:ROUTINE:AFTER';

    /**
     * Fired when the housekeeping runner finishes
     */
    const HOUSEKEEPING_FINISH = 'HOUSEKEEPING:FINISH';
}
