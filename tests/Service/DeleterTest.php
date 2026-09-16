<?php

namespace Tests\Housekeeping\Service;

use Nails\Housekeeping\Exception\HousekeepingException;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Service\Deleter;
use Nails\Housekeeping\Service\Logger;
use PHPUnit\Framework\TestCase;

class TestableDeleter extends Deleter
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
 * @covers \Nails\Housekeeping\Service\Deleter
 */
class DeleterTest extends TestCase
{
    private string $sDir;
    private \DateTime $oNow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oNow = new \DateTime('2026-09-16 12:00:00');
        $this->sDir = sys_get_temp_dir() . '/nails-housekeeping-' . uniqid('', true);
        mkdir($this->sDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->sDir)) {
            $this->removeDir($this->sDir);
        }
        parent::tearDown();
    }

    public function test_archive_files_gzips_old_php_and_leaves_recent_and_existing_gz(): void
    {
        $sOld     = $this->writeFile('log-2026-08-01.php', "old log\n", 20);
        $sRecent  = $this->writeFile('log-2026-09-10.php', "recent log\n", 6);
        $sAlready = $this->writeFile('log-2026-07-01.php.gz', gzencode("already\n") ?: '', 40);
        $sIgnored = $this->writeFile('notes.txt', "nope\n", 40);

        $oResult = $this->deleter()->archiveFiles(
            $this->context(),
            $this->sDir,
            '*.php',
            14
        );

        self::assertTrue($oResult->isSuccess());
        self::assertSame(1, $oResult->getProcessed());
        self::assertFileDoesNotExist($sOld);
        self::assertFileExists($sOld . '.gz');
        self::assertSame("old log\n", gzdecode((string) file_get_contents($sOld . '.gz')));
        self::assertFileExists($sRecent);
        self::assertFileExists($sAlready);
        self::assertFileExists($sIgnored);
    }

    public function test_archive_files_preserves_mtime(): void
    {
        $sOld   = $this->writeFile('log-2026-08-01.php', "old log\n", 20);
        $iMTime = (int) filemtime($sOld);

        $this->deleter()->archiveFiles($this->context(), $this->sDir, '*.php', 14);

        self::assertSame($iMTime, filemtime($sOld . '.gz'));
    }

    public function test_archive_files_dry_run_does_not_mutate(): void
    {
        $sOld = $this->writeFile('log-2026-08-01.php', "old log\n", 20);

        $oResult = $this->deleter()->archiveFiles(
            $this->context(true),
            $this->sDir,
            '*.php',
            14
        );

        self::assertTrue($oResult->isSuccess());
        self::assertSame(1, $oResult->getProcessed());
        self::assertFileExists($sOld);
        self::assertFileDoesNotExist($sOld . '.gz');
    }

    public function test_archive_files_skips_names_that_already_have_the_suffix(): void
    {
        $sGz = $this->writeFile('log-2026-07-01.php.gz', gzencode("cold\n") ?: '', 40);

        $oResult = $this->deleter()->archiveFiles(
            $this->context(),
            $this->sDir,
            '*',
            14
        );

        self::assertTrue($oResult->isSuccess());
        self::assertSame(0, $oResult->getProcessed());
        self::assertFileExists($sGz);
        self::assertFileDoesNotExist($sGz . '.gz');
    }

    public function test_archive_files_completes_an_interrupted_run(): void
    {
        $sOld = $this->writeFile('log-2026-08-01.php', "old log\n", 20);
        file_put_contents($sOld . '.gz', gzencode("old log\n"));

        $oResult = $this->deleter()->archiveFiles(
            $this->context(),
            $this->sDir,
            '*.php',
            14
        );

        self::assertTrue($oResult->isSuccess());
        self::assertSame(1, $oResult->getProcessed());
        self::assertFileDoesNotExist($sOld);
        self::assertFileExists($sOld . '.gz');
        self::assertSame("old log\n", gzdecode((string) file_get_contents($sOld . '.gz')));
    }

    public function test_delete_files_removes_old_php_and_gz(): void
    {
        $sOldPhp = $this->writeFile('log-2025-01-01.php', "old\n", 200);
        $sOldGz  = $this->writeFile('log-2025-02-01.php.gz', gzencode("old\n") ?: '', 200);
        $sHot    = $this->writeFile('log-2026-09-01.php', "hot\n", 20);
        $sCold   = $this->writeFile('log-2026-08-01.php.gz', gzencode("cold\n") ?: '', 40);

        $oResult = $this->deleter()->deleteFiles(
            $this->context(),
            $this->sDir,
            ['*.php', '*.php.gz'],
            180
        );

        self::assertTrue($oResult->isSuccess());
        self::assertSame(2, $oResult->getProcessed());
        self::assertFileDoesNotExist($sOldPhp);
        self::assertFileDoesNotExist($sOldGz);
        self::assertFileExists($sHot);
        self::assertFileExists($sCold);
    }

    public function test_delete_files_dry_run_does_not_unlink(): void
    {
        $sOld = $this->writeFile('log-2025-01-01.php', "old\n", 200);

        $oResult = $this->deleter()->deleteFiles(
            $this->context(true),
            $this->sDir,
            '*.php',
            180
        );

        self::assertTrue($oResult->isSuccess());
        self::assertSame(1, $oResult->getProcessed());
        self::assertFileExists($sOld);
    }

    public function test_missing_directory_throws(): void
    {
        $this->expectException(HousekeepingException::class);
        $this->expectExceptionMessage('Directory does not exist');

        $this->deleter()->deleteFiles(
            $this->context(),
            $this->sDir . '/missing',
            '*.php',
            180
        );
    }

    private function deleter(): TestableDeleter
    {
        return new TestableDeleter($this->oNow);
    }

    private function context(bool $bDryRun = false): Context
    {
        $oLogger = $this->createStub(Logger::class);
        $oLogger->method('routine')->willReturnSelf();

        return new Context($bDryRun, $oLogger, 'Tests\\Housekeeping\\Service\\DeleterTest');
    }

    private function writeFile(string $sName, string $sContents, int $iDaysAgo): string
    {
        $sPath = $this->sDir . DIRECTORY_SEPARATOR . $sName;
        file_put_contents($sPath, $sContents);
        touch($sPath, $this->oNow->getTimestamp() - ($iDaysAgo * 86400));

        return $sPath;
    }

    private function removeDir(string $sDir): void
    {
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
