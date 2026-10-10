package com.edusmartconsult.hospitality.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.AssistChip
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.edusmartconsult.hospitality.R
import com.edusmartconsult.hospitality.core.CartLine
import com.edusmartconsult.hospitality.core.OrderMath
import com.edusmartconsult.hospitality.core.Taxes
import com.edusmartconsult.hospitality.core.asObject
import com.edusmartconsult.hospitality.core.bool
import com.edusmartconsult.hospitality.core.dbl
import com.edusmartconsult.hospitality.core.long
import com.edusmartconsult.hospitality.core.money
import com.edusmartconsult.hospitality.core.obj
import com.edusmartconsult.hospitality.core.objs
import com.edusmartconsult.hospitality.core.str
import com.edusmartconsult.hospitality.data.net.ApiException
import com.edusmartconsult.hospitality.print.EscPos
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.put

private val SELLABLE = setOf("menu", "drink", "product", "service", "other")

@OptIn(ExperimentalMaterial3Api::class, androidx.compose.foundation.layout.ExperimentalLayoutApi::class)
@Composable
fun PosScreen(onOrders: () -> Unit, onOpen: (String) -> Unit) {
    val container = LocalContainer.current
    val store = container.store
    val c = ctx()
    val scope = rememberCoroutineScope()
    val context = LocalContext.current
    val snack = LocalSnack.current
    val items by rememberDocs("catalog_items") { l -> l.filter { it.bool("is_active", true) && it.str("kind") in SELLABLE }.sortedBy { it.str("name") } }
    val cats by rememberDocs("catalog_categories") { l -> l.filter { it.bool("is_active", true) }.sortedBy { it.long("position") } }
    val tables by rememberDocs("dining_tables") { l -> l.filter { it.bool("is_active", true) }.sortedBy { it.str("name") } }
    val cart = remember { mutableStateListOf<CartLine>() }
    var cat by remember { mutableStateOf<String?>(null) }
    var query by remember { mutableStateOf("") }
    var type by remember { mutableStateOf("dine_in") }
    var tableId by remember { mutableStateOf<String?>(null) }
    var customer by remember { mutableStateOf("") }
    var showCart by remember { mutableStateOf(false) }
    var busy by remember { mutableStateOf(false) }
    val rates = c?.taxRates ?: emptyList()
    val shown = items.filter { (cat == null || it.str("category_id") == cat) && (query.isBlank() || it.str("name").orEmpty().contains(query, true) || it.str("barcode") == query) }
    val totals = OrderMath.totals(cart.toList(), 0, c?.serviceChargeBp() ?: 0)

    fun add(item: JsonObject) {
        val idx = cart.indexOfFirst { it.itemId == item.str("id") }
        if (idx >= 0) cart[idx] = cart[idx].copy(quantity = cart[idx].quantity + 1)
        else cart.add(CartLine(item.str("id")!!, item.str("name") ?: "", item.long("price"), 1.0, item.str("station") ?: "none", Taxes.ratesFor(item, rates)))
    }

    fun submit(send: Boolean) {
        if (cart.isEmpty() || busy) return
        busy = true
        scope.launch {
            val id = store.createOrder(cart.toList(), type, tableId, customer.ifBlank { null }, send, c?.properties?.firstOrNull()?.str("id"))
            cart.clear(); customer = ""; tableId = null; showCart = false; busy = false
            onOpen(id)
        }
    }

    val cartPanel: @Composable () -> Unit = {
        Column(Modifier.fillMaxWidth().padding(16.dp).verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Text(stringResource(R.string.cart), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
            Picker(stringResource(R.string.order_type), listOf("dine_in" to stringResource(R.string.type_dine_in), "takeaway" to stringResource(R.string.type_takeaway), "room_service" to stringResource(R.string.type_room_service), "retail" to stringResource(R.string.type_retail)), type, { type = it })
            if (tables.isNotEmpty()) Picker(stringResource(R.string.table), listOf("" to stringResource(R.string.no_table)) + tables.map { it.str("id")!! to (it.str("name") ?: "") }, tableId ?: "", { tableId = it.ifBlank { null } })
            Field(customer, { customer = it }, stringResource(R.string.customer_optional))
            if (cart.isEmpty()) Empty(stringResource(R.string.cart_empty))
            cart.forEachIndexed { i, l ->
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = androidx.compose.ui.Alignment.CenterVertically) {
                    Column(Modifier.weight(1f)) { Text(l.name); Text(money(l.lineTotal), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
                    OutlinedButton(onClick = { if (l.quantity <= 1) cart.removeAt(i) else cart[i] = l.copy(quantity = l.quantity - 1) }, contentPadding = PaddingValues(horizontal = 12.dp)) { Text("−") }
                    Text(l.quantity.toLong().toString(), Modifier.padding(horizontal = 10.dp), fontWeight = FontWeight.Bold)
                    OutlinedButton(onClick = { cart[i] = l.copy(quantity = l.quantity + 1) }, contentPadding = PaddingValues(horizontal = 12.dp)) { Text("+") }
                }
            }
            HorizontalDivider()
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text(stringResource(R.string.tax)); Text(money(totals.tax)) }
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text(stringResource(R.string.total), fontWeight = FontWeight.Bold); Text(money(totals.total), fontWeight = FontWeight.Bold) }
            Text(stringResource(R.string.estimate), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Row2 {
                OutlinedButton(onClick = { submit(false) }, enabled = cart.isNotEmpty() && !busy, modifier = Modifier.weight(1f)) { Text(stringResource(R.string.save_order)) }
                PrimaryButton(stringResource(R.string.send_order), { submit(true) }, Modifier.weight(1f), enabled = cart.isNotEmpty() && !busy)
            }
        }
    }

    BoxWithConstraints(Modifier.fillMaxSize()) {
        val wide = maxWidth >= 720.dp
        Row(Modifier.fillMaxSize()) {
            Column(Modifier.weight(1f)) {
                Row(Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 8.dp), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = androidx.compose.ui.Alignment.CenterVertically) {
                    Field(query, { query = it }, stringResource(R.string.search), Modifier.weight(1f))
                    IconButton(onClick = { scanBarcode(context, { code -> val hit = items.firstOrNull { it.str("barcode") == code }; if (hit != null) add(hit) else query = code }, { scope.launch { snack.showSnackbar(it) } }) }) {
                        Icon(Icons.Filled.QrCodeScanner, stringResource(R.string.scan_barcode))
                    }
                    TextButton(onClick = onOrders) { Text(stringResource(R.string.orders_title)) }
                }
                if (cats.isNotEmpty()) {
                    Row(Modifier.fillMaxWidth().padding(horizontal = 12.dp).verticalScroll(rememberScrollState(), enabled = false), horizontalArrangement = Arrangement.spacedBy(8.dp)) {}
                    androidx.compose.foundation.lazy.LazyRow(contentPadding = PaddingValues(horizontal = 12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        item { FilterChip(selected = cat == null, onClick = { cat = null }, label = { Text("All") }) }
                        items(cats) { k -> FilterChip(selected = cat == k.str("id"), onClick = { cat = k.str("id") }, label = { Text(k.str("name") ?: "") }) }
                    }
                }
                if (shown.isEmpty()) Empty(stringResource(R.string.menu_empty))
                LazyVerticalGrid(GridCells.Adaptive(140.dp), contentPadding = PaddingValues(12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.weight(1f)) {
                    items(shown, key = { it.str("id") ?: "" }) { i ->
                        ListCard(onClick = { add(i) }) {
                            Text(i.str("name") ?: "", fontWeight = FontWeight.Medium, maxLines = 2)
                            Text(money(i.long("price")), color = MaterialTheme.colorScheme.primary)
                        }
                    }
                }
                if (!wide && cart.isNotEmpty()) {
                    PrimaryButton("${stringResource(R.string.cart)} · ${cart.sumOf { it.quantity.toInt() }} · ${money(totals.total)}", { showCart = true }, Modifier.fillMaxWidth().padding(12.dp))
                }
            }
            if (wide) Column(Modifier.width(380.dp).fillMaxSize()) { cartPanel() }
        }
    }
    if (showCart && !(false)) ModalBottomSheet(onDismissRequest = { showCart = false }) { cartPanel() }
}

/** Cash shift (float / cash-up). Needs a connection: the server owns the shift record. */
@Composable
fun ShiftCard() {
    val container = LocalContainer.current
    val c = ctx()
    val scope = rememberCoroutineScope()
    var shift by remember { mutableStateOf<JsonObject?>(null) }
    var loaded by remember { mutableStateOf(false) }
    var dialog by remember { mutableStateOf<String?>(null) }
    var amount by remember { mutableStateOf("") }
    var message by remember { mutableStateOf<String?>(null) }
    val offline = stringResource(R.string.offline_required)
    suspend fun load() { runCatching { container.api.get("shifts/current").asObject().obj("data") }.onSuccess { shift = it; loaded = true } }
    LaunchedEffect(Unit) { load() }
    if (!loaded || c?.can("pos.create") != true) return
    ListCard(modifier = Modifier.padding(12.dp)) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = androidx.compose.ui.Alignment.CenterVertically) {
            Text(if (shift == null) stringResource(R.string.no_shift) else stringResource(R.string.shift) + " · " + money(shift!!.long("opening_float")), fontWeight = FontWeight.SemiBold)
            if (shift == null) OutlinedButton(onClick = { amount = ""; dialog = "open" }) { Text(stringResource(R.string.open_shift)) }
            else OutlinedButton(onClick = { amount = ""; dialog = "close" }) { Text(stringResource(R.string.close_shift)) }
        }
        message?.let { Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall) }
    }
    dialog?.let { kind ->
        AlertDialog(onDismissRequest = { dialog = null },
            title = { Text(if (kind == "open") stringResource(R.string.open_shift) else stringResource(R.string.close_shift)) },
            text = { Field(amount, { amount = it.filter(Char::isDigit) }, stringResource(if (kind == "open") R.string.opening_float else R.string.counted_cash), number = true) },
            confirmButton = { TextButton(enabled = amount.isNotBlank(), onClick = {
                scope.launch {
                    try {
                        if (kind == "open") container.api.post("shifts", com.edusmartconsult.hospitality.core.obj { put("opening_float", amount.toLong()); put("device_id", container.session.deviceId) })
                        else container.api.post("shifts/${shift!!.str("id")}/close", com.edusmartconsult.hospitality.core.obj { put("counted_cash", amount.toLong()) })
                        message = null; dialog = null; load()
                    } catch (e: ApiException) { message = if (e.isOffline) offline else e.message; dialog = null }
                }
            }) { Text(stringResource(R.string.confirm_ok)) } },
            dismissButton = { TextButton(onClick = { dialog = null }) { Text(stringResource(R.string.cancel)) } })
    }
}

