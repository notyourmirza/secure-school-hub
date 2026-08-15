using Microsoft.Data.Sqlite;

namespace SchoolSaaS.Desktop;

public sealed class SyncQueue
{
    private readonly string _connectionString;

    public SyncQueue(string databasePath)
    {
        _connectionString = $"Data Source={databasePath}";
        using var connection = new SqliteConnection(_connectionString);
        connection.Open();
        using var command = connection.CreateCommand();
        command.CommandText = """
            CREATE TABLE IF NOT EXISTS sync_queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                operation_uuid TEXT NOT NULL UNIQUE,
                client_uuid TEXT NOT NULL,
                entity TEXT NOT NULL DEFAULT 'students',
                operation_type TEXT NOT NULL DEFAULT 'create',
                method TEXT NOT NULL,
                endpoint TEXT NOT NULL,
                payload TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                attempts INTEGER NOT NULL DEFAULT 0,
                last_error TEXT NULL,
                next_attempt_at TEXT NULL,
                created_at TEXT NOT NULL,
                synced_at TEXT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_sync_queue_pending ON sync_queue(status, next_attempt_at, id);
            """;
        command.ExecuteNonQuery();
    }

    public void Enqueue(string method, string endpoint, string payload, string? clientUuid = null, string entity = "students", string operationType = "create", string? operationUuid = null)
    {
        using var connection = new SqliteConnection(_connectionString);
        connection.Open();
        using var command = connection.CreateCommand();
        command.CommandText = "INSERT OR IGNORE INTO sync_queue (operation_uuid, client_uuid, entity, operation_type, method, endpoint, payload, created_at) VALUES ($operation, $client, $entity, $type, $method, $endpoint, $payload, $created)";
        command.Parameters.AddWithValue("$operation", operationUuid ?? Guid.NewGuid().ToString());
        command.Parameters.AddWithValue("$client", clientUuid ?? Guid.NewGuid().ToString());
        command.Parameters.AddWithValue("$entity", entity);
        command.Parameters.AddWithValue("$type", operationType);
        command.Parameters.AddWithValue("$method", method);
        command.Parameters.AddWithValue("$endpoint", endpoint);
        command.Parameters.AddWithValue("$payload", payload);
        command.Parameters.AddWithValue("$created", DateTimeOffset.UtcNow.ToString("O"));
        command.ExecuteNonQuery();
    }

    public IReadOnlyList<QueuedOperation> Pending()
    {
        using var connection = new SqliteConnection(_connectionString);
        connection.Open();
        using var command = connection.CreateCommand();
        command.CommandText = "SELECT id, operation_uuid, client_uuid, entity, operation_type, method, endpoint, payload, attempts FROM sync_queue WHERE status = 'pending' AND (next_attempt_at IS NULL OR next_attempt_at <= $now) ORDER BY id";
        command.Parameters.AddWithValue("$now", DateTimeOffset.UtcNow.ToString("O"));
        using var reader = command.ExecuteReader();
        var result = new List<QueuedOperation>();
        while (reader.Read())
        {
            result.Add(new QueuedOperation(reader.GetInt64(0), reader.GetString(1), reader.GetString(2), reader.GetString(3), reader.GetString(4), reader.GetString(5), reader.GetString(6), reader.GetString(7), reader.GetInt32(8)));
        }
        return result;
    }

    public void MarkSynced(long id) => Update(id, "synced", null);
    public void MarkFailed(long id, string error) => Update(id, "pending", error);

    private void Update(long id, string status, string? error)
    {
        using var connection = new SqliteConnection(_connectionString);
        connection.Open();
        using var command = connection.CreateCommand();
        command.CommandText = "UPDATE sync_queue SET status = $status, attempts = attempts + 1, last_error = $error, next_attempt_at = CASE WHEN $status = 'pending' THEN $next ELSE NULL END, synced_at = CASE WHEN $status = 'synced' THEN $now ELSE synced_at END WHERE id = $id";
        command.Parameters.AddWithValue("$status", status);
        command.Parameters.AddWithValue("$error", (object?)error ?? DBNull.Value);
        command.Parameters.AddWithValue("$now", DateTimeOffset.UtcNow.ToString("O"));
        command.Parameters.AddWithValue("$next", DateTimeOffset.UtcNow.AddSeconds(BackoffSeconds(id)).ToString("O"));
        command.Parameters.AddWithValue("$id", id);
        command.ExecuteNonQuery();
    }

    private int BackoffSeconds(long id)
    {
        using var connection = new SqliteConnection(_connectionString);
        connection.Open();
        using var command = connection.CreateCommand();
        command.CommandText = "SELECT attempts FROM sync_queue WHERE id = $id";
        command.Parameters.AddWithValue("$id", id);
        var attempts = Convert.ToInt32(command.ExecuteScalar() ?? 0);
        return Math.Min(900, (int)Math.Pow(2, Math.Min(attempts, 10)));
    }
}

public sealed record QueuedOperation(long Id, string OperationUuid, string ClientUuid, string Entity, string OperationType, string Method, string Endpoint, string Payload, int Attempts);
