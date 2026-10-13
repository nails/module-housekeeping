<?php

namespace Tests\Housekeeping\Log;

use Nails\Config;
use Nails\Housekeeping\Log\Thresholds;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Nails\Housekeeping\Log\Thresholds
 */
class ThresholdsTest extends TestCase
{
    private Thresholds $oThresholds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oThresholds = new Thresholds();
    }

    public function test_channel_override_beats_catch_all_and_catch_all_beats_default(): void
    {
        $sKey = 'LOG_RETENTION_TEST_PRECEDENCE';
        Config::set($sKey, 40);
        Config::set($sKey . '_CRON', 7);

        $oCron = $this->oThresholds->forFile('cron-2026-01-01.php', $sKey, 90);
        $oApi  = $this->oThresholds->forFile('api-2026-01-01.php', $sKey, 90);

        self::assertSame('cron', $oCron->channel);
        self::assertSame(7, $oCron->days);
        self::assertSame('api', $oApi->channel);
        self::assertSame(40, $oApi->days);

        $oUnset = $this->oThresholds->forFile('mfa-2026-01-01.php', 'LOG_RETENTION_TEST_UNSET', 90);
        self::assertSame('mfa', $oUnset->channel);
        self::assertSame(90, $oUnset->days);
    }

    public function test_hyphenated_channel_names_normalise(): void
    {
        $sKey = 'LOG_RETENTION_TEST_CDN_ISSUES_KEY';
        Config::set($sKey . '_CDN_ISSUES', 12);

        self::assertSame('CDN_ISSUES', $this->oThresholds->normalise('cdn-issues'));

        $oFile = $this->oThresholds->forFile('cdn-issues-2025-10-16-10-42-07.php', $sKey, 90);

        self::assertSame('cdn-issues', $oFile->channel);
        self::assertSame(12, $oFile->days);
    }

    public function test_unrecognised_filenames_use_the_catch_all(): void
    {
        $sKey = 'LOG_RETENTION_TEST_UNRECOGNISED';
        Config::set($sKey, 40);
        Config::set($sKey . '_NOTES', 1);

        $oFile = $this->oThresholds->forFile('notes.php', $sKey, 90);

        self::assertNull($this->oThresholds->channel('notes.php'));
        self::assertSame(Thresholds::CATCH_ALL, $oFile->channel);
        self::assertSame(40, $oFile->days);
    }

    public function test_gzip_resolves_to_the_same_channel_as_plain(): void
    {
        $sKey = 'LOG_ARCHIVE_TEST_GZIP_CHANNEL';
        Config::set($sKey . '_CRON', 3);

        $oPlain = $this->oThresholds->forFile('cron-2026-01-01.php', $sKey, 7);
        $oGzip  = $this->oThresholds->forFile('cron-2026-01-01.php.gz', $sKey, 7);

        self::assertSame('cron', $this->oThresholds->channel('cron-2026-01-01.php'));
        self::assertSame('cron', $this->oThresholds->channel('cron-2026-01-01.php.gz'));
        self::assertSame($oPlain->channel, $oGzip->channel);
        self::assertSame($oPlain->days, $oGzip->days);
        self::assertSame(3, $oGzip->days);
    }
}
