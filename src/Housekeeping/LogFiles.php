<?php

namespace Nails\Housekeeping\Housekeeping;

use Nails\Common\Service\Logger as CommonLogger;
use Nails\Config;
use Nails\Factory;
use Nails\Housekeeping\Routine\Base;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Routine\Result;
use Nails\Housekeeping\Traits\DeletesFiles;

class LogFiles extends Base
{
    use DeletesFiles {
        execute as purgeFiles;
    }

    const LABEL                  = 'Log files';
    const DESCRIPTION            = 'Deletes log files older than the configured retention period, including compressed archives';
    const CRON_EXPRESSION        = '0 0 * * *';
    const CONFIG_RETENTION_DAYS  = 'LOG_RETENTION';
    const DEFAULT_RETENTION_DAYS = 90;

    protected function directory(): string
    {
        /** @var CommonLogger $oLogger */
        $oLogger = Factory::service('Logger');
        return $oLogger->getDir();
    }

    /**
     * @return string|string[]
     */
    protected function pattern(): string|array
    {
        return ['*.php', '*.php.gz'];
    }

    protected function olderThanDays(): int
    {
        return (int) Config::get(static::CONFIG_RETENTION_DAYS, static::DEFAULT_RETENTION_DAYS);
    }

    public function execute(Context $oContext): Result
    {
        $iDays = $this->olderThanDays();
        if ($iDays < 1) {
            $oContext
                ->writeln('Log retention disabled')
                ->log('DISABLED ' . static::CONFIG_RETENTION_DAYS . '=0');

            return Result::ok(0, 'Log retention disabled');
        }

        return $this->purgeFiles($oContext);
    }
}
