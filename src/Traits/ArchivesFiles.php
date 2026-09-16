<?php

namespace Nails\Housekeeping\Traits;

use Nails\Factory;
use Nails\Housekeeping\Constants;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Routine\Result;
use Nails\Housekeeping\Service\Deleter;

trait ArchivesFiles
{
    /**
     * Directory to scan
     */
    abstract protected function directory(): string;

    /**
     * fnmatch() pattern applied to filenames
     *
     * @return string|string[]
     */
    protected function pattern(): string|array
    {
        return '*.php';
    }

    /**
     * Only files older than this many days are compressed. 0 = all matching files.
     */
    protected function olderThanDays(): int
    {
        return 14;
    }

    /**
     * Suffix appended to the original filename (gzip)
     */
    protected function archiveSuffix(): string
    {
        return '.gz';
    }

    public function execute(Context $oContext): Result
    {
        /** @var Deleter $oDeleter */
        $oDeleter = Factory::service('Deleter', Constants::MODULE_SLUG);

        return $oDeleter->archiveFiles(
            $oContext,
            $this->directory(),
            $this->pattern(),
            $this->olderThanDays(),
            $this->archiveSuffix()
        );
    }
}
