<?php

namespace Nails\Housekeeping\Housekeeping;

use Nails\Common\Service\Logger as CommonLogger;
use Nails\Config;
use Nails\Factory;
use Nails\Housekeeping\Constants;
use Nails\Housekeeping\Log\Threshold;
use Nails\Housekeeping\Log\Thresholds;
use Nails\Housekeeping\Routine\Base;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Routine\Result;
use Nails\Housekeeping\Service\Deleter;
use Nails\Housekeeping\Traits\ArchivesFiles;
use SplFileInfo;

class LogFilesArchive extends Base
{
    use ArchivesFiles;

    const LABEL                = 'Log file archives';
    const DESCRIPTION          = 'Compresses log files older than the configured archive threshold';
    const CRON_EXPRESSION      = '0 0 * * *';
    const CONFIG_ARCHIVE_DAYS  = 'LOG_ARCHIVE';
    const DEFAULT_ARCHIVE_DAYS = 7;

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

    protected function archiveKey(): string
    {
        return static::CONFIG_ARCHIVE_DAYS;
    }

    protected function olderThanDays(): int
    {
        return (int) Config::get($this->archiveKey(), static::DEFAULT_ARCHIVE_DAYS);
    }

    public function execute(Context $oContext): Result
    {
        return $this->archiveLogs($oContext, $this->deleter());
    }

    protected function deleter(): Deleter
    {
        /** @var Deleter $oDeleter */
        $oDeleter = Factory::service('Deleter', Constants::MODULE_SLUG);

        return $oDeleter;
    }

    protected function archiveLogs(Context $oContext, Deleter $oDeleter): Result
    {
        $oThresholds = new Thresholds();

        return $oDeleter->archiveFiles(
            $oContext,
            $this->directory(),
            $this->pattern(),
            $this->olderThanDays(),
            '.gz',
            fn (SplFileInfo $oFile): Threshold => $oThresholds->forFile(
                $oFile->getFilename(),
                $this->archiveKey(),
                static::DEFAULT_ARCHIVE_DAYS
            )
        );
    }
}
