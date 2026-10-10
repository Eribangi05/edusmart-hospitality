package com.edusmartconsult.hospitality.data

import android.content.Context
import com.edusmartconsult.hospitality.core.AppJson
import com.edusmartconsult.hospitality.core.CartLine
import com.edusmartconsult.hospitality.core.OrderMath
import com.edusmartconsult.hospitality.core.Taxes
import com.edusmartconsult.hospitality.core.asObject
import com.edusmartconsult.hospitality.core.int
import com.edusmartconsult.hospitality.core.long
import com.edusmartconsult.hospitality.core.newId
import com.edusmartconsult.hospitality.core.obj
import com.edusmartconsult.hospitality.core.objs
import com.edusmartconsult.hospitality.core.str
import com.edusmartconsult.hospitality.data.local.AppDb
import com.edusmartconsult.hospitality.data.local.DocEntity
import com.edusmartconsult.hospitality.data.local.OutboxEntity
import com.edusmartconsult.hospitality.data.net.Api
import com.edusmartconsult.hospitality.data.sync.SyncEngine
import com.edusmartconsult.hospitality.data.sync.SyncWorker
import java.time.Instant
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.map
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonArray
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

/**
 * The app's data hub. Reads come from the local database (so screens work offline);
 * offline-capable writes update local state at once and queue an outbox item for the sync engine.
 */
class Store(private val appContext: Context, val api: Api, val db: AppDb, val session: Session, val sync: SyncEngine) {
    private val _ctx = MutableStateFlow(AppContext.parse(session.contextJson))
    val ctx: StateFlow<AppContext?> = _ctx

    val pendingCount = db.outbox().observePendingCount()
    val failedCount = db.outbox().observeFailedCount()
    val outbox = db.outbox().observeAll()

    fun observe(entity: String): Flow<List<JsonObject>> = db.docs().observe(entity).map { rows ->
        rows.mapNotNull { runCatching { AppJson.parseToJsonElement(it.json) as? JsonObject }.getOrNull() }
    }

    suspend fun get(entity: String, id: String): JsonObject? = db.docs().get(entity, id)?.let { AppJson.parseToJsonElement(it.json).asObject() }
    suspend fun isPending(entity: String, id: String): Boolean = db.docs().get(entity, id)?.pending == true

    suspend fun refreshContext() {
        val res = api.get("me/context")
        session.contextJson = AppJson.encodeToString(JsonElement.serializer(), res)
        _ctx.value = AppContext.parse(session.contextJson)
    }

    /** After login: fetch the context, then sync everything this user may see. */
    suspend fun bootstrap(): Boolean {
        refreshContext()
        return sync.syncNow()
    }

    suspend fun wipeLocal() {
        db.docs().clear(); db.outbox().clear(); db.kv().clear()
        _ctx.value = null
    }

    private suspend fun queue(entity: String, op: String, payload: JsonObject, itemId: String = newId(), key: String = newId()) {
        db.outbox().insert(OutboxEntity(itemId = itemId, entity = entity, op = op, payload = AppJson.encodeToString(JsonObject.serializer(), payload), idempotencyKey = key, createdAt = System.currentTimeMillis()))
        SyncWorker.kick(appContext)
    }

    private fun now(): String = Instant.now().toString()

    private suspend fun putDoc(entity: String, o: JsonObject, pending: Boolean = true) {
        db.docs().upsert(DocEntity(entity, o.str("id")!!, AppJson.encodeToString(JsonObject.serializer(), o), o.str("updated_at") ?: now(), pending))
    }

    private fun nextOrderNumber(): String {
        val n = session.orderSeq + 1
        session.orderSeq = n
        // device prefix keeps offline numbers unique across tills
        return "D${session.deviceId.take(4).uppercase()}-${n.toString().padStart(4, '0')}"
    }

