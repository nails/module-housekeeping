# Changelog

## Unreleased

**Behaviour change.** Apps that rely on the defaults will, on the next midnight run, gzip logs older than 7 days and delete logs older than 90 days. Files between 90 and 180 days old are deleted on the first run after upgrading. Apps that need a longer window must set `LOG_RETENTION` and `LOG_ARCHIVE` before upgrading.

- `LOG_ARCHIVE` default is 7 days (was 14).
- `LOG_RETENTION` default is 90 days (was 180).
- `LOG_RETENTION=0` (or any value below 1) keeps logs that do not have their own positive channel threshold. It previously deleted every matching log. There is no setting that means "delete every log".
- `LOG_ARCHIVE=0` disables compression for files that do not have their own positive channel threshold.
- Per-channel keys `LOG_RETENTION_<CHANNEL>` and `LOG_ARCHIVE_<CHANNEL>` override the catch-all for that file. The channel is the filename before the date (`cron-2026-01-01.php` → `CRON`, `cdn-issues-2025-10-16-10-42-07.php` → `CDN_ISSUES`). A value below 1 disables that channel only, so `LOG_RETENTION_CRON=30` still deletes cron logs when `LOG_RETENTION=0`.
- Fixed `Data too long for column 'key'` when stamping the last run. The setting key is now `last_run.` plus an md5 of the routine class (41 characters), so it fits the 50 character `key` column. Existing last-run stamps are not migrated; each routine's last run shows again after its next run.
- `housekeeping:run` writes nothing when no routine is due. A run that executes routines still logs the banner, the discovery line, and each routine's audit output. The cron task sets `LOG_OUTPUT` to false, so a successful run is not copied into the cron log.
