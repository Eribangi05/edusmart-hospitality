package com.edusmartconsult.hospitality.data

import com.edusmartconsult.hospitality.core.Rate
import com.edusmartconsult.hospitality.core.arr
import com.edusmartconsult.hospitality.core.int
import com.edusmartconsult.hospitality.core.obj
import com.edusmartconsult.hospitality.core.objs
import com.edusmartconsult.hospitality.core.str
import com.edusmartconsult.hospitality.core.strs
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.booleanOrNull
import kotlinx.serialization.json.longOrNull

/** What the server says this user may do: modules, permissions, taxes, payment methods, custom fields, settings. */
class AppContext(val raw: JsonObject) {
    val permissions: Set<String> = raw.strs("permissions").toSet()
    val modules: Set<String> = raw.strs("modules").toSet()
    val settings: JsonObject = raw.obj("settings") ?: JsonObject(emptyMap())
    val taxRates: List<Rate> = raw.objs("tax_rates").map { Rate.from(it) }
    val paymentMethods: List<JsonObject> = raw.objs("payment_methods")
    val customFields: List<JsonObject> = raw.objs("custom_fields")
    val properties: List<JsonObject> = raw.objs("properties")
    val tenantName: String = raw.obj("tenant")?.str("name") ?: ""
    val tenantStatus: String = raw.obj("tenant")?.str("status") ?: "active"
    val userName: String = raw.obj("user")?.str("name") ?: ""
    val userId: String = raw.obj("user")?.str("id") ?: ""
    val locale: String = raw.str("locale") ?: "en"

    fun can(permission: String): Boolean {
        if ("*" in permissions || permission in permissions) return true
        return permission.substringBefore('.') + ".*" in permissions
    }

    fun canAny(vararg perms: String) = perms.any { can(it) }
    fun hasModule(module: String) = module in modules
    fun has(module: String, vararg perms: String) = hasModule(module) && canAny(*perms)

    fun setting(key: String): JsonElement? = settings[key]
    fun settingInt(key: String, default: Int = 0): Int = (settings[key] as? JsonPrimitive)?.longOrNull?.toInt() ?: default
    fun settingBool(key: String, default: Boolean = false): Boolean = (settings[key] as? JsonPrimitive)?.booleanOrNull ?: default
    fun settingStr(key: String, default: String = ""): String = (settings[key] as? JsonPrimitive)?.takeIf { it !is JsonNull }?.content ?: default

    /** Per-company label overrides ("Guest" -> "Visitor"). */
    fun term(key: String, lang: String): String? = (settings["terminology"] as? JsonObject)?.obj(key)?.str(lang)

    fun customFieldsFor(entity: String): List<JsonObject> = customFields.filter { it.str("entity") == entity }
    fun serviceChargeBp(): Int = settingInt("pos.service_charge_bp", 0)

    companion object {
        fun parse(text: String?): AppContext? = text?.let { runCatching { AppContext(com.edusmartconsult.hospitality.core.AppJson.parseToJsonElement(it) as JsonObject) }.getOrNull() }
    }
}
