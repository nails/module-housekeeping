<?php

/**
 * This file is the template for the contents of Housekeeping routines
 * Used by the console command when creating routines.
 */

return <<<'EOD'
<?php

/**
 * The {{CLASS_NAME}} housekeeping routine
 *
 * @package  App
 * @category Housekeeping
 */

namespace {{NAMESPACE}};

use Nails\Housekeeping\Routine\Base;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Routine\Result;

/**
 * Class {{CLASS_NAME}}
 *
 * @package {{NAMESPACE}}
 */
class {{CLASS_NAME}} extends Base
{
    /**
     * Description of the routine
     *
     * @var string
     */
    const DESCRIPTION = '';

    /**
     * The cron expression of when to run
     *
     * @var string
     */
    const CRON_EXPRESSION = '@daily';

    public function execute(Context $oContext): Result
    {
        $oContext->log('START custom work');

        // Perform cleanup here. Use Factory::service('Deleter', \Nails\Housekeeping\Constants::MODULE_SLUG)
        // or one of the official traits (DeletesModelRows, DeletesFiles, ArchivesFiles, TruncatesTable).

        return Result::ok();
    }
}

EOD;
