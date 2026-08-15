package com.schoolsass.app

import androidx.work.CoroutineWorker
import androidx.work.NetworkType
import androidx.work.WorkerParameters
import android.content.Context
import androidx.work.workDataOf
import org.json.JSONObject

class SyncWorker(appContext: Context, workerParams: WorkerParameters) : CoroutineWorker(appContext, workerParams) {
    override suspend fun doWork(): Result {
        val token = inputData.getString(KEY_TOKEN) ?: return Result.failure(workDataOf("error" to "Missing access token"))
        val baseUrl = inputData.getString(KEY_BASE_URL) ?: return Result.failure(workDataOf("error" to "Missing API base URL"))
        val store = OfflineOperationStore(applicationContext)
        val api = SyncApi(baseUrl)
        return try {
            var failed = false
            val pending = store.pending()
            if (pending.isNotEmpty()) {
                val results = api.push(token, pending)
                for (index in 0 until results.length()) {
                    val result = results.getJSONObject(index)
                    val operationUuid = result.optString("operation_uuid")
                    if ((result.optString("status") == "applied" || result.optString("status") == "replayed") && operationUuid.isNotBlank()) store.remove(operationUuid)
                    else if (operationUuid.isNotBlank()) { failed = true; store.recordFailure(operationUuid, result.optString("error_code", "SYNC_FAILED")) }
                }
            }
            var cursor = store.cursor()
            var page: JSONObject
            do {
                page = api.pull(token, cursor)
                val changes = page.optJSONArray("changes") ?: org.json.JSONArray()
                for (index in 0 until changes.length()) {
                    val change = changes.getJSONObject(index)
                    val changeCursor = change.optLong("cursor", cursor)
                    val status = change.optString("status")
                    val entity = change.optString("entity")
                    val canonicalId = change.optString("canonical_id")
                    if (status == "conflict") store.recordConflict(change, "Server reported a conflict")
                    else if (entity.isNotBlank() && canonicalId.isNotBlank()) store.cache(entity, canonicalId, change.optJSONObject("payload") ?: JSONObject())
                    cursor = maxOf(cursor, changeCursor)
                }
                store.saveCursor(cursor)
            } while (page.optBoolean("has_more", false))
            if (failed) Result.retry() else Result.success()
        } catch (_: Exception) {
            Result.retry()
        }
    }

    companion object {
        const val KEY_TOKEN = "access_token"
        const val KEY_BASE_URL = "api_base_url"
        const val UNIQUE_NAME = "school-saas-offline-sync"
    }
}
