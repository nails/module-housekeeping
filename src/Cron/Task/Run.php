<?php

namespace Nails\Housekeeping\Cron\Task;

use Nails\Cron\Task\Base;

class Run extends Base
{
    const DESCRIPTION     = 'Executes due housekeeping routines';
    const CRON_EXPRESSION = '* * * * *';
    const CONSOLE_COMMAND = 'housekeeping:run';
    const MAX_PROCESSES   = 1;
}
