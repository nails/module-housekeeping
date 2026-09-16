<?php

namespace Tests\Housekeeping\Service;

use Nails\Common\Factory\Logger as CommonLogger;
use Nails\Housekeeping\Service\Logger;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Nails\Housekeeping\Service\Logger
 */
class LoggerTest extends TestCase
{
    private string $sDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sDir = sys_get_temp_dir() . '/nails-housekeeping-logs-' . uniqid('', true) . '/';
        mkdir($this->sDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->sDir . '*') ?: [] as $sFile) {
            @unlink($sFile);
        }
        @rmdir($this->sDir);
        parent::tearDown();
    }

    public function test_list_log_files_includes_gzipped_archives_newest_first(): void
    {
        file_put_contents($this->sDir . 'housekeeping-2026-09-16.php', $this->logBody("today\n"));
        file_put_contents(
            $this->sDir . 'housekeeping-2026-09-01.php.gz',
            gzencode($this->logBody("cold\n"))
        );
        file_put_contents($this->sDir . 'log-2026-09-16.php', "not housekeeping\n");

        $aFiles = $this->logger()->listLogFiles();

        self::assertSame(
            [
                $this->sDir . 'housekeeping-2026-09-16.php',
                $this->sDir . 'housekeeping-2026-09-01.php.gz',
            ],
            $aFiles
        );
    }

    public function test_read_log_tail_decompresses_gzipped_files(): void
    {
        file_put_contents(
            $this->sDir . 'housekeeping-2026-09-01.php.gz',
            gzencode($this->logBody("INFO - cold line\n"))
        );

        $sContents = $this->logger()->readLogTail('housekeeping-2026-09-01.php.gz');

        self::assertSame("INFO - cold line\n", $sContents);
    }

    public function test_read_log_tail_still_reads_uncompressed_files(): void
    {
        file_put_contents(
            $this->sDir . 'housekeeping-2026-09-16.php',
            $this->logBody("INFO - hot line\n")
        );

        $sContents = $this->logger()->readLogTail('housekeeping-2026-09-16.php');

        self::assertSame("INFO - hot line\n", $sContents);
    }

    public function test_read_log_tail_rejects_unexpected_names(): void
    {
        file_put_contents($this->sDir . 'log-2026-09-16.php', "nope\n");

        self::assertSame('', $this->logger()->readLogTail('log-2026-09-16.php'));
        self::assertSame('', $this->logger()->readLogTail('../housekeeping-2026-09-16.php'));
    }

    private function logger(): Logger
    {
        $oCommonLogger = $this->createStub(CommonLogger::class);
        $oCommonLogger->method('getDir')->willReturn($this->sDir);
        $oCommonLogger->method('getFile')->willReturn('housekeeping-2026-09-16.php');

        return new Logger($oCommonLogger, 'session', 'housekeeping-2026-09-16.php');
    }

    private function logBody(string $sBody): string
    {
        return "<?php die('Unauthorised'); ?>\n" . $sBody;
    }
}
