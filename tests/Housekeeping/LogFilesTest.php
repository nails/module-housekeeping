<?php

namespace Tests\Housekeeping\Housekeeping;

use Nails\Config;
use Nails\Housekeeping\Housekeeping\LogFiles;
use Nails\Housekeeping\Housekeeping\LogFilesArchive;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Routine\Result;
use Nails\Housekeeping\Service\Deleter;
use Nails\Housekeeping\Service\Logger;
use PHPUnit\Framework\TestCase;

class LogFilesHarness extends LogFiles
{
    const CONFIG_RETENTION_DAYS = 'LOG_RETENTION_TEST_HARNESS';

    /**
     * @return string|string[]
     */
    public function exposedPattern(): string|array
    {
        return $this->pattern();
    }

    public function exposedOlderThanDays(): int
    {
        return $this->olderThanDays();
    }

    protected function directory(): string
    {
        return sys_get_temp_dir();
    }
}

class LogFilesArchiveHarness extends LogFilesArchive
{
    const CONFIG_ARCHIVE_DAYS = 'LOG_ARCHIVE_TEST_HARNESS';

    public function exposedOlderThanDays(): int
    {
        return $this->olderThanDays();
    }

    protected function directory(): string
    {
        return sys_get_temp_dir();
    }
}

class LogFilesDirHarness extends LogFiles
{
    public function __construct(
        private readonly string $sDirectory,
        private readonly Deleter $oDeleter,
        private readonly string $sConfigKey,
    ) {
    }

    protected function directory(): string
    {
        return $this->sDirectory;
    }

    protected function deleter(): Deleter
    {
        return $this->oDeleter;
    }

    protected function retentionKey(): string
    {
        return $this->sConfigKey;
    }
}

class LogFilesArchiveDirHarness extends LogFilesArchive
{
    public function __construct(
        private readonly string $sDirectory,
        private readonly Deleter $oDeleter,
        private readonly string $sConfigKey,
    ) {
    }

    protected function directory(): string
    {
        return $this->sDirectory;
    }

    protected function deleter(): Deleter
    {
        return $this->oDeleter;
    }

    protected function archiveKey(): string
    {
        return $this->sConfigKey;
    }
}

class FixedClockDeleter extends Deleter
{
    public function __construct(private readonly \DateTime $oNow)
    {
    }

    protected function now(): \DateTime
    {
        return clone $this->oNow;
    }
}

/**
 * @covers \Nails\Housekeeping\Housekeeping\LogFiles
 * @covers \Nails\Housekeeping\Housekeeping\LogFilesArchive
 */
