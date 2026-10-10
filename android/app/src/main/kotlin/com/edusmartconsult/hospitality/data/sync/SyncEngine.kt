package com.edusmartconsult.hospitality.data.sync

import com.edusmartconsult.hospitality.core.AppJson
import com.edusmartconsult.hospitality.core.arr
import com.edusmartconsult.hospitality.core.asObject
import com.edusmartconsult.hospitality.core.bool
import com.edusmartconsult.hospitality.core.obj
import com.edusmartconsult.hospitality.core.objs
import com.edusmartconsult.hospitality.core.str
import com.edusmartconsult.hospitality.data.Session
import com.edusmartconsult.hospitality.data.local.AppDb
import com.edusmartconsult.hospitality.data.local.DocEntity
import com.edusmartconsult.hospitality.data.net.Api
import com.edusmartconsult.hospitality.data.net.ApiException
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonArray
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

data class SyncStatus(val running: Boolean = false, val lastError: String? = null, val lastSyncAt: Long = 0)

/**
 * Push the outbox in creation order, then pull changes with per-entity cursors.
 * Records created offline carry client UUIDs, so the pulled server version replaces the local copy in place.
 */
class SyncEngine(private val api: Api, private val db: AppDb, private val session: Session) {
    private val lock = Mutex()
    private val _status = MutableStateFlow(SyncStatus(lastSyncAt = session.lastSync))
    val status = _status.asStateFlow()

    /** @return true when everything that could be synced was synced. */
    suspend fun syncNow(): Boolean = lock.withLock {
        _status.value = _status.value.copy(running = true)
        try {
            push()
            pull()
            session.lastSync = System.currentTimeMillis()
            _status.value = SyncStatus(false, null, session.lastSync)
            true
        } catch (e: ApiException) {
            _status.value = SyncStatus(false, if (e.isOffline) "offline" else e.message, session.lastSync)
            false
        } catch (e: Exception) {
            _status.value = SyncStatus(false, e.message ?: "error", session.lastSync)
            false
        }
    }

    private suspend fun push() {
        val outbox = db.outbox()
        while (true) {
            val batch = outbox.pending(50)
            if (batch.isEmpty()) return
            val body = buildJsonObject {
                put("device_id", session.deviceId)
                put("items", buildJsonArray {
                    batch.forEach { o ->
                        add(buildJsonObject {
                            put("id", o.itemId); put("entity", o.entity); put("op", o.op); put("idempotency_key", o.idempotencyKey)
                            put("payload", AppJson.parseToJsonElement(o.payload))
                        })
                    }
                })
            }
            val res = api.post("sync/push", body).asObject()
            val results = res.objs("results").associateBy { it.str("id") }
            val done = mutableListOf<Long>()
            for (o in batch) {
                val r = results[o.itemId]
                when (r?.str("status")) {
                    "applied", "duplicate" -> done += o.seq
                    "conflict", "rejected" -> outbox.fail(o.seq, listOfNotNull(r.str("reason"), r.str("message")).joinToString(": "))
                    else -> outbox.bump(listOf(o.seq))
                }
            }
            if (done.isNotEmpty()) outbox.delete(done)
            // a batch with no progress (all failed/unknown) must not loop forever
            if (done.isEmpty() && outbox.pending(1).firstOrNull()?.seq == batch.first().seq) return
        }
    }

    private suspend fun pull() {
        var more = true
        var pages = 0
        while (more && pages++ < 200) {
            val q = buildMap<String, String?> { put("limit", "500"); session.syncCursors?.let { put("cursors", it) } }
            val res = api.get("sync/pull", q).asObject()
            val changes = res.obj("changes") ?: JsonObject(emptyMap())
            for ((entity, rows) in changes) {
                val upserts = mutableListOf<DocEntity>()
                val deletes = mutableListOf<String>()
                for (row in (rows as? JsonArray).orEmpty().mapNotNull { it as? JsonObject }) {
                    val id = row.str("id") ?: continue
                    if (row.str("deleted_at") != null) deletes += id
                    else upserts += DocEntity(entity, id, AppJson.encodeToString(JsonObject.serializer(), row), row.str("updated_at") ?: "", pending = false)
                }
                if (upserts.isNotEmpty()) db.docs().upsert(upserts)
                if (deletes.isNotEmpty()) db.docs().delete(entity, deletes)
            }
            res.str("cursors")?.let { session.syncCursors = it }
            more = res.bool("has_more")
        }
    }
}

private fun JsonArray?.orEmpty(): List<kotlinx.serialization.json.JsonElement> = this?.toList() ?: emptyList()
