package com.edusmartconsult.hospitality.data

import android.content.Context
import android.content.SharedPreferences
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import com.edusmartconsult.hospitality.BuildConfig
import com.edusmartconsult.hospitality.core.newId

/** What the API client needs from storage (an interface so the client can be unit-tested). */
interface AuthStore {
    val deviceId: String
    var accessToken: String?
    var refreshToken: String?
    var baseUrl: String
    fun clear()
}

/** Tokens live in Keystore-backed encrypted preferences. The device id is stable per install. */
class Session(context: Context) : AuthStore {
    private val secure: SharedPreferences = try {
        val key = MasterKey.Builder(context).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build()
        EncryptedSharedPreferences.create(context, "session_secure", key,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV, EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM)
    } catch (e: Exception) {
        context.getSharedPreferences("session_fallback", Context.MODE_PRIVATE) // very old/broken keystore: still sandboxed
    }
    private val plain: SharedPreferences = context.getSharedPreferences("app", Context.MODE_PRIVATE)

    override val deviceId: String
        get() = plain.getString("device_id", null) ?: newId().also { plain.edit().putString("device_id", it).apply() }

    override var accessToken: String?
        get() = secure.getString("access", null)
        set(v) = secure.edit().putString("access", v).apply()
    override var refreshToken: String?
        get() = secure.getString("refresh", null)
        set(v) = secure.edit().putString("refresh", v).apply()

    override var baseUrl: String
        get() = plain.getString("base_url", null) ?: BuildConfig.API_BASE_URL
        set(v) = plain.edit().putString("base_url", if (v.endsWith("/")) v else "$v/").apply()

    var contextJson: String?
        get() = plain.getString("ctx", null)
        set(v) = plain.edit().putString("ctx", v).apply()
    var syncCursors: String?
        get() = plain.getString("cursors", null)
        set(v) = plain.edit().putString("cursors", v).apply()
    var lastSync: Long
        get() = plain.getLong("last_sync", 0)
        set(v) = plain.edit().putLong("last_sync", v).apply()
    var orderSeq: Int
        get() = plain.getInt("order_seq", 0)
        set(v) = plain.edit().putInt("order_seq", v).apply()
    var printerAddress: String?
        get() = plain.getString("printer", null)
        set(v) = plain.edit().putString("printer", v).apply()

    val isLoggedIn: Boolean get() = !accessToken.isNullOrEmpty()

    /** Wipe everything tied to the user (logout / remote revoke). The device id stays. */
    override fun clear() {
        secure.edit().clear().apply()
        plain.edit().remove("ctx").remove("cursors").remove("last_sync").apply()
    }
}
