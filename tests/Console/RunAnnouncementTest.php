<?php

namespace Tests\Housekeeping\Console;

use Nails\Housekeeping\Console\RunAnnouncement;
use PHPUnit\Framework\TestCase;

class RunAnnouncementTest extends TestCase
{
    public function test_nothing_due_stays_quiet(): void
    {
        self::assertFalse(RunAnnouncement::visible(false, null, false));
    }

    public function test_due_work_forced_runs_and_named_runs_announce(): void
    {
        self::assertTrue(RunAnnouncement::visible(false, null, true));
        self::assertTrue(RunAnnouncement::visible(true, null, false));
        self::assertTrue(RunAnnouncement::visible(false, 'LogFiles', false));
    }
}