class LogFilesTest extends TestCase
{
    /** @var string[] */
    private array $aDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->aDirs as $sDir) {
            $this->removeDir($sDir);
        }

        parent::tearDown();
    }

    public function test_log_files_matches_php_and_gzip_and_defaults_to_90_days(): void
    {
        $oRoutine = new LogFilesHarness();

        self::assertSame(['*.php', '*.php.gz'], $oRoutine->exposedPattern());
        self::assertSame(90, $oRoutine->exposedOlderThanDays());
        self::assertSame('0 0 * * *', $oRoutine->getCronExpression());
    }

    public function test_log_files_reads_config(): void
    {
        Config::set(LogFilesHarness::CONFIG_RETENTION_DAYS, 30);

        self::assertSame(30, (new LogFilesHarness())->exposedOlderThanDays());
    }

    public function test_log_files_archive_defaults_to_7_days(): void
    {
        $oRoutine = new LogFilesArchiveHarness();

        self::assertSame(7, $oRoutine->exposedOlderThanDays());
        self::assertSame('0 0 * * *', $oRoutine->getCronExpression());
        self::assertSame(LogFiles::CRON_EXPRESSION, $oRoutine->getCronExpression());
    }

    public function test_log_files_archive_reads_config(): void
    {
        Config::set(LogFilesArchiveHarness::CONFIG_ARCHIVE_DAYS, 30);

        self::assertSame(30, (new LogFilesArchiveHarness())->exposedOlderThanDays());
    }

    public function test_log_retention_zero_deletes_nothing(): void
    {
        $sKey  = 'LOG_RETENTION_TEST_ZERO';
        $sDir  = $this->makeDir();
        $sFile = $this->write($sDir, 'log-old.php', 400);
        Config::set($sKey, 0);

        $aLogs   = [];
        $oResult = (new LogFilesDirHarness($sDir, $this->deleter(), $sKey))
            ->execute($this->context($aLogs, LogFilesDirHarness::class));

        self::assertTrue($oResult->isSuccess());
        self::assertSame(0, $oResult->getProcessed());
        self::assertFileExists($sFile);
        self::assertContains('CHANNEL * older_than_days=0 processed=0 dry_run=false', $aLogs);
    }

    public function test_channel_zero_disables_only_that_channel(): void
    {
        $sKey = 'LOG_RETENTION_TEST_CHANNEL_ONLY';
        Config::set($sKey, 90);
        Config::set($sKey . '_CRON', 0);

        $sDir  = $this->makeDir();
        $sCron = $this->write($sDir, 'cron-2020-01-01.php', 400);
        $sApi  = $this->write($sDir, 'api-2020-01-01.php', 400);

        $aLogs   = [];
        $oResult = (new LogFilesDirHarness($sDir, $this->deleter(), $sKey))
            ->execute($this->context($aLogs, LogFilesDirHarness::class));

        self::assertTrue($oResult->isSuccess());
        self::assertSame(1, $oResult->getProcessed());
        self::assertFileExists($sCron);
        self::assertFileDoesNotExist($sApi);
        self::assertContains('CHANNEL cron older_than_days=0 processed=0 dry_run=false', $aLogs);
        self::assertContains('CHANNEL api older_than_days=90 processed=1 dry_run=false', $aLogs);
    }

    public function test_channel_override_still_deletes_when_catch_all_is_disabled(): void
    {
        $sKey = 'LOG_RETENTION_TEST_CATCHALL_OFF';
        Config::set($sKey, 0);
        Config::set($sKey . '_CRON', 30);

        $sDir   = $this->makeDir();
        $sCron  = $this->write($sDir, 'cron-2020-01-01.php', 400);
        $sApi   = $this->write($sDir, 'api-2020-01-01.php', 400);
        $sNotes = $this->write($sDir, 'notes.php', 400);

        $aLogs   = [];
        $oResult = (new LogFilesDirHarness($sDir, $this->deleter(), $sKey))
            ->execute($this->context($aLogs, LogFilesDirHarness::class));

        self::assertTrue($oResult->isSuccess());
        self::assertSame(1, $oResult->getProcessed());
        self::assertFileDoesNotExist($sCron);
        self::assertFileExists($sApi);
        self::assertFileExists($sNotes);
        self::assertContains('CHANNEL cron older_than_days=30 processed=1 dry_run=false', $aLogs);
        self::assertContains('CHANNEL api older_than_days=0 processed=0 dry_run=false', $aLogs);
        self::assertContains('CHANNEL * older_than_days=0 processed=0 dry_run=false', $aLogs);
    }

    public function test_hyphenated_channel_and_gzip_use_the_channel_key(): void
    {
        $sKey = 'LOG_RETENTION_TEST_HYPHEN';
        Config::set($sKey, 9999);
        Config::set($sKey . '_CDN_ISSUES', 30);
        Config::set($sKey . '_CRON', 30);

        $sDir    = $this->makeDir();
        $sIssues = $this->write($sDir, 'cdn-issues-2025-10-16-10-42-07.php', 400);
        $sGzip   = $this->write($sDir, 'cron-2020-01-01.php.gz', 400);
        $sPlain  = $this->write($sDir, 'cron-2020-01-02.php', 400);
        $sApi    = $this->write($sDir, 'api-2020-01-01.php.gz', 400);

        $aLogs   = [];
        $oResult = (new LogFilesDirHarness($sDir, $this->deleter(), $sKey))
            ->execute($this->context($aLogs, LogFilesDirHarness::class));

        self::assertTrue($oResult->isSuccess());
        self::assertSame(3, $oResult->getProcessed());
        self::assertFileDoesNotExist($sIssues);
        self::assertFileDoesNotExist($sGzip);
        self::assertFileDoesNotExist($sPlain);
        self::assertFileExists($sApi);
        self::assertContains('CHANNEL cdn-issues older_than_days=30 processed=1 dry_run=false', $aLogs);
        self::assertContains('CHANNEL cron older_than_days=30 processed=2 dry_run=false', $aLogs);
        self::assertContains('CHANNEL api older_than_days=9999 processed=0 dry_run=false', $aLogs);
    }

    public function test_dry_run_reports_channel_decisions_without_deleting(): void
    {
        $sKey = 'LOG_RETENTION_TEST_DRY';
        Config::set($sKey, 90);
        Config::set($sKey . '_CRON', 30);

        $sDir  = $this->makeDir();
        $sCron = $this->write($sDir, 'cron-2020-01-01.php', 400);

        $aLogs   = [];
        $oResult = (new LogFilesDirHarness($sDir, $this->deleter(), $sKey))
            ->execute($this->context($aLogs, LogFilesDirHarness::class, true));

        self::assertTrue($oResult->isSuccess());
        self::assertSame(1, $oResult->getProcessed());
        self::assertFileExists($sCron);
        self::assertContains('CHANNEL cron older_than_days=30 processed=1 dry_run=true', $aLogs);
    }

    public function test_log_files_archive_zero_disables_without_touching_files(): void
    {
        $sKey  = 'LOG_ARCHIVE_TEST_ZERO';
        $sDir  = $this->makeDir();
        $sFile = $this->write($sDir, 'log-old.php', 40);
        Config::set($sKey, 0);

        $aLogs   = [];
        $oResult = (new LogFilesArchiveDirHarness($sDir, $this->deleter(), $sKey))
            ->execute($this->context($aLogs, LogFilesArchiveDirHarness::class));

        self::assertInstanceOf(Result::class, $oResult);
        self::assertTrue($oResult->isSuccess());
        self::assertSame(0, $oResult->getProcessed());
        self::assertFileExists($sFile);
        self::assertFileDoesNotExist($sFile . '.gz');
        self::assertContains('CHANNEL * older_than_days=0 processed=0 dry_run=false', $aLogs);
    }

    public function test_purge_runs_before_archive_by_class_name(): void
    {
        self::assertTrue(strcmp(LogFiles::class, LogFilesArchive::class) < 0);
    }

    /**
     * @param string[] $aLogs
     */
    private function context(array &$aLogs, string $sRoutineClass, bool $bDryRun = false): Context
    {
        $oLogger = $this->createStub(Logger::class);
        $oLogger->method('routine')->willReturnCallback(
            function (string $sClass, string $sMessage) use (&$aLogs, $oLogger): Logger {
                $aLogs[] = $sMessage;
                return $oLogger;
            }
        );

        return new Context($bDryRun, $oLogger, $sRoutineClass);
    }

    private function deleter(): FixedClockDeleter
    {
        return new FixedClockDeleter(new \DateTime('2026-09-16 12:00:00'));
    }

    private function write(string $sDir, string $sName, int $iDaysAgo): string
    {
        $sPath = $sDir . DIRECTORY_SEPARATOR . $sName;
        file_put_contents($sPath, "log\n");
        touch($sPath, (new \DateTime('2026-09-16 12:00:00'))->getTimestamp() - ($iDaysAgo * 86400));

        return $sPath;
    }

    private function makeDir(): string
    {
        $sDir = sys_get_temp_dir() . '/nails-housekeeping-logs-' . uniqid('', true);
        mkdir($sDir);
        $this->aDirs[] = $sDir;

        return $sDir;
    }

    private function removeDir(string $sDir): void
    {
        if (!is_dir($sDir)) {
            return;
        }

        $aItems = scandir($sDir);
        if ($aItems === false) {
            return;
        }

        foreach ($aItems as $sItem) {
            if ($sItem === '.' || $sItem === '..') {
                continue;
            }

            $sPath = $sDir . DIRECTORY_SEPARATOR . $sItem;
            if (is_dir($sPath)) {
                $this->removeDir($sPath);
            } else {
                @unlink($sPath);
            }
        }

        @rmdir($sDir);
    }
}
