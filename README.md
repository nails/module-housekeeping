# Nails Housekeeping

A generic orchestrator for cleanup routines in Nails applications.

Modules and apps define routines by implementing `Nails\Housekeeping\Interfaces\Routine` (normally by extending `Nails\Housekeeping\Routine\Base`) under `src/Housekeeping/`. Routines are discovered automatically, scheduled via a single `housekeeping:run` cron task, and every action is written to `housekeeping-YYYY-MM-DD.php`. A minute with nothing due produces no console output. The cron task does not copy a successful run into the cron log; a non-zero exit still is.

See [the documentation](https://docs.nailsapp.co.uk/modules/housekeeping) for the authoring API, official traits, and console commands.
