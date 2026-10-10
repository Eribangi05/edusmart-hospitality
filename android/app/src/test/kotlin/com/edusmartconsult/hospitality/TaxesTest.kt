package com.edusmartconsult.hospitality

import com.edusmartconsult.hospitality.core.CartLine
import com.edusmartconsult.hospitality.core.OrderMath
import com.edusmartconsult.hospitality.core.Rate
import com.edusmartconsult.hospitality.core.Taxes
import org.junit.Assert.assertEquals
import org.junit.Test

/** These expectations are the same numbers asserted by the backend's PHP tests, so offline totals match the server. */
class TaxesTest {
    private val vatExclusive = Rate("1", "vat", "VAT", 1800, "tax", false)
    private val vatInclusive = vatExclusive.copy(inclusive = true)

    @Test fun exclusiveVatIsAddedOnTop() {
        val r = Taxes.compute(1500, listOf(vatExclusive))
        assertEquals(1500, r.net); assertEquals(270, r.taxTotal); assertEquals(1770, r.total)
    }

    @Test fun inclusiveVatIsExtracted() {
        val r = Taxes.compute(3000, listOf(vatInclusive))
        assertEquals(2542, r.net); assertEquals(458, r.taxTotal); assertEquals(3000, r.total)
    }

    @Test fun zeroRatesAreIgnored() {
        assertEquals(1000, Taxes.compute(1000, listOf(vatExclusive.copy(rateBp = 0))).total)
    }

    @Test fun orderTotalsMatchServer() {
        val line = CartLine("a", "Brochette", 3000, 2.0, "kitchen", listOf(vatExclusive))
        val t = OrderMath.totals(listOf(line))
        assertEquals(6000, t.subtotal); assertEquals(1080, t.tax); assertEquals(7080, t.total)
    }

    @Test fun discountReducesNetBeforeTax() {
        val line = CartLine("a", "Brochette", 3000, 2.0, "kitchen", listOf(vatExclusive))
        val t = OrderMath.totals(listOf(line), discount = 600)
        assertEquals(5400, t.subtotal); assertEquals(972, t.tax); assertEquals(6372, t.total)
    }

    @Test fun serviceChargeAppliesToNetAndTipIsAdded() {
        val line = CartLine("a", "Beer", 1000, 1.0, "bar", listOf(vatExclusive))
        val t = OrderMath.totals(listOf(line), serviceBp = 1000, tip = 50)
        assertEquals(100, t.service); assertEquals(1000 + 180 + 100 + 50, t.total)
    }
}
