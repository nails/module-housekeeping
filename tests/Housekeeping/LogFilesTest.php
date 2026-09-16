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

class LogFilesArchiveDisabledHarness extends LogFilesArchive
{
    const CONFIG_ARCHIVE_DAYS  = 'LOG_ARCHIVE_TEST_DISABLED';
    const DEFAULT_ARCHIVE_DAYS = 0;

    protected function directory(): string
    {
        return sys_get_temp_dir();
    }
}

/**
 * @covers \Nails\Housekeeping\Housekeeping\LogFiles
 * @covers \Nails\Housekeeping\Housekeeping\LogFilesArchive
 */
class LogFilesTest extends TestCase
{
    public function test_log_files_matches_php_and_gzip_and_defaults_to_180_days(): void
    {
        $oRoutine = new LogFilesHarness();

        self::assertSame(['*.php', '*.php.gz'], $oRoutine->exposedPattern());
        self::assertSame(180, $oRoutine->exposedOlderThanDays());
        self::assertSame('0 0 * * *', $oRoutine->getCronExpression());
    }

    public function test_log_files_archive_defaults_to_14_days(): void
    {
        $oRoutine = new LogFilesArchiveHarness();

        self::assertSame(14, $oRoutine->exposedOlderThanDays());
        self::assertSame('0 0 * * *', $oRoutine->getCronExpression());
        self::assertSame(LogFiles::CRON_EXPRESSION, $oRoutine->getCronExpression());
    }

    public function test_log_files_archive_reads_config(): void
    {
        Config::set(LogFilesArchiveHarness::CONFIG_ARCHIVE_DAYS, 30);

        self::assertSame(30, (new LogFilesArchiveHarness())->exposedOlderThanDays());
    }

    public function test_log_files_archive_zero_disables_without_touching_files(): void
    {
        $oLogger = $this->createStub(Logger::class);
        $oLogger->method('routine')->willReturnSelf();
        $oContext = new Context(false, $oLogger, LogFilesArchiveDisabledHarness::class);

        $oResult = (new LogFilesArchiveDisabledHarness())->execute($oContext);

        self::assertInstanceOf(Result::class, $oResult);
        self::assertTrue($oResult->isSuccess());
        self::assertSame(0, $oResult->getProcessed());
        self::assertSame('Log archive disabled', $oResult->getMessage());
    }

    public function test_purge_runs_before_archive_by_class_name(): void
    {
        self::assertTrue(strcmp(LogFiles::class, LogFilesArchive::class) < 0);
    }
}
