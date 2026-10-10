package com.edusmartconsult.hospitality.data.net

import com.edusmartconsult.hospitality.core.AppJson
import com.edusmartconsult.hospitality.core.asObject
import com.edusmartconsult.hospitality.core.obj
import com.edusmartconsult.hospitality.core.str
import com.edusmartconsult.hospitality.data.AuthStore
import java.io.IOException
import java.util.concurrent.TimeUnit
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.put
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody

/** [code] is the server's machine-readable error code (e.g. "forbidden", "no_availability"); status 0 = no network. */
class ApiException(val status: Int, val code: String, message: String, val details: JsonObject? = null) : Exception(message) {
    val isOffline: Boolean get() = status == 0
}

sealed interface SessionEvent {
    data class LoggedOut(val reason: String) : SessionEvent
}

class Api(private val session: AuthStore, private val client: OkHttpClient = defaultClient()) {
    val events = MutableSharedFlow<SessionEvent>(extraBufferCapacity = 4)
    private val refreshLock = Mutex()
    private val json = "application/json; charset=utf-8".toMediaType()

    suspend fun get(path: String, query: Map<String, String?> = emptyMap()): JsonElement = call("GET", path, null, query)
    suspend fun post(path: String, body: JsonObject = JsonObject(emptyMap())): JsonElement = call("POST", path, body)
    suspend fun put(path: String, body: JsonObject): JsonElement = call("PUT", path, body)
    suspend fun patch(path: String, body: JsonObject): JsonElement = call("PATCH", path, body)

    private suspend fun call(method: String, path: String, body: JsonObject?, query: Map<String, String?> = emptyMap(), retry: Boolean = true): JsonElement {
        val (status, text) = execute(method, path, body, query)
        if (status in 200..299) return if (text.isBlank()) JsonNull else AppJson.parseToJsonElement(text)
        val err = runCatching { AppJson.parseToJsonElement(text).asObject().obj("error") }.getOrNull()
        val code = err?.str("code") ?: "http_$status"
        val message = err?.str("message") ?: "Request failed ($status)"
        if (status == 401 && code != "device_revoked" && retry && session.refreshToken != null && !path.startsWith("auth/")) {
            if (refresh()) return call(method, path, body, query, retry = false)
        }
        if (status == 401 && (code == "device_revoked" || code == "unauthenticated")) {
            session.clear()
            events.tryEmit(SessionEvent.LoggedOut(code))
        }
        throw ApiException(status, code, message, err?.obj("details"))
    }

    private suspend fun execute(method: String, path: String, body: JsonObject?, query: Map<String, String?>): Pair<Int, String> = withContext(Dispatchers.IO) {
        try {
            val url = session.baseUrl.toHttpUrl().newBuilder().addEncodedPathSegments(path.trimStart('/')).apply {
                query.forEach { (k, v) -> if (v != null) addQueryParameter(k, v) }
            }.build()
            val rb = Request.Builder().url(url).header("Accept", "application/json")
            session.accessToken?.let { rb.header("Authorization", "Bearer $it") }
            val payload = body?.let { AppJson.encodeToString(JsonObject.serializer(), it).toRequestBody(json) }
            rb.method(method, payload ?: if (method == "GET") null else "{}".toRequestBody(json))
            client.newCall(rb.build()).execute().use { it.code to (it.body?.string() ?: "") }
        } catch (e: IOException) {
            throw ApiException(0, "offline", "No connection")
        }
    }

    /** Single-flight token refresh. Refresh tokens rotate; reuse revokes the device, so never refresh concurrently. */
    private suspend fun refresh(): Boolean = refreshLock.withLock {
        val rt = session.refreshToken ?: return false
        val (status, text) = execute("POST", "auth/refresh", obj { put("refresh_token", rt); put("device_id", session.deviceId) }, emptyMap())
        if (status !in 200..299) {
            if (status == 401) { session.clear(); events.tryEmit(SessionEvent.LoggedOut("session_expired")) }
            return false
        }
        val o = AppJson.parseToJsonElement(text).asObject()
        session.accessToken = o.str("access_token")
        session.refreshToken = o.str("refresh_token")
        true
    }

    // ---- auth ----
    sealed interface LoginResult {
        data object Success : LoginResult
        data class TwoFactor(val challengeId: String) : LoginResult
    }

    private fun deviceJson(): JsonObject = obj {
        put("id", session.deviceId)
        put("name", android.os.Build.MODEL ?: "Android")
        put("platform", "android")
        put("app_version", com.edusmartconsult.hospitality.BuildConfig.VERSION_NAME)
    }

    suspend fun login(email: String, password: String): LoginResult {
        val (status, text) = execute("POST", "auth/login", obj { put("email", email); put("password", password); put("device", deviceJson()) }, emptyMap())
        val o = runCatching { AppJson.parseToJsonElement(text).asObject() }.getOrDefault(JsonObject(emptyMap()))
        return when (status) {
            200 -> { store(o); LoginResult.Success }
            202 -> LoginResult.TwoFactor(o.str("challenge_id") ?: "")
            else -> { val e = o.obj("error"); throw ApiException(status, e?.str("code") ?: "error", e?.str("message") ?: "Login failed", e?.obj("details")) }
        }
    }

    suspend fun twoFactor(challengeId: String, code: String) {
        val (status, text) = execute("POST", "auth/2fa", obj { put("challenge_id", challengeId); put("code", code) }, emptyMap())
        val o = runCatching { AppJson.parseToJsonElement(text).asObject() }.getOrDefault(JsonObject(emptyMap()))
        if (status != 200) { val e = o.obj("error"); throw ApiException(status, e?.str("code") ?: "error", e?.str("message") ?: "Invalid code") }
        store(o)
    }

    suspend fun logout() {
        runCatching { post("auth/logout") }
        session.clear()
    }

    private fun store(o: JsonObject) {
        session.accessToken = o.str("access_token")
        session.refreshToken = o.str("refresh_token")
    }

    companion object {
        fun defaultClient(): OkHttpClient = OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS).readTimeout(30, TimeUnit.SECONDS).writeTimeout(30, TimeUnit.SECONDS)
            .retryOnConnectionFailure(true).build()
    }
}
