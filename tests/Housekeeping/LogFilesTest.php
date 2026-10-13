<?php

namespace Tests\Housekeeping\Housekeeping;

use Nails\Config;
use Nails\Housekeeping\Housekeeping\LogFiles;
use Nails\Housekeeping\Housekeeping\LogFilesArchive;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Routine\Result;
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
    const CONFIG_RETENTION_DAYS = 'LOG_RETENTION_TEST_ZERO';

    public function __construct(private readonly string $sDirectory)
    {
    }

    protected function directory(): string
    {
        return $this->sDirectory;
    }
}

class LogFilesArchiveDirHarness extends LogFilesArchive
{
    const CONFIG_ARCHIVE_DAYS = 'LOG_ARCHIVE_TEST_ZERO';

    public function __construct(private readonly string $sDirectory)
    {
    }

    protected function directory(): string
    {
        return $this->sDirectory;
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
        $sDir  = $this->makeDir();
        $sFile = $sDir . DIRECTORY_SEPARATOR . 'log-old.php';
        file_put_contents($sFile, "old\n");
        touch($sFile, time() - (400 * 86400));

        Config::set(LogFilesDirHarness::CONFIG_RETENTION_DAYS, 0);

        $aLogs    = [];
        $oContext = $this->context($aLogs, LogFilesDirHarness::class);
        $oResult  = (new LogFilesDirHarness($sDir))->execute($oContext);

        self::assertTrue($oResult->isSuccess());
        self::assertSame(0, $oResult->getProcessed());
        self::assertSame('Log retention disabled', $oResult->getMessage());
        self::assertFileExists($sFile);
        self::assertContains('DISABLED ' . LogFilesDirHarness::CONFIG_RETENTION_DAYS . '=0', $aLogs);
    }

    public function test_log_files_archive_zero_disables_without_touching_files(): void
    {
        $sDir  = $this->makeDir();
        $sFile = $sDir . DIRECTORY_SEPARATOR . 'log-old.php';
        file_put_contents($sFile, "old\n");
        touch($sFile, time() - (40 * 86400));

        Config::set(LogFilesArchiveDirHarness::CONFIG_ARCHIVE_DAYS, 0);

        $aLogs    = [];
        $oContext = $this->context($aLogs, LogFilesArchiveDirHarness::class);
        $oResult  = (new LogFilesArchiveDirHarness($sDir))->execute($oContext);

        self::assertInstanceOf(Result::class, $oResult);
        self::assertTrue($oResult->isSuccess());
        self::assertSame(0, $oResult->getProcessed());
        self::assertSame('Log archive disabled', $oResult->getMessage());
        self::assertFileExists($sFile);
        self::assertFileDoesNotExist($sFile . '.gz');
        self::assertContains('DISABLED ' . LogFilesArchiveDirHarness::CONFIG_ARCHIVE_DAYS . '=0', $aLogs);
    }

    public function test_purge_runs_before_archive_by_class_name(): void
    {
        self::assertTrue(strcmp(LogFiles::class, LogFilesArchive::class) < 0);
    }

    /**
     * @param string[] $aLogs
     */
    private function context(array &$aLogs, string $sRoutineClass): Context
    {
        $oLogger = $this->createStub(Logger::class);
        $oLogger->method('routine')->willReturnCallback(
            function (string $sClass, string $sMessage) use (&$aLogs, $oLogger): Logger {
                $aLogs[] = $sMessage;
                return $oLogger;
            }
        );

        return new Context(false, $oLogger, $sRoutineClass);
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
