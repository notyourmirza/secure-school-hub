package com.schoolsass.app

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.work.Constraints
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.workDataOf
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.util.UUID

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent { SchoolApp() }
    }
}

@Composable
private fun SchoolApp() {
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var token by remember { mutableStateOf<String?>(null) }
    var role by remember { mutableStateOf<String?>(null) }
    var status by remember { mutableStateOf("Not signed in") }
    var students by remember { mutableStateOf<List<String>>(emptyList()) }
    var records by remember { mutableStateOf<List<String>>(emptyList()) }
    var studentId by remember { mutableStateOf("") }
    var attendanceDate by remember { mutableStateOf("") }
    var attendanceStatus by remember { mutableStateOf("present") }
    var section by remember { mutableStateOf("home") }
    var conflicts by remember { mutableStateOf<List<String>>(emptyList()) }
    val scope = rememberCoroutineScope()
    val context = LocalContext.current
    val offlineStore = remember { OfflineOperationStore(context) }

    MaterialTheme {
        Scaffold(topBar = { TopAppBar(title = { Text("School SaaS · Teacher") }) }) { padding ->
            Column(Modifier.padding(padding).padding(20.dp).fillMaxSize(), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                if (token == null) {
                    Text("Secure teacher login", style = MaterialTheme.typography.headlineSmall)
                    OutlinedTextField(email, { email = it }, label = { Text("Email") }, modifier = Modifier.fillMaxWidth())
                    OutlinedTextField(password, { password = it }, label = { Text("Password") }, modifier = Modifier.fillMaxWidth())
                    Button(onClick = {
                        scope.launch {
                            status = "Signing in..."
                            try {
                                val result = api(BuildConfig.API_BASE_URL, "POST", "/api/v1/auth/login", null, JSONObject().put("email", email).put("password", password).toString())
                                val data = result.getJSONObject("data")
                                token = data.getString("token")
                                role = data.getJSONObject("user").getString("role")
                                status = "Signed in as ${role}"
                                syncPendingOperations(BuildConfig.API_BASE_URL, token!!, offlineStore) { message -> status = message }
                                syncPulledChanges(BuildConfig.API_BASE_URL, token!!, offlineStore) { message -> status = message }
                                conflicts = offlineStore.conflicts().map { it.optString("reason") + " · " + it.optJSONObject("change")?.optString("entity") }
                                scheduleBackgroundSync(context, token!!)
                            } catch (e: Exception) { status = "Login failed: ${e.message}" }
                        }
                    }) { Text("Login") }
                } else {
                    Text("Teacher workspace", style = MaterialTheme.typography.headlineSmall)
                    Text("Role: $role · $status")
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        Button(onClick = { section = "students"; scope.launch { students = loadList("/api/v1/teacher/students", token!!); status = "Students loaded" } }) { Text("Students") }
                        Button(onClick = { section = "attendance" }) { Text("Attendance") }
                        Button(onClick = { section = "timetable"; scope.launch { records = loadList("/api/v1/teacher/timetable", token!!); status = "Timetable loaded" } }) { Text("Timetable") }
                        Button(onClick = { section = "notices"; scope.launch { records = loadList("/api/v1/teacher/notices", token!!); status = "Notices loaded" } }) { Text("Notices") }
                        Button(onClick = { section = "marks"; scope.launch { records = loadList("/api/v1/teacher/marks", token!!); status = "Marks loaded" } }) { Text("Marks") }
                        Button(onClick = { section = "results"; scope.launch { records = loadList("/api/v1/results", token!!); status = "Results loaded" } }) { Text("Results") }
                        Button(onClick = { conflicts = offlineStore.conflicts().map { it.optString("reason") + " · " + it.optJSONObject("change")?.optString("entity") }; section = "conflicts" }) { Text("Conflicts (${conflicts.size})") }
                    }
                    when (section) {
                        "attendance" -> AttendancePanel(studentId, { studentId = it }, attendanceDate, { attendanceDate = it }, attendanceStatus, { attendanceStatus = it }, token!!, offlineStore, { status = it })
                        "students" -> LazyColumn { items(students) { Text(it, Modifier.padding(vertical = 8.dp)) } }
                        "marks", "results" -> LazyColumn { items(records) { Text(it, Modifier.padding(vertical = 8.dp)) } }
                        "conflicts" -> LazyColumn { items(conflicts) { conflict ->
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text(conflict, Modifier.padding(vertical = 8.dp))
                                Button(onClick = { val index = conflicts.indexOf(conflict); offlineStore.acknowledgeConflict(index); conflicts = offlineStore.conflicts().map { it.optString("reason") + " · " + it.optJSONObject("change")?.optString("entity") }; status = "Conflict acknowledged locally; server resolution still requires a supported API action" }) { Text("Acknowledge") }
                            }
                        } }
                        else -> LazyColumn { items(records) { Text(it, Modifier.padding(vertical = 8.dp)) } }
                    }
                }
            }
        }
    }
}