    // ------------------------------------------------------------------ POS (offline-capable)
    suspend fun createOrder(lines: List<CartLine>, type: String, tableId: String?, customer: String?, sendNow: Boolean, propertyId: String? = null): String {
        val c = _ctx.value
        val id = newId()
        val number = nextOrderNumber()
        val t = OrderMath.totals(lines, 0, c?.serviceChargeBp() ?: 0)
        val status = if (sendNow) "sent" else "open"
        val orderDoc = buildJsonObject {
            put("id", id); put("number", number); put("type", type); put("status", status); put("created_at", now()); put("updated_at", now())
            tableId?.let { put("table_id", it) }; customer?.let { put("customer_name", it) }; propertyId?.let { put("property_id", it) }
            put("subtotal", t.subtotal); put("tax", t.tax); put("service", t.service); put("discount", 0); put("tip", 0); put("total", t.total); put("paid", 0)
        }
        putDoc("orders", orderDoc)
        val itemIds = lines.map { newId() }
        lines.forEachIndexed { i, l ->
            putDoc("order_items", buildJsonObject {
                put("id", itemIds[i]); put("order_id", id); put("catalog_item_id", l.itemId); put("name", l.name); put("quantity", l.quantity); put("unit_price", l.unitPrice)
                put("line_total", l.lineTotal); put("station", l.station); put("status", if (sendNow) (if (l.station == "none") "served" else "sent") else "new"); put("notes", l.notes); put("created_at", now()); put("updated_at", now())
            })
        }
        queue("order", "create", buildJsonObject {
            put("id", id); put("number", number); put("type", type)
            tableId?.let { put("table_id", it) }; customer?.let { put("customer_name", it) }; propertyId?.let { put("property_id", it) }
            put("items", buildJsonArray {
                lines.forEachIndexed { i, l -> add(buildJsonObject { put("id", itemIds[i]); put("catalog_item_id", l.itemId); put("quantity", l.quantity); if (l.notes.isNotBlank()) put("notes", l.notes) }) }
            })
        }, itemId = id)
        if (sendNow) queue("order", "update", buildJsonObject { put("id", id); put("action", "send") })
        return id
    }

    suspend fun sendOrder(orderId: String) {
        val o = get("orders", orderId) ?: return
        putDoc("orders", JsonObject(o + ("status" to JsonPrimitive("sent"))))
        db.docs().all("order_items").map { AppJson.parseToJsonElement(it.json).asObject() }.filter { it.str("order_id") == orderId && it.str("status") == "new" }.forEach {
            putDoc("order_items", JsonObject(it + ("status" to JsonPrimitive(if (it.str("station") == "none") "served" else "sent"))))
        }
        queue("order", "update", buildJsonObject { put("id", orderId); put("action", "send") })
    }

    /** Cash is settled instantly and works offline; the server re-checks everything on sync. */
    suspend fun payCash(orderId: String, amount: Long) {
        val o = get("orders", orderId) ?: return
        val paid = o.long("paid") + amount
        val total = o.long("total")
        val key = newId()
        putDoc("orders", JsonObject(o + mapOf("paid" to JsonPrimitive(paid)) + (if (paid >= total) mapOf("status" to JsonPrimitive("paid"), "closed_at" to JsonPrimitive(now())) else emptyMap())))
        putDoc("payments", buildJsonObject { put("id", newId()); put("order_id", orderId); put("method_type", "cash"); put("amount", amount); put("status", "successful"); put("paid_at", now()); put("updated_at", now()) })
        queue("order", "update", buildJsonObject { put("id", orderId); put("action", "pay"); put("amount", amount); put("method_type", "cash"); put("idempotency_key", key) }, key = key)
    }

    // ------------------------------------------------------------------ housekeeping / staff
    suspend fun taskAction(taskId: String, action: String) {
        val t = get("work_tasks", taskId) ?: return
        val status = when (action) { "start" -> "in_progress"; "complete" -> "done"; "inspect" -> "inspected"; else -> t.str("status") ?: "pending" }
        putDoc("work_tasks", JsonObject(t + ("status" to JsonPrimitive(status))))
        queue("task", "update", buildJsonObject { put("id", taskId); put("action", action) })
    }

    suspend fun clock(type: String) {
        val id = newId()
        db.kv().put(com.edusmartconsult.hospitality.data.local.KvEntity("last_clock", "$type|${now()}"))
        queue("attendance", "create", buildJsonObject { put("id", id); put("type", type); put("at", now()); put("device_id", session.deviceId) }, itemId = id)
    }

    suspend fun lastClock(): Pair<String, String>? = db.kv().get("last_clock")?.split("|", limit = 2)?.takeIf { it.size == 2 }?.let { it[0] to it[1] }

    // ------------------------------------------------------------------ failed outbox items
    suspend fun retry(seq: Long) { db.outbox().retry(seq); SyncWorker.kick(appContext) }

    /** Drop a rejected item and the optimistic local record it created. */
    suspend fun discard(item: OutboxEntity) {
        db.outbox().delete(listOf(item.seq))
        if (item.entity == "order" && item.op == "create") {
            db.docs().delete("orders", listOf(item.itemId))
            db.docs().all("order_items").filter { AppJson.parseToJsonElement(it.json).asObject().str("order_id") == item.itemId }.let { rows -> db.docs().delete("order_items", rows.map { it.id }) }
        }
    }

    // helpers for screens
    fun term(key: String, lang: String): String? = _ctx.value?.term(key, lang)
}
