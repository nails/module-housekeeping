<?php

namespace Nails\Housekeeping\Service;

use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Model\Base as ModelBase;
use Nails\Common\Service\Database;
use Nails\Factory;
use Nails\Housekeeping\Exception\HousekeepingException;
use Nails\Housekeeping\Housekeeping\LogFiles;
use Nails\Housekeeping\Housekeeping\LogFilesArchive;
use Nails\Housekeeping\Log\Threshold;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Routine\Result;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class Deleter
{
    /**
     * Select matching rows, audit them, then delete in batches.
     *
     * @param array<int, mixed> $aWhere
     * @param string[]          $aAuditColumns
     *
     * @throws FactoryException
     * @throws HousekeepingException
     * @throws ModelException
     */
    public function deleteRows(
        Context $oContext,
        ModelBase $oModel,
        array $aWhere,
        array $aAuditColumns = ['id'],
        int $iBatchSize = 200,
        bool $bOptimizeAfter = false,
    ): Result {
        if (empty($aWhere)) {
            throw new HousekeepingException(sprintf(
                'Refusing to delete from %s without a where condition',
                $oModel->getTableName()
            ));
        }

        if ($iBatchSize < 1) {
            throw new HousekeepingException('Batch size must be at least 1');
        }

        $sIdColumn     = $oModel->getColumnId();
        $aAuditColumns = array_values(array_unique(array_merge([$sIdColumn], $aAuditColumns)));
        $iProcessed    = 0;
        $sTable        = $oModel->getTableName();
        $iLastId       = 0;

        $oContext
            ->writeln(sprintf('Deleting from <comment>%s</comment> in batches of %d', $sTable, $iBatchSize))
            ->log(sprintf('TABLE %s batch_size=%d dry_run=%s', $sTable, $iBatchSize, $oContext->isDryRun() ? 'true' : 'false'));

        while (true) {
            if ($oContext->shouldStop()) {
                return $oContext->abort($iProcessed);
            }

            $aRows = $oModel->getAll(1, $iBatchSize, [
                'where'  => array_merge($aWhere, [[$sIdColumn . ' >', $iLastId]]),
                'sort'   => [[$sIdColumn, 'asc']],
                'select' => $aAuditColumns,
            ]);

            if (empty($aRows)) {
                break;
            }

            $aIds = [];
            foreach ($aRows as $oRow) {
                $iId     = (int) ($oRow->{$sIdColumn} ?? 0);
                $iLastId = $iId;
                $aIds[]  = $iId;
                $oContext->log('DELETE ' . $this->formatAudit($oRow, $aAuditColumns));
                $oContext->writeln(' ↳ ' . $this->formatAudit($oRow, $aAuditColumns));
            }

            if (!$oContext->isDryRun()) {
                if (!$oModel->deleteMany($aIds)) {
                    $sError = implode('; ', $oModel->getErrors()) ?: 'deleteMany() returned false';
                    $oContext->log('ERROR ' . $sError);
                    return Result::fail($sError, $iProcessed, count($aIds));
                }
            }

            $iProcessed += count($aIds);
        }

        if ($bOptimizeAfter && !$oContext->isDryRun() && $iProcessed > 0) {
            $oContext->writeln('Optimising table... ');
            $oContext->log('OPTIMIZE ' . $sTable);
            /** @var Database $oDb */
            $oDb = Factory::service('Database');
            $oDb->query(sprintf('OPTIMIZE TABLE `%s`', $sTable));
        }

        $oContext->writeln(sprintf(
            '<comment>%s</comment> %s',
            number_format($iProcessed),
            $oContext->isDryRun() ? 'would be deleted' : 'deleted'
        ));

        return Result::ok($iProcessed);
    }

    /**
     * Delete files matching a glob under a directory that are older than N days.
     *
     * @param string|string[]                              $mPattern
     * @param null|callable(SplFileInfo): Threshold       $cThreshold When set, each file supplies its own day count. A count below 1 skips that file. The single $iOlderThanDays value is used only when this is null, where 0 still means every matching file.
     *
     * @throws FactoryException
     * @throws HousekeepingException
     */
    public function deleteFiles(
        Context $oContext,
        string $sDirectory,
        string|array $mPattern = '*.php',
        int $iOlderThanDays = LogFiles::DEFAULT_RETENTION_DAYS,
        ?callable $cThreshold = null,
    ): Result {
        $sDirectory = $this->normaliseDirectory($sDirectory);
        $sPattern   = $this->patternLabel($mPattern);
        $sOlder     = $this->olderThanLabel($iOlderThanDays, $cThreshold);

        $oContext
            ->writeln(sprintf(
                'Cleaning <comment>%s</comment> (pattern <comment>%s</comment>, older than <comment>%s</comment> days)',
                $sDirectory,
                $sPattern,
                $sOlder
            ))
            ->log(sprintf(
                'DIRECTORY %s pattern=%s older_than_days=%s dry_run=%s',
                $sDirectory,
                $sPattern,
                $sOlder,
                $oContext->isDryRun() ? 'true' : 'false'
            ));

        $oNow       = $this->now();
        $iProcessed = 0;
        $iFailed    = 0;
        $aChannels  = [];

        foreach ($this->files($sDirectory) as $oFile) {
            $oThreshold = $this->matchThreshold($oFile, $mPattern, $iOlderThanDays, $cThreshold, $oNow, $aChannels);
            if ($oThreshold === null) {
                continue;
            }

            $sFileName = $oFile->getFilename();
            $sPath     = $oFile->getRealPath() ?: $oFile->getPathname();
            $oContext->log('UNLINK ' . $sPath);
            $oContext->writeln(' ↳ Removing <comment>' . $sFileName . '</comment>');

            if (!$oContext->isDryRun()) {
                if (!@unlink($sPath)) {
                    $iFailed++;
                    $oContext->log('ERROR failed to unlink ' . $sPath);
                    $oContext->writeln('   <error>failed</error>');
                    continue;
                }
            }

            $iProcessed++;
            $this->markChannelProcessed($aChannels, $oThreshold);
        }

        $this->logChannels($oContext, $cThreshold, $aChannels);

        $oContext->writeln(sprintf(
            '<comment>%s</comment> files %s',
            number_format($iProcessed),
            $oContext->isDryRun() ? 'would be cleaned' : 'were cleaned'
        ));

        if ($iFailed > 0) {
            return Result::fail('Failed to unlink ' . $iFailed . ' file(s)', $iProcessed, $iFailed);
        }

        return Result::ok($iProcessed);
    }

    /**
     * Gzip files matching a glob under a directory that are older than N days.
     *
     * Original mtime is copied onto the archive so a later retention pass still
     * sees the file's real age. Already-compressed files (those ending in the
     * suffix) are skipped.
     *
     * @param string|string[]                        $mPattern
     * @param null|callable(SplFileInfo): Threshold $cThreshold When set, each file supplies its own day count. A count below 1 skips that file. The single $iOlderThanDays value is used only when this is null, where 0 still means every matching file.
     *
     * @throws FactoryException
     * @throws HousekeepingException
     */
    public function archiveFiles(
        Context $oContext,
        string $sDirectory,
        string|array $mPattern = '*.php',
        int $iOlderThanDays = LogFilesArchive::DEFAULT_ARCHIVE_DAYS,
        string $sSuffix = '.gz',
        ?callable $cThreshold = null,
    ): Result {
        if (!function_exists('gzopen')) {
            throw new HousekeepingException('The zlib extension is required to archive files');
        }

        $sDirectory = $this->normaliseDirectory($sDirectory);
        $sSuffix    = $this->normaliseSuffix($sSuffix);
        $sPattern   = $this->patternLabel($mPattern);
        $sOlder     = $this->olderThanLabel($iOlderThanDays, $cThreshold);

        $oContext
            ->writeln(sprintf(
                'Archiving <comment>%s</comment> (pattern <comment>%s</comment>, older than <comment>%s</comment> days, suffix <comment>%s</comment>)',
                $sDirectory,
                $sPattern,
                $sOlder,
                $sSuffix
            ))
            ->log(sprintf(
                'DIRECTORY %s pattern=%s older_than_days=%s suffix=%s dry_run=%s',
                $sDirectory,
                $sPattern,
                $sOlder,
                $sSuffix,
                $oContext->isDryRun() ? 'true' : 'false'
            ));

        $oNow       = $this->now();
        $iProcessed = 0;
        $iFailed    = 0;
        $aChannels  = [];

        foreach ($this->files($sDirectory) as $oFile) {
            $sFileName = $oFile->getFilename();
            if (str_ends_with($sFileName, $sSuffix)) {
                continue;
            }

            $oThreshold = $this->matchThreshold($oFile, $mPattern, $iOlderThanDays, $cThreshold, $oNow, $aChannels);
            if ($oThreshold === null) {
                continue;
            }

            $sPath = $oFile->getRealPath() ?: $oFile->getPathname();
            $sDest = $sPath . $sSuffix;

            if (!$this->archiveOne($oContext, $oFile, $sPath, $sDest)) {
                $iFailed++;
                continue;
            }

            $iProcessed++;
            $this->markChannelProcessed($aChannels, $oThreshold);
        }

        $this->logChannels($oContext, $cThreshold, $aChannels);

        $oContext->writeln(sprintf(
            '<comment>%s</comment> files %s',
            number_format($iProcessed),
            $oContext->isDryRun() ? 'would be archived' : 'were archived'
        ));

        if ($iFailed > 0) {
            return Result::fail('Failed to archive ' . $iFailed . ' file(s)', $iProcessed, $iFailed);
        }

        return Result::ok($iProcessed);
    }

    /**
     * Log the current row count then truncate the table.
     *
     * @throws FactoryException
     * @throws ModelException
     */
    public function truncateTable(Context $oContext, ModelBase $oModel): Result
    {
        $sTable = $oModel->getTableName();
        $iCount = $oModel->countAll();

        $oContext
            ->writeln(sprintf(
                'Truncating <comment>%s</comment> (<comment>%s</comment> rows)',
                $sTable,
                number_format($iCount)
            ))
            ->log(sprintf(
                'TRUNCATE %s rows=%d dry_run=%s',
                $sTable,
                $iCount,
                $oContext->isDryRun() ? 'true' : 'false'
            ));

        if (!$oContext->isDryRun()) {
            $oModel->truncate();
        }

        return Result::ok($iCount);
    }

    /**
     * @throws FactoryException
     */
    protected function now(): \DateTime
    {
        /** @var \DateTime $oNow */
        $oNow = Factory::factory('DateTime');
        return $oNow;
    }

    /**
     * @throws HousekeepingException
     */
    private function normaliseDirectory(string $sDirectory): string
    {
        $sDirectory = rtrim($sDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (!is_dir($sDirectory)) {
            throw new HousekeepingException(sprintf('Directory does not exist: %s', $sDirectory));
        }

        return $sDirectory;
    }

    /**
     * @throws HousekeepingException
     */
    private function normaliseSuffix(string $sSuffix): string
    {
        $sSuffix = ltrim($sSuffix, '.');
        if ($sSuffix === '') {
            throw new HousekeepingException('Archive suffix must not be empty');
        }

        return '.' . $sSuffix;
    }

    /**
     * @param string|string[] $mPattern
     */
    private function patternLabel(string|array $mPattern): string
    {
        return implode(',', (array) $mPattern);
    }

    /**
     * @return \Generator<SplFileInfo>
     */
    private function files(string $sDirectory): \Generator
    {
        $oFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var SplFileInfo $oFile */
        foreach ($oFiles as $oFile) {
            if ($oFile->isFile()) {
                yield $oFile;
            }
        }
    }

    private function olderThanLabel(int $iOlderThanDays, ?callable $cThreshold): string
    {
        return $cThreshold === null ? (string) $iOlderThanDays : 'per-file';
    }

    /**
     * @param string|string[]                              $mPattern
     * @param null|callable(SplFileInfo): Threshold       $cThreshold
     * @param array<string, array{days: int, processed: int}> $aChannels
     */
    private function matchThreshold(
        SplFileInfo $oFile,
        string|array $mPattern,
        int $iOlderThanDays,
        ?callable $cThreshold,
        \DateTimeInterface $oNow,
        array &$aChannels,
    ): ?Threshold {
        if (!$this->filenameMatches($oFile->getFilename(), $mPattern)) {
            return null;
        }

        if ($cThreshold === null) {
            if (!$this->isOlderThan($oFile, $iOlderThanDays, $oNow)) {
                return null;
            }

            return new Threshold('', $iOlderThanDays);
        }

        $oThreshold = $cThreshold($oFile);
        $this->noteChannel($aChannels, $oThreshold);

        if ($oThreshold->days < 1 || !$this->isOlderThan($oFile, $oThreshold->days, $oNow)) {
            return null;
        }

        return $oThreshold;
    }

    private function isOlderThan(SplFileInfo $oFile, int $iOlderThanDays, \DateTimeInterface $oNow): bool
    {
        $oModified = \DateTime::createFromFormat('U', (string) $oFile->getMTime());
        if ($oModified === false) {
            return false;
        }

        if ($iOlderThanDays > 0 && $oNow->diff($oModified, true)->days <= $iOlderThanDays) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, array{days: int, processed: int}> $aChannels
     */
    private function noteChannel(array &$aChannels, Threshold $oThreshold): void
    {
        if (!isset($aChannels[$oThreshold->channel])) {
            $aChannels[$oThreshold->channel] = ['days' => $oThreshold->days, 'processed' => 0];
        }
    }

    /**
     * @param array<string, array{days: int, processed: int}> $aChannels
     */
    private function markChannelProcessed(array &$aChannels, Threshold $oThreshold): void
    {
        if ($oThreshold->channel === '' || !isset($aChannels[$oThreshold->channel])) {
            return;
        }

        $aChannels[$oThreshold->channel] = [
            'days'      => $aChannels[$oThreshold->channel]['days'],
            'processed' => $aChannels[$oThreshold->channel]['processed'] + 1,
        ];
    }

    /**
     * @param null|callable(SplFileInfo): Threshold           $cThreshold
     * @param array<string, array{days: int, processed: int}> $aChannels
     */
    private function logChannels(Context $oContext, ?callable $cThreshold, array $aChannels): void
    {
        if ($cThreshold === null) {
            return;
        }

        ksort($aChannels);
        $sDryRun = $oContext->isDryRun() ? 'true' : 'false';
        foreach ($aChannels as $sChannel => $aStats) {
            $oContext->log(sprintf(
                'CHANNEL %s older_than_days=%d processed=%d dry_run=%s',
                $sChannel,
                $aStats['days'],
                $aStats['processed'],
                $sDryRun
            ));
        }
    }

    /**
     * @param string|string[] $mPattern
     */
    private function filenameMatches(string $sFileName, string|array $mPattern): bool
    {
        foreach ((array) $mPattern as $sPattern) {
            if (fnmatch($sPattern, $sFileName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gzip $sPath to $sDest, preserve mtime, then unlink the original.
     * If $sDest already exists from a previous interrupted run, just unlink the original.
     */
    private function archiveOne(
        Context $oContext,
        SplFileInfo $oFile,
        string $sPath,
        string $sDest,
    ): bool {
        $sFileName      = $oFile->getFilename();
        $bAlreadyExists = is_file($sDest) && (int) filesize($sDest) > 0;

        $oContext->log(sprintf(
            'ARCHIVE %s -> %s%s',
            $sPath,
            $sDest,
            $bAlreadyExists ? ' already_exists=true' : ''
        ));
        $oContext->writeln(' ↳ Compressing <comment>' . $sFileName . '</comment>');

        if ($oContext->isDryRun()) {
            return true;
        }

        if ($bAlreadyExists) {
            if (!@unlink($sPath)) {
                $oContext->log('ERROR failed to unlink ' . $sPath);
                $oContext->writeln('   <error>failed</error>');
                return false;
            }
            return true;
        }

        if (is_file($sDest)) {
            @unlink($sDest);
        }

        $iMTime = $oFile->getMTime();
        $iPerms = $oFile->getPerms();

        if (!$this->gzipFile($sPath, $sDest)) {
            if (is_file($sDest)) {
                @unlink($sDest);
            }
            $oContext->log('ERROR failed to compress ' . $sPath);
            $oContext->writeln('   <error>failed</error>');
            return false;
        }

        if (is_int($iPerms)) {
            @chmod($sDest, $iPerms & 0777);
        }
        @touch($sDest, $iMTime);

        if (!@unlink($sPath)) {
            $oContext->log('ERROR failed to unlink ' . $sPath);
            $oContext->writeln('   <error>failed</error>');
            return false;
        }

        return true;
    }

    private function gzipFile(string $sSource, string $sDest): bool
    {
        $mIn = fopen($sSource, 'rb');
        if ($mIn === false) {
            return false;
        }

        $mOut = gzopen($sDest, 'wb6');
        if ($mOut === false) {
            fclose($mIn);
            return false;
        }

        $iCopied = stream_copy_to_stream($mIn, $mOut);
        fclose($mIn);
        gzclose($mOut);

        return $iCopied !== false;
    }

    /**
     * @param string[] $aColumns
     */
    private function formatAudit(object $oRow, array $aColumns): string
    {
        $aParts = [];
        foreach ($aColumns as $sColumn) {
            $mValue     = $oRow->{$sColumn} ?? null;
            $aParts[]   = $sColumn . '=' . $this->stringify($mValue);
        }

        return implode(' ', $aParts);
    }

    private function stringify(mixed $mValue): string
    {
        if ($mValue === null) {
            return 'null';
        }

        if ($mValue instanceof \DateTimeInterface) {
            return $mValue->format('Y-m-d H:i:s');
        }

        if (is_bool($mValue)) {
            return $mValue ? 'true' : 'false';
        }

        if (is_scalar($mValue)) {
            return str_replace(["\n", "\r"], ' ', (string) $mValue);
        }

        return json_encode($mValue, JSON_UNESCAPED_SLASHES) ?: '';
    }
}
