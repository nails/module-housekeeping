# Nails Housekeeping

A generic orchestrator for cleanup routines in Nails applications.

Modules and apps define routines by implementing `Nails\Housekeeping\Interfaces\Routine` (normally by extending `Nails\Housekeeping\Routine\Base`) under `src/Housekeeping/`. Routines are discovered automatically, scheduled via a single `housekeeping:run` cron task, and every removal is written to `housekeeping-YYYY-MM-DD.php`.

See [the documentation](https://docs.nailsapp.co.uk/modules/housekeeping) for the authoring API, official traits, and console commands.