@Composable
private fun AttendancePanel(studentId: String, setStudentId: (String) -> Unit, date: String, setDate: (String) -> Unit, attendanceStatus: String, setAttendanceStatus: (String) -> Unit, token: String, store: OfflineOperationStore, report: (String) -> Unit) {
    val scope = rememberCoroutineScope()
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text("Record attendance", style = MaterialTheme.typography.titleLarge)
        OutlinedTextField(studentId, setStudentId, label = { Text("Student ID") }, modifier = Modifier.fillMaxWidth())
        OutlinedTextField(date, setDate, label = { Text("Date YYYY-MM-DD") }, modifier = Modifier.fillMaxWidth())
        OutlinedTextField(attendanceStatus, setAttendanceStatus, label = { Text("Status: present / absent / late") }, modifier = Modifier.fillMaxWidth())
        Button(onClick = {
            scope.launch {
                val payload = JSONObject().put("student_id", studentId.toIntOrNull() ?: 0).put("attendance_date", date).put("status", attendanceStatus)
                val operationUuid = store.enqueue("attendance", "create", UUID.randomUUID().toString(), payload)
                try {
                    val results = SyncApi(BuildConfig.API_BASE_URL).push(token, store.pending().filter { it.optString("operation_uuid") == operationUuid })
                    val result = results.optJSONObject(0)
                    if (result != null && (result.optString("status") == "applied" || result.optString("status") == "replayed")) {
                        store.remove(operationUuid)
                        report("Attendance saved with duplicate-safe sync")
                    } else report("Attendance remains queued for retry")
                } catch (_: Exception) { report("Offline: attendance queued safely for reconnect sync") }
            }
        }) { Text("Save attendance") }
    }
}

private suspend fun loadList(path: String, bearer: String): List<String> {
    val result = api(BuildConfig.API_BASE_URL, "GET", path, bearer, null)
    val array = result.optJSONArray("data") ?: JSONArray()
    return (0 until array.length()).map { index ->
        val row = array.getJSONObject(index)
        row.keys().asSequence().take(5).joinToString(" · ") { key -> "$key=${row.optString(key)}" }
    }
}

private suspend fun api(baseUrl: String, method: String, path: String, bearer: String?, body: String?): JSONObject = withContext(Dispatchers.IO) {
    val connection = (URL("$baseUrl$path").openConnection() as HttpURLConnection).apply {
        requestMethod = method
        connectTimeout = 10000
        readTimeout = 10000
        setRequestProperty("Content-Type", "application/json")
        bearer?.let { setRequestProperty("Authorization", "Bearer $it") }
        if (body != null) { doOutput = true; outputStream.use { it.write(body.toByteArray(Charsets.UTF_8)) } }
    }
    val stream = if (connection.responseCode >= 400) connection.errorStream else connection.inputStream
    val response = JSONObject(stream.bufferedReader().use { it.readText() })
    if (connection.responseCode >= 400 || !response.optBoolean("success", false)) {
        val code = response.optJSONObject("error")?.optString("code", "") ?: ""
        val message = when (connection.responseCode) {
            401 -> "Your session has expired. Please sign in again."
            403 -> "You are not authorized for this school resource."
            404 -> "The requested record was not found."
            422 -> response.optString("message", "Please check the entered values.")
            429 -> "Too many requests. Please wait and try again."
            else -> if (code == "SCHOOL_INACTIVE") "This school account is not active." else response.optString("message", "The server could not complete the request.")
        }
        throw IllegalStateException(message)
    }
    response
}

private suspend fun syncPulledChanges(baseUrl: String, token: String, store: OfflineOperationStore, report: (String) -> Unit) {
    try {
        var cursor = store.cursor()
        var page: JSONObject
        do {
            page = SyncApi(baseUrl).pull(token, cursor)
            val changes = page.optJSONArray("changes") ?: JSONArray()
            for (index in 0 until changes.length()) {
                val change = changes.getJSONObject(index)
                cursor = maxOf(cursor, change.optLong("cursor", cursor))
                val entity = change.optString("entity")
                val canonicalId = change.optString("canonical_id")
                if (change.optString("status") == "conflict") store.recordConflict(change, "Server reported a conflict")
                else if (entity.isNotBlank() && canonicalId.isNotBlank()) store.cache(entity, canonicalId, change.optJSONObject("payload") ?: JSONObject())
            }
            store.saveCursor(cursor)
        } while (page.optBoolean("has_more", false))
        val conflicts = store.conflicts().size
        report(if (conflicts == 0) "Server changes reconciled" else "Server changes reconciled; $conflicts conflict(s) require review")
    } catch (error: Exception) {
        report("Local data remains available; pull sync pending: ${error.message}")
    }
}

private fun scheduleBackgroundSync(context: android.content.Context, token: String) {
    val constraints = Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build()
    val request = OneTimeWorkRequestBuilder<SyncWorker>().setConstraints(constraints).setInputData(workDataOf(SyncWorker.KEY_TOKEN to token, SyncWorker.KEY_BASE_URL to BuildConfig.API_BASE_URL)).build()
    WorkManager.getInstance(context).enqueueUniqueWork(SyncWorker.UNIQUE_NAME, ExistingWorkPolicy.REPLACE, request)
}

private suspend fun syncPendingOperations(baseUrl: String, token: String, store: OfflineOperationStore, report: (String) -> Unit) {
    val pending = store.pending()
    if (pending.isEmpty()) return
    try {
        val results = SyncApi(baseUrl).push(token, pending)
        for (index in 0 until results.length()) {
            val result = results.getJSONObject(index)
            val operationUuid = result.optString("operation_uuid")
            if ((result.optString("status") == "applied" || result.optString("status") == "replayed") && operationUuid.isNotBlank()) store.remove(operationUuid)
            else if (operationUuid.isNotBlank()) store.recordFailure(operationUuid, result.optString("error_code", "SYNC_FAILED"))
        }
        report("Offline changes synchronized: ${results.length()}")
    } catch (error: Exception) { report("Offline sync pending: ${error.message}") }
}
