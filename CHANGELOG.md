# Changelog

## 2.0.0 - 2026-09-22

- Switched the documented realtime path from Pusher Channels to Laravel Reverb.
- The progress bar opens a Reverb WebSocket directly. It no longer loads `pusher-js` or a Pusher cluster.
- Package events publish on the `reverb` connection by default, even if the app default broadcaster is still Pusher.
- Added a browser progress panel and a floating queue taskbar.
- Progress events now include percentage, processed, pending, failed, total, and per-task status.
- `pending` is the number of remaining jobs. Previously it reported processed jobs.
- Each batch broadcasts on `job-batches` and `job-batch.{id}` instead of one shared channel.
- Channels, queue, timeout, and broadcast connection are configurable.
- Added an optional local demo page at `/realtime-job-batch/demo`.
- Legacy Pusher channel names can be re-enabled with `REALTIME_JOB_LEGACY_CHANNELS=true`.

## 1.1.0 - 2023-09-06

- Added: Set repository method

## 1.0.0 - 2023-09-05

- Initial release
