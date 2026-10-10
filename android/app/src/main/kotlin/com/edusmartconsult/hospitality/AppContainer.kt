package com.edusmartconsult.hospitality

import android.content.Context
import com.edusmartconsult.hospitality.data.Session
import com.edusmartconsult.hospitality.data.Store
import com.edusmartconsult.hospitality.data.local.AppDb
import com.edusmartconsult.hospitality.data.net.Api
import com.edusmartconsult.hospitality.data.net.SessionEvent
import com.edusmartconsult.hospitality.data.sync.SyncEngine
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.launch

/** Hand-wired dependency container: small, explicit and easy to test. */
class AppContainer(context: Context) {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)
    val session = Session(context)
    val api = Api(session)
    val db = AppDb.create(context)
    val sync = SyncEngine(api, db, session)
    val store = Store(context, api, db, session, sync)

    val loggedIn = MutableStateFlow(session.isLoggedIn)
    /** One-shot message for the login screen (e.g. device revoked). */
    val notice = MutableStateFlow<String?>(null)

    init {
        scope.launch {
            api.events.collect { e ->
                if (e is SessionEvent.LoggedOut) {
                    store.wipeLocal()
                    notice.value = e.reason
                    loggedIn.value = false
                }
            }
        }
    }

    suspend fun onLoggedIn() {
        loggedIn.value = true
        notice.value = null
        store.bootstrap()
    }

    suspend fun signOut() {
        api.logout()
        store.wipeLocal()
        loggedIn.value = false
    }
}
