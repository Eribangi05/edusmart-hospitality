package com.edusmartconsult.hospitality

import com.edusmartconsult.hospitality.core.arr
import com.edusmartconsult.hospitality.core.asObject
import com.edusmartconsult.hospitality.core.obj
import com.edusmartconsult.hospitality.core.objs
import com.edusmartconsult.hospitality.core.newId
import com.edusmartconsult.hospitality.core.str
import com.edusmartconsult.hospitality.data.AuthStore
import com.edusmartconsult.hospitality.data.net.Api
import kotlinx.coroutines.runBlocking
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.buildJsonArray
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Assume.assumeTrue
import org.junit.Test

/**
 * Contract test: drives the real Api client against a running backend.
 *   LIVE_API=http://localhost:8000/api/v1/ ./gradlew :app:testDevDebugUnitTest --tests '*LiveBackendTest'
 * Skipped when LIVE_API is not set (CI uses the backend's own PHP tests for the same protocol).
 */
class LiveBackendTest {
    private class Store(override var baseUrl: String) : AuthStore {
        override val deviceId = newId()
        override var accessToken: String? = null
        override var refreshToken: String? = null
        override fun clear() { accessToken = null; refreshToken = null }
    }

    @Test fun registerLoginPushPullRoundTrip() = runBlocking {
        val base = System.getenv("LIVE_API")
        assumeTrue("LIVE_API not set", base != null)
        val store = Store(base!!)
        val api = Api(store)
        val email = "it-${System.currentTimeMillis()}@example.com"
        // create a company through the public API
        val reg = api.post("auth/register", buildJsonObject {
            put("company", "IT Lodge ${System.currentTimeMillis()}"); put("name", "Tester"); put("email", email); put("password", "correct-horse-battery")
            put("modules", buildJsonArray { add(kotlinx.serialization.json.JsonPrimitive("pos")); add(kotlinx.serialization.json.JsonPrimitive("rooms")) })
            put("device", buildJsonObject { put("id", store.deviceId); put("name", "JUnit"); put("platform", "android"); put("app_version", "test") })
        }).asObject()
        store.accessToken = reg.str("access_token"); store.refreshToken = reg.str("refresh_token")

        val ctx = api.get("me/context").asObject()
        assertTrue(ctx.arr("modules").map { it.toString() }.any { it.contains("pos") })

        val item = api.post("catalog-items", buildJsonObject { put("kind", "drink"); put("name", "Primus"); put("price", 1500) }).asObject().obj("data")!!
        val orderId = newId()
        val key = newId()
        val push = api.post("sync/push", buildJsonObject {
            put("device_id", store.deviceId)
            put("items", buildJsonArray {
                add(buildJsonObject {
                    put("id", orderId); put("entity", "order"); put("op", "create"); put("idempotency_key", key)
                    put("payload", buildJsonObject { put("id", orderId); put("number", "DTEST-0001"); put("items", buildJsonArray { add(buildJsonObject { put("catalog_item_id", item.str("id")!!); put("quantity", 2) }) }) })
                })
                add(buildJsonObject {
                    put("id", "p1"); put("entity", "order"); put("op", "update"); put("idempotency_key", newId())
                    put("payload", buildJsonObject { put("id", orderId); put("action", "pay"); put("amount", 3540); put("method_type", "cash") })
                })
            })
        }).asObject()
        assertEquals(listOf("applied", "applied"), push.objs("results").map { it.str("status") })
        // replay is a duplicate
        val again = api.post("sync/push", buildJsonObject { put("items", buildJsonArray { add(buildJsonObject { put("id", orderId); put("entity", "order"); put("op", "create"); put("idempotency_key", key); put("payload", buildJsonObject { put("id", orderId) }) }) }) }).asObject()
        assertEquals("duplicate", again.objs("results").first().str("status"))

        val pull = api.get("sync/pull", mapOf("entities" to "orders,order_items,payments,catalog_items")).asObject()
        val orders = pull.obj("changes")!!.arr("orders")
        assertEquals(1, orders.size)
        assertEquals("paid", (orders.first() as JsonObject).str("status"))
        assertEquals(1, pull.obj("changes")!!.arr("payments").size)
    }
}
