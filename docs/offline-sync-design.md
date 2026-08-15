# Offline-First Synchronization Design

## Objective

The Windows admin application and Android teacher application must remain usable when the network is unavailable. Local writes are committed to the platform’s local store first, then queued for delivery to the PHP API when connectivity returns. MySQL remains the central source of truth; clients never connect to it directly.

## Identity and idempotency

Every locally created business record receives a stable `client_uuid` before it is written to SQLite or the Android local database. Every queued mutation receives a separate stable `operation_uuid`. The API treats `operation_uuid` as an idempotency key and stores it in `sync_operations` with a unique constraint. A retried request with the same operation UUID must return the original result rather than insert a second row.

The business record also keeps its `client_uuid` where appropriate. Tables that accept offline creation must enforce a tenant-scoped unique constraint on `(school_id, client_uuid)`. The server derives `school_id` from the access token and ignores any client-supplied tenant identifier.

## Operation lifecycle

| State | Meaning | Client behavior |
|---|---|---|
| `pending` | Locally created and waiting for transport | Retry when online |
| `sending` | Claimed by a sync worker | Do not send concurrently again |
| `applied` | Accepted by PHP/MySQL | Remove from active queue and retain canonical mapping |
| `conflict` | Server rejected because the record version changed | Show conflict and require a deliberate resolution |
| `failed` | Transport or validation failed after retry policy | Keep visible for manual retry |

The server applies each operation in a transaction. It first checks `sync_operations.operation_uuid`. If the operation is already `applied`, it returns the stored canonical response. If it is new, it validates permissions and tenant context, applies the mutation, records the result, and commits. A transaction rollback leaves no partial business record.

## Conflict policy

Each synchronized entity should carry a server version or `updated_at` value. Updates include the client’s `base_version`. If the server version has changed since the client last pulled the record, the API returns `409 SYNC_CONFLICT` with both the server record and the client payload. Deletes become tombstones or soft deletes so a later pull cannot resurrect stale data. Automatic last-write-wins is not used for financially sensitive records, attendance corrections, or identity fields.

## Pull and push contract

`POST /api/v1/sync/push` accepts a batch of operations and returns one result per `operation_uuid`, including `status`, `client_uuid`, `canonical_id`, and an optional conflict/error object. The batch must be independently idempotent; retrying the entire batch is safe.

`GET /api/v1/sync/pull?cursor=...` returns tenant-authorized changes after a cursor, including creates, updates, and tombstones. The cursor is advanced only after the client commits the received changes locally. `GET /api/v1/sync/status` returns pending count, failed count, last successful sync, and the server cursor.

## WPF behavior

The WPF SQLite queue is durable and uses a unique client UUID. A single sync worker claims pending operations, sends them in order, applies canonical IDs, and marks each result. It uses bounded exponential backoff, records the last error, and exposes failed operations through Sync Center. Network loss never discards a local write.

## Android behavior

The Android application must use a local database/cache and repository boundary. The UI reads cached records immediately and displays online/offline state. Offline attendance and teacher-authorized updates are written locally with client and operation UUIDs. WorkManager or an equivalent lifecycle-aware worker retries synchronization when network constraints are satisfied. Tokens must be stored in encrypted platform storage, not plain preferences.

## Duplicate-prevention tests

The test suite must submit the same operation UUID twice, retry a timed-out response, retry an entire batch, and attempt the same client UUID under the same tenant. Each case must produce one canonical MySQL record. A second tenant must not be able to reuse or retrieve another tenant’s operation UUID. Conflict, validation failure, and partial batch failure must remain visible and retryable without creating duplicates.

The first integration test has passed against local MySQL: the same `operation_uuid` and `client_uuid` were pushed twice, both responses returned canonical student ID `3`, and the database contained exactly one matching student row. The WPF queue now stores separate operation and record UUIDs, retry backoff, next-attempt scheduling, and failure details. The Android foundation now has a durable local operation store and replays pending operations through the same PHP endpoint after login; full lifecycle-aware background scheduling remains to be completed.
