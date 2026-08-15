package com.schoolsass.app

import android.content.Context
import org.json.JSONArray
import org.json.JSONObject
import java.util.UUID

/** Existing durable queue plus cursor/cache state for pull reconciliation. */
class OfflineOperationStore(context: Context) {
    private val preferences = context.getSharedPreferences("offline_sync", Context.MODE_PRIVATE)
    private val operationsKey = "operations"
    private val cacheKey = "entity_cache"
    private val cursorKey = "pull_cursor"
    private val conflictsKey = "conflicts"

    @Synchronized
    fun enqueue(entity: String, operationType: String, clientUuid: String = UUID.randomUUID().toString(), payload: JSONObject): String {
        val operationUuid = UUID.randomUUID().toString()
        val operations = readOperations()
        operations.put(JSONObject().apply {
            put("operation_uuid", operationUuid)
            put("client_uuid", clientUuid)
            put("entity", entity)
            put("operation_type", operationType)
            put("payload", payload)
            put("attempts", 0)
            put("last_error", JSONObject.NULL)
        })
        saveOperations(operations)
        return operationUuid
    }

    @Synchronized fun pending(): List<JSONObject> = (0 until readOperations().length()).map { readOperations().getJSONObject(it) }

    @Synchronized
    fun remove(operationUuid: String) {
        val source = readOperations(); val remaining = JSONArray()
        for (index in 0 until source.length()) if (source.getJSONObject(index).optString("operation_uuid") != operationUuid) remaining.put(source.getJSONObject(index))
        saveOperations(remaining)
    }

    @Synchronized
    fun recordFailure(operationUuid: String, message: String) {
        val source = readOperations()
        for (index in 0 until source.length()) if (source.getJSONObject(index).optString("operation_uuid") == operationUuid) {
            val operation = source.getJSONObject(index)
            operation.put("attempts", operation.optInt("attempts", 0) + 1)
            operation.put("last_error", message.take(500))
        }
        saveOperations(source)
    }

    @Synchronized fun cursor(): Long = preferences.getLong(cursorKey, 0L)
    @Synchronized fun saveCursor(value: Long) { preferences.edit().putLong(cursorKey, value).apply() }

    @Synchronized
    fun cache(entity: String, canonicalId: String, record: JSONObject) {
        val source = readCache()
        source.put(cacheKey(entity, canonicalId), JSONObject(record.toString()))
        saveCache(source)
    }

    @Synchronized
    fun cached(entity: String): List<JSONObject> {
        val source = readCache(); val output = mutableListOf<JSONObject>(); val prefix = "$entity:"
        source.keys().forEach { key -> if (key.startsWith(prefix)) output.add(source.getJSONObject(key)) }
        return output
    }

    @Synchronized
    fun recordConflict(change: JSONObject, reason: String) {
        val conflicts = JSONArray(preferences.getString(conflictsKey, "[]"))
        conflicts.put(JSONObject().put("reason", reason).put("change", change).put("created_at", System.currentTimeMillis()))
        preferences.edit().putString(conflictsKey, conflicts.toString()).apply()
    }

    @Synchronized fun conflicts(): List<JSONObject> = (0 until JSONArray(preferences.getString(conflictsKey, "[]")).length()).map { JSONArray(preferences.getString(conflictsKey, "[]")).getJSONObject(it) }

    /** Removes a locally reviewed conflict record; server-side resolution still requires a supported API action. */
    @Synchronized fun acknowledgeConflict(index: Int) {
        val source = JSONArray(preferences.getString(conflictsKey, "[]"))
        if (index !in 0 until source.length()) return
        val remaining = JSONArray()
        for (position in 0 until source.length()) if (position != index) remaining.put(source.getJSONObject(position))
        preferences.edit().putString(conflictsKey, remaining.toString()).apply()
    }

    private fun cacheKey(entity: String, canonicalId: String) = "$entity:$canonicalId"
    private fun readOperations(): JSONArray = JSONArray(preferences.getString(operationsKey, "[]"))
    private fun saveOperations(value: JSONArray) { preferences.edit().putString(operationsKey, value.toString()).apply() }
    private fun readCache(): JSONObject = JSONObject(preferences.getString(cacheKey, "{}"))
    private fun saveCache(value: JSONObject) { preferences.edit().putString(cacheKey, value.toString()).apply() }
}
