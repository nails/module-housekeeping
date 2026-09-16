<?php

namespace Nails\Housekeeping\Housekeeping;

use Nails\Common\Service\Logger as CommonLogger;
use Nails\Config;
use Nails\Factory;
use Nails\Housekeeping\Routine\Base;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Routine\Result;
use Nails\Housekeeping\Traits\ArchivesFiles;

class LogFilesArchive extends Base
{
    use ArchivesFiles {
        execute as archiveFiles;
    }

    const LABEL                = 'Log file archives';
    const DESCRIPTION          = 'Compresses log files older than the configured archive threshold';
    const CRON_EXPRESSION      = '0 0 * * *';
    const CONFIG_ARCHIVE_DAYS  = 'LOG_ARCHIVE';
    const DEFAULT_ARCHIVE_DAYS = 14;

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
        return '*.php';
    }

    protected function olderThanDays(): int
    {
        return (int) Config::get(static::CONFIG_ARCHIVE_DAYS, static::DEFAULT_ARCHIVE_DAYS);
    }

    public function execute(Context $oContext): Result
    {
        $iDays = $this->olderThanDays();
        if ($iDays < 1) {
            $oContext
                ->writeln('Log archive disabled')
                ->log('DISABLED ' . static::CONFIG_ARCHIVE_DAYS . '=0');

            return Result::ok(0, 'Log archive disabled');
        }

        return $this->archiveFiles($oContext);
    }
}