@Composable
fun OrdersScreen(onOpen: (String) -> Unit) {
    val orders by rememberDocs("orders") { l -> l.sortedByDescending { it.str("created_at") ?: "" } }
    var filter by remember { mutableStateOf("open") }
    val shown = orders.filter { when (filter) { "open" -> it.str("status") in setOf("open", "sent"); "paid" -> it.str("status") == "paid"; else -> true } }
    Column(Modifier.fillMaxSize()) {
        ShiftCard()
        Row(Modifier.padding(12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            listOf("open" to R.string.status_open, "paid" to R.string.status_paid, "all" to R.string.no_table).forEach { (k, res) ->
                FilterChip(selected = filter == k, onClick = { filter = k }, label = { Text(if (k == "all") "All" else stringResource(res)) })
            }
        }
        if (shown.isEmpty()) Empty()
        LazyColumn(contentPadding = PaddingValues(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            items(shown, key = { it.str("id") ?: "" }) { o ->
                ListCard(onClick = { onOpen(o.str("id")!!) }) {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text(o.str("number") ?: "", fontWeight = FontWeight.SemiBold); Text(money(o.long("total"))) }
                    Text(orderStatus(o.str("status")), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
    }
}

@Composable
fun orderStatus(s: String?): String = when (s) { "open" -> stringResource(R.string.status_open); "sent" -> stringResource(R.string.status_sent); "paid" -> stringResource(R.string.status_paid); "void" -> stringResource(R.string.status_void); else -> s ?: "" }

@Composable
fun OrderDetailScreen(id: String) {
    val container = LocalContainer.current
    val store = container.store
    val c = ctx()
    val scope = rememberCoroutineScope()
    val snack = LocalSnack.current
    val context = LocalContext.current
    val order by rememberDocs("orders") { l -> l.filter { it.str("id") == id } }
    val items by rememberDocs("order_items") { l -> l.filter { it.str("order_id") == id }.sortedBy { it.str("created_at") } }
    val payments by rememberDocs("payments") { l -> l.filter { it.str("order_id") == id } }
    val o = order.firstOrNull()
    var amount by remember(o?.long("paid")) { mutableStateOf(((o?.long("total") ?: 0) - (o?.long("paid") ?: 0)).coerceAtLeast(0).toString()) }
    var momoPhone by remember { mutableStateOf("") }
    var momoPaymentId by remember { mutableStateOf<String?>(null) }
    var message by remember { mutableStateOf<String?>(null) }
    var voidDialog by remember { mutableStateOf<JsonObject?>(null) }
    var promo by remember { mutableStateOf("") }
    val promoOk = stringResource(R.string.promo_applied)
    val waiting = stringResource(R.string.momo_waiting)
    val failedMsg = stringResource(R.string.momo_failed)
    val notSynced = stringResource(R.string.order_not_synced)
    val offline = stringResource(R.string.offline_required)
    val printerMissing = stringResource(R.string.printer_not_set)
    val printedMsg = stringResource(R.string.printed)

    if (o == null) { Loading(); return }
    val status = o.str("status")
    val balance = (o.long("total") - o.long("paid")).coerceAtLeast(0)
    val open = status == "open" || status == "sent"

    // poll a pending mobile-money request until the customer approves or declines
    LaunchedEffect(momoPaymentId) {
        val pid = momoPaymentId ?: return@LaunchedEffect
        repeat(30) {
            delay(4000)
            val p = runCatching { container.api.get("payments/$pid").asObject().obj("data") }.getOrNull()
            when (p?.str("status")) {
                "successful" -> { message = null; momoPaymentId = null; container.sync.syncNow(); return@LaunchedEffect }
                "failed" -> { message = failedMsg; momoPaymentId = null; return@LaunchedEffect }
            }
        }
    }

    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
            Text(o.str("number") ?: "", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
            Text(orderStatus(status), color = MaterialTheme.colorScheme.primary, fontWeight = FontWeight.SemiBold)
        }
        items.forEach { i ->
            val voided = i.str("status") == "void"
            Row(Modifier.fillMaxWidth().clickable(enabled = open && !voided) { voidDialog = i }, horizontalArrangement = Arrangement.SpaceBetween) {
                Text("${i.dbl("quantity").let { q -> if (q % 1.0 == 0.0) q.toLong().toString() else q.toString() }} × ${i.str("name")}" + if (voided) " (void)" else "", color = if (voided) MaterialTheme.colorScheme.outline else MaterialTheme.colorScheme.onSurface)
                Text(money(i.long("line_total")))
            }
        }
        HorizontalDivider()
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text(stringResource(R.string.subtotal)); Text(money(o.long("subtotal"))) }
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text(stringResource(R.string.tax)); Text(money(o.long("tax"))) }
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text(stringResource(R.string.total), fontWeight = FontWeight.Bold); Text(money(o.long("total")), fontWeight = FontWeight.Bold) }
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text(stringResource(R.string.balance)); Text(money(balance)) }
        payments.forEach { p -> Text("${p.str("method_type")?.replace('_', ' ')} · ${p.str("status")} · ${money(p.long("amount"))}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
        message?.let { Text(it, color = MaterialTheme.colorScheme.error) }
        if (momoPaymentId != null) Text(waiting, color = MaterialTheme.colorScheme.primary)

        if (open && c?.has("pos", "pos.edit") == true) Row2 {
            Field(promo, { promo = it.uppercase() }, stringResource(R.string.promo_code), Modifier.weight(1f))
            OutlinedButton(enabled = promo.isNotBlank(), onClick = {
                scope.launch {
                    message = null
                    if (store.isPending("orders", id) && (!container.sync.syncNow() || store.isPending("orders", id))) { message = notSynced; return@launch }
                    try { container.api.post("orders/$id/voucher", com.edusmartconsult.hospitality.core.obj { put("code", promo) }); container.sync.syncNow(); promo = ""; snack.showSnackbar(promoOk) } catch (e: ApiException) { message = if (e.isOffline) offline else e.message }
                }
            }) { Text(stringResource(R.string.apply)) }
        }
        if (status == "open") OutlinedButton(onClick = { scope.launch { store.sendOrder(id) } }, modifier = Modifier.fillMaxWidth()) { Text(stringResource(R.string.send_order)) }
        if (open && balance > 0 && c?.has("pos", "pos.create") == true) {
            SectionTitle(stringResource(R.string.pay))
            Field(amount, { amount = it.filter(Char::isDigit) }, stringResource(R.string.amount), number = true)
            PrimaryButton(stringResource(R.string.pay_cash), { scope.launch { store.payCash(id, (amount.toLongOrNull() ?: 0).coerceIn(1, balance)) } }, Modifier.fillMaxWidth(), enabled = (amount.toLongOrNull() ?: 0) > 0)
            if (c.paymentMethods.any { it.str("type") == "momo" }) {
                Field(momoPhone, { momoPhone = it.filter(Char::isDigit) }, stringResource(R.string.momo_phone), phone = true)
                OutlinedButton(enabled = momoPhone.length >= 9 && momoPaymentId == null, modifier = Modifier.fillMaxWidth(), onClick = {
                    scope.launch {
                        message = null
                        if (store.isPending("orders", id)) { if (!container.sync.syncNow() || store.isPending("orders", id)) { message = notSynced; return@launch } }
                        try {
                            val res = container.api.post("orders/$id/pay", com.edusmartconsult.hospitality.core.obj {
                                put("amount", (amount.toLongOrNull() ?: balance).coerceIn(1, balance)); put("method_type", "momo"); put("payer_phone", momoPhone); put("idempotency_key", com.edusmartconsult.hospitality.core.newId())
                            }).asObject()
                            momoPaymentId = res.obj("payment")?.str("id")
                        } catch (e: ApiException) { message = if (e.isOffline) offline else e.message }
                    }
                }) { Text(stringResource(R.string.pay_momo)) }
            }
        }
        OutlinedButton(modifier = Modifier.fillMaxWidth(), onClick = {
            val addr = container.session.printerAddress
            if (addr == null) { scope.launch { snack.showSnackbar(printerMissing) }; return@OutlinedButton }
            scope.launch {
                runCatching {
                    val bytes = EscPos.receipt(c?.tenantName ?: "", c?.settingStr("receipt.footer") ?: "", o, items, payments, java.time.LocalDateTime.now().toString().replace('T', ' ').take(16))
                    EscPos.send(context, addr, bytes)
                }.onSuccess { snack.showSnackbar(printedMsg) }.onFailure { snack.showSnackbar(it.message ?: "print failed") }
            }
        }) { Text(stringResource(R.string.print_receipt)) }
    }

    voidDialog?.let { line ->
        var reason by remember { mutableStateOf("") }
        var pin by remember { mutableStateOf("") }
        AlertDialog(onDismissRequest = { voidDialog = null }, title = { Text(stringResource(R.string.delete) + ": " + (line.str("name") ?: "")) },
            text = { Column(verticalArrangement = Arrangement.spacedBy(8.dp)) { Field(reason, { reason = it }, "Reason"); Field(pin, { pin = it.filter(Char::isDigit).take(6) }, "Manager PIN", number = true, password = true) } },
            confirmButton = { TextButton(enabled = reason.isNotBlank(), onClick = {
                scope.launch {
                    try {
                        container.api.post("order-items/${line.str("id")}/void", com.edusmartconsult.hospitality.core.obj { put("reason", reason); if (pin.isNotBlank()) put("manager_pin", pin) })
                        container.sync.syncNow()
                    } catch (e: ApiException) { snack.showSnackbar(if (e.isOffline) offline else e.message ?: "") }
                    voidDialog = null
                }
            }) { Text(stringResource(R.string.confirm_ok)) } },
            dismissButton = { TextButton(onClick = { voidDialog = null }) { Text(stringResource(R.string.cancel)) } })
    }
}

@Composable
fun KitchenScreen() {
    val container = LocalContainer.current
    val scope = rememberCoroutineScope()
    var station by remember { mutableStateOf("kitchen") }
    var tickets by remember { mutableStateOf<List<JsonObject>>(emptyList()) }
    var offline by remember { mutableStateOf(false) }
    LaunchedEffect(station) {
        while (true) {
            runCatching { container.api.get("kitchen/tickets", mapOf("station" to station)).asObject().objs("data") }
                .onSuccess { tickets = it; offline = false }.onFailure { offline = true }
            delay(8000)
        }
    }
    Column(Modifier.fillMaxSize().padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            FilterChip(selected = station == "kitchen", onClick = { station = "kitchen" }, label = { Text(stringResource(R.string.station_kitchen)) })
            FilterChip(selected = station == "bar", onClick = { station = "bar" }, label = { Text(stringResource(R.string.station_bar)) })
        }
        if (offline) Text(stringResource(R.string.offline_required), color = MaterialTheme.colorScheme.error)
        if (tickets.isEmpty()) Empty()
        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            items(tickets) { t ->
                ListCard {
                    Text(t.obj("order")?.str("number") ?: "", fontWeight = FontWeight.Bold)
                    t.objs("items").forEach { i ->
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = androidx.compose.ui.Alignment.CenterVertically) {
                            Column(Modifier.weight(1f)) { Text("${i.dbl("quantity").toLong()} × ${i.str("name")}"); i.str("notes")?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodySmall) } }
                            val next = when (i.str("status")) { "sent" -> "preparing" to R.string.start; "preparing" -> "ready" to R.string.ready; else -> "served" to R.string.served }
                            TextButton(onClick = { scope.launch { runCatching { container.api.post("order-items/${i.str("id")}/status", com.edusmartconsult.hospitality.core.obj { put("status", next.first) }) } } }) { Text(stringResource(next.second)) }
                        }
                    }
                }
            }
        }
    }
}
