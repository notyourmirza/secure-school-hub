package com.schoolsass.app

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

class SyncApi(private val baseUrl: String) {
    suspend fun push(token: String, operations: List<JSONObject>): JSONArray = withContext(Dispatchers.IO) {
        if (operations.isEmpty()) return@withContext JSONArray()
        val body = JSONObject().put("operations", JSONArray(operations)).toString()
        val response = request(token, "POST", "/api/v1/sync/push", body)
        response.getJSONObject("data").getJSONArray("results")
    }

    suspend fun pull(token: String, cursor: Long, limit: Int = 50): JSONObject = withContext(Dispatchers.IO) {
        request(token, "GET", "/api/v1/sync/pull?cursor=$cursor&limit=${limit.coerceIn(1, 100)}", null).getJSONObject("data")
    }

    suspend fun status(token: String): Long = withContext(Dispatchers.IO) {
        request(token, "GET", "/api/v1/sync/status", null).getJSONObject("data").optLong("cursor", 0L)
    }

    private fun request(token: String, method: String, path: String, body: String?): JSONObject {
        val connection = (URL("$baseUrl$path").openConnection() as HttpURLConnection).apply {
            requestMethod = method
            connectTimeout = 10000
            readTimeout = 20000
            setRequestProperty("Content-Type", "application/json")
            setRequestProperty("Authorization", "Bearer $token")
            if (body != null) doOutput = true
        }
        if (body != null) connection.outputStream.use { it.write(body.toByteArray(Charsets.UTF_8)) }
        val stream = if (connection.responseCode >= 400) connection.errorStream else connection.inputStream
        val response = JSONObject(stream.bufferedReader().use { it.readText() })
        if (connection.responseCode >= 400 || !response.optBoolean("success", false)) throw IllegalStateException(response.optString("message", "Sync request failed"))
        return response
    }
}
