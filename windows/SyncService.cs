using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Text.Json;

namespace SchoolSaaS.Desktop;

public sealed class SyncService
{
    private readonly HttpClient _http;
    private readonly SyncQueue _queue;

    public SyncService(string apiBaseUrl, SyncQueue queue)
    {
        _http = new HttpClient { BaseAddress = new Uri(apiBaseUrl.TrimEnd('/')) };
        _queue = queue;
    }

    public async Task<SyncSummary> PushPendingAsync(string token, CancellationToken cancellationToken = default)
    {
        _http.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", token);
        var pending = _queue.Pending();
        if (pending.Count == 0) return new SyncSummary(0, 0, 0);

        var operations = pending.Select(operation => new
        {
            operation_uuid = operation.OperationUuid,
            client_uuid = operation.ClientUuid,
            entity = operation.Entity,
            operation_type = operation.OperationType,
            payload = JsonSerializer.Deserialize<JsonElement>(operation.Payload)
        }).ToArray();

        try
        {
            using var response = await _http.PostAsJsonAsync("/api/v1/sync/push", new { operations }, cancellationToken);
            var document = await response.Content.ReadFromJsonAsync<JsonElement>(cancellationToken: cancellationToken);
            if (!response.IsSuccessStatusCode || !document.GetProperty("success").GetBoolean())
            {
                foreach (var operation in pending) _queue.MarkFailed(operation.Id, $"HTTP {(int)response.StatusCode}");
                return new SyncSummary(pending.Count, 0, pending.Count);
            }

            var results = document.GetProperty("data").GetProperty("results");
            var applied = 0;
            var failed = 0;
            foreach (var result in results.EnumerateArray())
            {
                var operationUuid = result.GetProperty("operation_uuid").GetString();
                var operation = pending.FirstOrDefault(item => item.OperationUuid == operationUuid);
                if (operation is null) continue;
                if (result.GetProperty("status").GetString() == "applied")
                {
                    _queue.MarkSynced(operation.Id);
                    applied++;
                }
                else
                {
                    _queue.MarkFailed(operation.Id, result.TryGetProperty("error_code", out var error) ? error.GetString() ?? "SYNC_FAILED" : "SYNC_FAILED");
                    failed++;
                }
            }
            return new SyncSummary(pending.Count, applied, failed);
        }
        catch (Exception exception) when (exception is HttpRequestException or TaskCanceledException)
        {
            foreach (var operation in pending) _queue.MarkFailed(operation.Id, exception.Message);
            return new SyncSummary(pending.Count, 0, pending.Count);
        }
    }
}

public sealed record SyncSummary(int Total, int Applied, int Failed);
