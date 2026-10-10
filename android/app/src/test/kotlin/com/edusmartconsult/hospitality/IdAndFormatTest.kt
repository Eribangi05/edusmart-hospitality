package com.edusmartconsult.hospitality

import com.edusmartconsult.hospitality.core.money
import com.edusmartconsult.hospitality.core.newId
import com.edusmartconsult.hospitality.print.EscPos
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class IdAndFormatTest {
    @Test fun idsAreVersion7AndTimeOrdered() {
        val a = newId(1_700_000_000_000)
        val b = newId(1_700_000_000_500)
        assertEquals('7', a[14]); assertTrue(a[19] in "89ab")
        assertTrue(a < b)
        assertEquals(200, (1..200).map { newId() }.toSet().size)
    }

    @Test fun moneyUsesWholeFrancsWithSeparators() {
        assertEquals("RWF 1,234,500", money(1_234_500L))
        assertEquals("RWF 0", money(0))
    }

    @Test fun receiptLinesAlignToPrinterWidth() {
        val l = EscPos.line("2 x Primus", "RWF 3,000")
        assertEquals(32, l.length)
        assertTrue(l.startsWith("2 x Primus")); assertTrue(l.endsWith("RWF 3,000"))
    }
}

class BaseUrlTest {
    @org.junit.Test fun normalizesServerAddresses() {
        org.junit.Assert.assertEquals("https://h.example.com/api/v1/", com.edusmartconsult.hospitality.data.normalizeBaseUrl("h.example.com"))
        org.junit.Assert.assertEquals("https://h.example.com/api/v1/", com.edusmartconsult.hospitality.data.normalizeBaseUrl(" https://h.example.com/ "))
        org.junit.Assert.assertEquals("https://h.example.com/api/v1/", com.edusmartconsult.hospitality.data.normalizeBaseUrl("https://h.example.com/api/v1"))
    }
}
