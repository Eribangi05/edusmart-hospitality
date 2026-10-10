package com.edusmartconsult.hospitality

import com.edusmartconsult.hospitality.core.AppJson
import com.edusmartconsult.hospitality.data.AppContext
import kotlinx.serialization.json.JsonObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class AppContextTest {
    private fun ctx(json: String) = AppContext(AppJson.parseToJsonElement(json) as JsonObject)

    @Test fun permissionsSupportWildcards() {
        assertTrue(ctx("""{"permissions":["*"]}""").can("anything.at_all"))
        val c = ctx("""{"permissions":["pos.view","rooms.*"],"modules":["pos","rooms"]}""")
        assertTrue(c.can("pos.view")); assertFalse(c.can("pos.void")); assertTrue(c.can("rooms.check_in"))
        assertTrue(c.has("pos", "pos.view")); assertFalse(c.has("tourism", "tourism.view"))
    }

    @Test fun settingsTerminologyAndCustomFields() {
        val c = ctx("""{"permissions":[],"settings":{"pos.service_charge_bp":500,"terminology":{"guest":{"en":"Visitor"}},"rooms.require_id_on_checkin":false},
            "custom_fields":[{"entity":"booking","key":"diet"},{"entity":"guest","key":"passport"}],
            "tax_rates":[{"id":"1","key":"vat","name":"VAT","rate_bp":1800,"kind":"tax","inclusive":false}]}""")
        assertEquals(500, c.serviceChargeBp())
        assertEquals("Visitor", c.term("guest", "en")); assertNull(c.term("guest", "fr"))
        assertFalse(c.settingBool("rooms.require_id_on_checkin", true))
        assertEquals(1, c.customFieldsFor("booking").size)
        assertEquals(1800, c.taxRates.first().rateBp)
    }
}
