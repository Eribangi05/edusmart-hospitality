package com.edusmartconsult.hospitality.core

import java.security.SecureRandom
import java.text.NumberFormat
import java.util.Locale
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.booleanOrNull
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.doubleOrNull
import kotlinx.serialization.json.longOrNull
import kotlinx.serialization.json.put

val AppJson = Json {
    ignoreUnknownKeys = true
    explicitNulls = false
    encodeDefaults = true
}

/** The server is schema-flexible (custom fields, per-tenant options), so the app reads records as JSON objects. */
fun JsonObject.str(key: String): String? = (this[key] as? JsonPrimitive)?.takeIf { it !is JsonNull }?.contentOrNull
fun JsonObject.long(key: String, default: Long = 0): Long =
    (this[key] as? JsonPrimitive)?.let { it.longOrNull ?: it.doubleOrNull?.toLong() } ?: default
fun JsonObject.int(key: String, default: Int = 0): Int = long(key, default.toLong()).toInt()
fun JsonObject.dbl(key: String, default: Double = 0.0): Double = (this[key] as? JsonPrimitive)?.doubleOrNull ?: default
fun JsonObject.bool(key: String, default: Boolean = false): Boolean =
    (this[key] as? JsonPrimitive)?.let { it.booleanOrNull ?: (it.longOrNull?.let { n -> n != 0L }) } ?: default
fun JsonObject.obj(key: String): JsonObject? = this[key] as? JsonObject
fun JsonObject.arr(key: String): JsonArray = this[key] as? JsonArray ?: JsonArray(emptyList())
fun JsonObject.objs(key: String): List<JsonObject> = arr(key).mapNotNull { it as? JsonObject }
fun JsonObject.strs(key: String): List<String> = arr(key).mapNotNull { (it as? JsonPrimitive)?.contentOrNull }
fun JsonElement?.asObject(): JsonObject = this as? JsonObject ?: JsonObject(emptyMap())

fun obj(block: kotlinx.serialization.json.JsonObjectBuilder.() -> Unit): JsonObject = buildJsonObject(block)

/** Money is whole RWF (no minor unit). */
private val numberFormat = NumberFormat.getIntegerInstance(Locale.US)
fun money(amount: Long): String = "RWF " + numberFormat.format(amount)
fun money(amount: Int): String = money(amount.toLong())

/** UUIDv7: time-ordered ids so offline-created records sort and index well, with no collisions across devices. */
private val rng = SecureRandom()
fun newId(now: Long = System.currentTimeMillis()): String {
    val b = ByteArray(16)
    rng.nextBytes(b)
    for (i in 0 until 6) b[i] = ((now shr (8 * (5 - i))) and 0xff).toByte()
    b[6] = ((b[6].toInt() and 0x0f) or 0x70).toByte()
    b[8] = ((b[8].toInt() and 0x3f) or 0x80).toByte()
    val h = b.joinToString("") { "%02x".format(it) }
    return "${h.substring(0, 8)}-${h.substring(8, 12)}-${h.substring(12, 16)}-${h.substring(16, 20)}-${h.substring(20)}"
}

fun String.shortId(): String = take(8)
