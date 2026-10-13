# Changelog

## Unreleased

**Behaviour change.** Apps that rely on the defaults will, on the next midnight run, gzip logs older than 7 days and delete logs older than 90 days. Files between 90 and 180 days old are deleted on the first run after upgrading. Apps that need a longer window must set `LOG_RETENTION` and `LOG_ARCHIVE` before upgrading.

- `LOG_ARCHIVE` default is 7 days (was 14).
- `LOG_RETENTION` default is 90 days (was 180).
- `LOG_RETENTION=0` (or any value below 1) keeps logs that do not have their own positive channel threshold. It previously deleted every matching log. There is no setting that means "delete every log".
- `LOG_ARCHIVE=0` disables compression for files that do not have their own positive channel threshold.
- Per-channel keys `LOG_RETENTION_<CHANNEL>` and `LOG_ARCHIVE_<CHANNEL>` override the catch-all for that file. The channel is the filename before the date (`cron-2026-01-01.php` → `CRON`, `cdn-issues-2025-10-16-10-42-07.php` → `CDN_ISSUES`). A value below 1 disables that channel only, so `LOG_RETENTION_CRON=30` still deletes cron logs when `LOG_RETENTION=0`.
