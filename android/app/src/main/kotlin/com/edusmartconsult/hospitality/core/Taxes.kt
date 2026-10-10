package com.edusmartconsult.hospitality.core

import kotlin.math.roundToLong
import kotlinx.serialization.json.JsonObject

/** Mirrors the server's tax engine so totals shown offline match what the server will compute. */
data class Rate(val id: String, val key: String, val name: String, val rateBp: Int, val kind: String, val inclusive: Boolean) {
    companion object {
        fun from(o: JsonObject) = Rate(o.str("id") ?: "", o.str("key") ?: "", o.str("name") ?: "", o.int("rate_bp"), o.str("kind") ?: "tax", o.bool("inclusive"))
    }
}

data class TaxResult(val net: Long, val taxTotal: Long) {
    val total: Long get() = net + taxTotal
}

object Taxes {
    /** Inclusive rates are extracted from the listed price; exclusive rates are added on the net. */
    fun compute(listed: Long, rates: List<Rate>): TaxResult {
        val active = rates.filter { it.rateBp > 0 }
        val inclBp = active.filter { it.inclusive }.sumOf { it.rateBp }
        val net = if (inclBp > 0) (listed * 10000.0 / (10000 + inclBp)).roundToLong() else listed
        val incl = listed - net
        val add = active.filter { !it.inclusive }.sumOf { (net * it.rateBp / 10000.0).roundToLong() }
        return TaxResult(net, incl + add)
    }

    /** Rates for a catalog item: explicit list if set, otherwise every active "tax" rate. */
    fun ratesFor(item: JsonObject, all: List<Rate>): List<Rate> {
        val ids = item.strs("tax_rate_ids")
        return if (item["tax_rate_ids"] == null || item["tax_rate_ids"] is kotlinx.serialization.json.JsonNull) all.filter { it.kind == "tax" } else all.filter { it.id in ids }
    }
}

data class CartLine(val itemId: String, val name: String, val unitPrice: Long, val quantity: Double, val station: String, val rates: List<Rate>, val notes: String = "") {
    val lineTotal: Long get() = (unitPrice * quantity).roundToLong()
}

data class OrderTotals(val subtotal: Long, val tax: Long, val service: Long, val discount: Long, val tip: Long) {
    val total: Long get() = subtotal + tax + service + tip
}

object OrderMath {
    /** Discount reduces the net before tax; service charge applies to the discounted net. */
    fun totals(lines: List<CartLine>, discount: Long = 0, serviceBp: Int = 0, tip: Long = 0): OrderTotals {
        val gross = lines.sumOf { it.lineTotal }
        val disc = minOf(discount, gross)
        var subtotal = 0L
        var tax = 0L
        for (l in lines) {
            val share = if (gross > 0) (disc * l.lineTotal.toDouble() / gross).roundToLong() else 0L
            val c = Taxes.compute(l.lineTotal - share, l.rates)
            subtotal += c.net
            tax += c.taxTotal
        }
        val service = (subtotal * serviceBp / 10000.0).roundToLong()
        return OrderTotals(subtotal, tax, service, disc, tip)
    }
}
