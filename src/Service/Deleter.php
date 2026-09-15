<?php

namespace Nails\Housekeeping\Service;

use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Model\Base as ModelBase;
use Nails\Common\Service\Database;
use Nails\Factory;
use Nails\Housekeeping\Exception\HousekeepingException;
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
        $iPage         = 1;

        $oContext
            ->writeln(sprintf('Deleting from <comment>%s</comment> in batches of %d', $sTable, $iBatchSize))
            ->log(sprintf('TABLE %s batch_size=%d dry_run=%s', $sTable, $iBatchSize, $oContext->isDryRun() ? 'true' : 'false'));

        while (true) {
            $aRows = $oModel->getAll($iPage, $iBatchSize, [
                'where'  => $aWhere,
                'sort'   => [[$sIdColumn, 'asc']],
                'select' => $aAuditColumns,
            ]);

            if (empty($aRows)) {
                break;
            }

            $aIds = [];
            foreach ($aRows as $oRow) {
                $iId    = (int) ($oRow->{$sIdColumn} ?? 0);
                $aIds[] = $iId;
                $oContext->log('DELETE ' . $this->formatAudit($oRow, $aAuditColumns));
                $oContext->writeln(' ↳ ' . $this->formatAudit($oRow, $aAuditColumns));
            }

            if (!$oContext->isDryRun()) {
                if (!$oModel->deleteMany($aIds)) {
                    $sError = implode('; ', $oModel->getErrors()) ?: 'deleteMany() returned false';
                    $oContext->log('ERROR ' . $sError);
                    return Result::fail($sError, $iProcessed, count($aIds));
                }
                // Next fetch stays on page 1 because the previous rows are gone
            } else {
                $iPage++;
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
     * @throws FactoryException
     * @throws HousekeepingException
     */
    public function deleteFiles(
        Context $oContext,
        string $sDirectory,
        string $sPattern = '*.php',
        int $iOlderThanDays = 180,
    ): Result {
        $sDirectory = rtrim($sDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (!is_dir($sDirectory)) {
            throw new HousekeepingException(sprintf('Directory does not exist: %s', $sDirectory));
        }

        $oContext
            ->writeln(sprintf(
                'Cleaning <comment>%s</comment> (pattern <comment>%s</comment>, older than <comment>%d</comment> days)',
                $sDirectory,
                $sPattern,
                $iOlderThanDays
            ))
            ->log(sprintf(
                'DIRECTORY %s pattern=%s older_than_days=%d dry_run=%s',
                $sDirectory,
                $sPattern,
                $iOlderThanDays,
                $oContext->isDryRun() ? 'true' : 'false'
            ));

        /** @var \DateTime $oNow */
        $oNow       = Factory::factory('DateTime');
        $iProcessed = 0;
        $iFailed    = 0;

        $oFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var SplFileInfo $oFile */
        foreach ($oFiles as $oFile) {
            if (!$oFile->isFile()) {
                continue;
            }

            $sFileName = $oFile->getFilename();
            if (!fnmatch($sPattern, $sFileName)) {
                continue;
            }

            $oModified = \DateTime::createFromFormat('U', (string) $oFile->getMTime());
            if ($oModified === false) {
                continue;
            }

            if ($iOlderThanDays > 0 && $oNow->diff($oModified, true)->days <= $iOlderThanDays) {
                continue;
            }

            $sPath = $oFile->getRealPath() ?: $oFile->getPathname();
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
        }

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
