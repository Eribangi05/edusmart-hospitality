package com.edusmartconsult.hospitality.print

import android.annotation.SuppressLint
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothManager
import android.content.Context
import com.edusmartconsult.hospitality.core.arr
import com.edusmartconsult.hospitality.core.long
import com.edusmartconsult.hospitality.core.money
import com.edusmartconsult.hospitality.core.str
import java.io.ByteArrayOutputStream
import java.util.UUID
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.JsonObject

/** Minimal ESC/POS: 58/80 mm thermal printers over Bluetooth SPP. Receipts are plain text, 32 columns. */
object EscPos {
    private const val WIDTH = 32
    private val INIT = byteArrayOf(0x1B, 0x40)
    private val CUT = byteArrayOf(0x1D, 0x56, 0x42, 0x00)
    private val CENTER = byteArrayOf(0x1B, 0x61, 0x01)
    private val LEFT = byteArrayOf(0x1B, 0x61, 0x00)
    private val BOLD_ON = byteArrayOf(0x1B, 0x45, 0x01)
    private val BOLD_OFF = byteArrayOf(0x1B, 0x45, 0x00)

    fun line(left: String, right: String): String {
        val space = (WIDTH - left.length - right.length).coerceAtLeast(1)
        return (left.take(WIDTH - right.length - 1) + " ".repeat(space) + right).take(WIDTH + 8)
    }

    fun receipt(business: String, footer: String, order: JsonObject, items: List<JsonObject>, payments: List<JsonObject>, whenText: String): ByteArray {
        val out = ByteArrayOutputStream()
        fun text(s: String) = out.write(("$s\n").toByteArray(Charsets.ISO_8859_1))
        out.write(INIT); out.write(CENTER); out.write(BOLD_ON); text(business); out.write(BOLD_OFF)
        text("Receipt ${order.str("number") ?: ""}"); text(whenText); out.write(LEFT); text("-".repeat(WIDTH))
        items.filter { it.str("voided_at") == null && it.str("status") != "void" }.forEach {
            val qty = it.str("quantity")?.toDoubleOrNull() ?: 1.0
            text(line("${if (qty % 1.0 == 0.0) qty.toLong() else qty} x ${it.str("name") ?: ""}", money(it.long("line_total"))))
        }
        text("-".repeat(WIDTH))
        text(line("Subtotal", money(order.long("subtotal")))); text(line("Tax", money(order.long("tax"))))
        if (order.long("service") > 0) text(line("Service", money(order.long("service"))))
        if (order.long("discount") > 0) text(line("Discount", "-" + money(order.long("discount"))))
        out.write(BOLD_ON); text(line("TOTAL", money(order.long("total")))); out.write(BOLD_OFF)
        payments.filter { it.str("status") == "successful" }.forEach { text(line(it.str("method_type")?.replace('_', ' ')?.replaceFirstChar(Char::uppercase) ?: "Paid", money(it.long("amount")))) }
        out.write(CENTER); text(""); text(footer); text(""); text(""); text(""); out.write(CUT)
        return out.toByteArray()
    }

    @SuppressLint("MissingPermission")
    suspend fun send(context: Context, address: String, data: ByteArray) = withContext(Dispatchers.IO) {
        val adapter: BluetoothAdapter = (context.getSystemService(Context.BLUETOOTH_SERVICE) as BluetoothManager).adapter ?: error("Bluetooth not available")
        val device = adapter.getRemoteDevice(address)
        val socket = device.createRfcommSocketToServiceRecord(UUID.fromString("00001101-0000-1000-8000-00805F9B34FB"))
        adapter.cancelDiscovery()
        socket.use {
            it.connect()
            it.outputStream.write(data)
            it.outputStream.flush()
            Thread.sleep(300)
        }
    }

    @SuppressLint("MissingPermission")
    fun pairedPrinters(context: Context): List<Pair<String, String>> {
        val adapter = (context.getSystemService(Context.BLUETOOTH_SERVICE) as BluetoothManager).adapter ?: return emptyList()
        return runCatching { adapter.bondedDevices.map { it.address to (it.name ?: it.address) } }.getOrDefault(emptyList())
    }
}
