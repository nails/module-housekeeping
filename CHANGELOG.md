# Changelog

## Unreleased

**Behaviour change.** Apps that rely on the defaults will, on the next midnight run, gzip logs older than 7 days and delete logs older than 90 days. Files between 90 and 180 days old are deleted on the first run after upgrading. Apps that need a longer window must set `LOG_RETENTION` and `LOG_ARCHIVE` before upgrading.

- `LOG_ARCHIVE` default is 7 days (was 14).
- `LOG_RETENTION` default is 90 days (was 180).
- `LOG_RETENTION=0` (or any value below 1) disables deletion and logs `DISABLED LOG_RETENTION=0`. It previously deleted every matching log. There is no setting that means "delete every log".
- `LOG_ARCHIVE=0` still disables compression.
