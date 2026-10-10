package com.edusmartconsult.hospitality.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.edusmartconsult.hospitality.R
import com.edusmartconsult.hospitality.core.asObject
import com.edusmartconsult.hospitality.core.int
import com.edusmartconsult.hospitality.core.long
import com.edusmartconsult.hospitality.core.money
import com.edusmartconsult.hospitality.core.newId
import com.edusmartconsult.hospitality.core.obj
import com.edusmartconsult.hospitality.core.objs
import com.edusmartconsult.hospitality.core.str
import com.edusmartconsult.hospitality.data.net.ApiException
import java.time.LocalDate
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

private val BOOKABLE = setOf("tour", "activity", "transfer", "rental", "package", "spa", "event", "service")

@Composable
fun ToursScreen(onNew: () -> Unit, onOpen: (String) -> Unit) {
    val c = ctx()
    val all by rememberDocs("bookings") { l -> l.sortedBy { it.str("date") } }
    var filter by remember { mutableStateOf("today") }
    val today = LocalDate.now().toString()
    val shown = all.filter { b ->
        val active = b.str("status") !in setOf("cancelled", "no_show")
        when (filter) { "today" -> active && b.str("date")?.take(10) == today; "upcoming" -> active && (b.str("date")?.take(10) ?: "") >= today; else -> true }
    }
    Column(Modifier.fillMaxSize()) {
        Row(Modifier.padding(12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            FilterChip(selected = filter == "today", onClick = { filter = "today" }, label = { Text(stringResource(R.string.today)) })
            FilterChip(selected = filter == "upcoming", onClick = { filter = "upcoming" }, label = { Text(stringResource(R.string.bookings)) })
            FilterChip(selected = filter == "all", onClick = { filter = "all" }, label = { Text("All") })
            if (c?.can("tourism.create") == true) OutlinedButton(onClick = onNew) { Text(stringResource(R.string.new_booking)) }
        }
        if (filter == "today") Text("${shown.sumOf { it.int("pax_adults") + it.int("pax_children") }} pax", Modifier.padding(horizontal = 16.dp), color = MaterialTheme.colorScheme.onSurfaceVariant)
        if (shown.isEmpty()) Empty()
        LazyColumn(contentPadding = PaddingValues(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            items(shown, key = { it.str("id") ?: "" }) { b ->
                ListCard(onClick = { onOpen(b.str("id")!!) }) {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text(b.str("customer_name") ?: "", fontWeight = FontWeight.SemiBold); Text(money(b.long("total"))) }
                    Text("${b.str("number")} · ${b.str("date")?.take(10)} ${b.str("start_time") ?: ""} · ${b.int("pax_adults") + b.int("pax_children")} pax · ${b.str("status")?.replace('_', ' ')}", style = MaterialTheme.typography.bodySmall)
                }
            }
        }
    }
}

@Composable
fun NewBookingScreen(onDone: () -> Unit) {
    val container = LocalContainer.current
    val c = ctx()
    val scope = rememberCoroutineScope()
    val services by rememberDocs("catalog_items") { l -> l.filter { it.str("kind") in BOOKABLE && it.str("is_active") != "false" }.sortedBy { it.str("name") } }
    var serviceId by remember { mutableStateOf<String?>(null) }
    var departures by remember { mutableStateOf<List<JsonObject>>(emptyList()) }
    var departureId by remember { mutableStateOf("") }
    var date by remember { mutableStateOf(LocalDate.now().toString()) }
    var adults by remember { mutableStateOf("2") }
    var children by remember { mutableStateOf("0") }
    var units by remember { mutableStateOf("1") }
    var name by remember { mutableStateOf("") }
    var phone by remember { mutableStateOf("") }
    var pickup by remember { mutableStateOf("") }
    var quote by remember { mutableStateOf<JsonObject?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var busy by remember { mutableStateOf(false) }
    val custom = remember { mutableStateMapOf<String, String>() }
    val offline = stringResource(R.string.offline_required)
    val service = services.firstOrNull { it.str("id") == serviceId }
    val perUnit = service?.str("pricing_model") in setOf("per_day", "per_night", "per_hour")
    LaunchedEffect(services) { if (serviceId == null) serviceId = services.firstOrNull()?.str("id") }
    LaunchedEffect(serviceId) {
        departureId = ""
        departures = emptyList()
        val s = serviceId ?: return@LaunchedEffect
        runCatching { container.api.get("tourism/departures", mapOf("catalog_item_id" to s, "status" to "open", "from" to LocalDate.now().toString())).asObject().objs("data").filter { it.int("available") > 0 } }.onSuccess { departures = it }
    }
    LaunchedEffect(serviceId, departureId, adults, children, units) {
        quote = null; error = null
        val s = serviceId ?: return@LaunchedEffect
        delay(300)
        try {
            quote = container.api.post("tourism/quote", buildJsonObject {
                put("catalog_item_id", s); if (departureId.isNotBlank()) put("departure_id", departureId)
                put("pax_adults", adults.toIntOrNull() ?: 1); put("pax_children", children.toIntOrNull() ?: 0); put("units", units.toDoubleOrNull() ?: 1.0)
            }).asObject().obj("data")
        } catch (e: ApiException) { error = if (e.isOffline) offline else e.message }
    }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        if (services.isEmpty()) Empty()
        Picker(stringResource(R.string.service), services.map { it.str("id")!! to "${it.str("name")} · ${it.str("kind")}" }, serviceId, { serviceId = it })
        if (departures.isNotEmpty()) Picker(stringResource(R.string.departure), listOf("" to stringResource(R.string.private_date)) + departures.map { it.str("id")!! to "${it.str("date")?.take(10)} ${it.str("start_time") ?: ""} · ${stringResource(R.string.seats_left, it.int("available"))}" }, departureId, { departureId = it })
        if (departureId.isBlank()) DateField(date, { date = it }, stringResource(R.string.date))
        Row2 { Field(adults, { adults = it.filter(Char::isDigit) }, stringResource(R.string.adults), Modifier.weight(1f), number = true); Field(children, { children = it.filter(Char::isDigit) }, stringResource(R.string.children), Modifier.weight(1f), number = true) }
        if (perUnit) Field(units, { units = it.filter { ch -> ch.isDigit() || ch == '.' } }, stringResource(R.string.units), number = true)
        Field(name, { name = it }, stringResource(R.string.customer_name))
        Field(phone, { phone = it }, stringResource(R.string.guest_phone), phone = true)
        Field(pickup, { pickup = it }, stringResource(R.string.pickup))
        CustomFieldsForm("booking", custom)
        quote?.let { q ->
            Column {
                q.objs("lines").forEach { l -> Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text(l.str("description") ?: "", style = MaterialTheme.typography.bodySmall); Text(money(l.long("line_total")), style = MaterialTheme.typography.bodySmall) } }
                HorizontalDivider()
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text(stringResource(R.string.total), fontWeight = FontWeight.Bold); Text(money(q.long("total")), fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary) }
            }
        }
        error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
        ActionButton(stringResource(R.string.create), Modifier.fillMaxWidth(), enabled = !busy && name.isNotBlank() && quote != null, onClick = {
            busy = true; error = null
            scope.launch {
                try {
                    container.api.post("bookings", buildJsonObject {
                        put("id", newId()); put("catalog_item_id", serviceId!!); if (departureId.isNotBlank()) put("departure_id", departureId) else put("date", date)
                        put("pax_adults", adults.toIntOrNull() ?: 1); put("pax_children", children.toIntOrNull() ?: 0); put("units", units.toDoubleOrNull() ?: 1.0)
                        put("customer_name", name); if (phone.isNotBlank()) put("customer_phone", phone); if (pickup.isNotBlank()) put("pickup_location", pickup)
                        put("source", "walk_in")
                        val meta = customFieldsMeta("booking", c, custom); if (meta.isNotEmpty()) put("meta", meta)
                    })
                    container.sync.syncNow(); onDone()
                } catch (e: ApiException) { error = if (e.isOffline) offline else e.message }
                busy = false
            }
        })
    }
}

@Composable
fun BookingDetailScreen(id: String) {
    val container = LocalContainer.current
    val c = ctx()
    val scope = rememberCoroutineScope()
    var data by remember { mutableStateOf<JsonObject?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var amount by remember { mutableStateOf("") }
    var phone by remember { mutableStateOf("") }
    val resources by rememberDocs("tourism_resources")
    val offline = stringResource(R.string.offline_required)
    suspend fun load() { try { data = container.api.get("bookings/$id").asObject().obj("data"); amount = data?.long("balance")?.toString() ?: "" } catch (e: ApiException) { error = if (e.isOffline) offline else e.message } }
    fun call(path: String, body: JsonObject = JsonObject(emptyMap())) = scope.launch { try { container.api.post(path, body); load(); container.sync.syncNow() } catch (e: ApiException) { error = if (e.isOffline) offline else e.message } }
    LaunchedEffect(id) { load() }
    val b = data ?: run { if (error != null) Text(error!!, Modifier.padding(16.dp), color = MaterialTheme.colorScheme.error) else Loading(); return }
    val status = b.str("status") ?: ""
    val next = mapOf("inquiry" to listOf("confirmed", "cancelled"), "confirmed" to listOf("in_progress", "cancelled", "no_show"), "paid" to listOf("in_progress", "cancelled", "no_show"), "in_progress" to listOf("completed"))[status].orEmpty()
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text(b.str("number") ?: "", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
        Text("${b.str("customer_name")} · ${b.str("date")?.take(10)} · ${b.int("pax_adults") + b.int("pax_children")} pax · ${status.replace('_', ' ')}")
        b.objs("lines").forEach { l -> Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text("${l.str("quantity")} × ${l.str("description")}", style = MaterialTheme.typography.bodySmall); Text(money(l.long("line_total")), style = MaterialTheme.typography.bodySmall) } }
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text(stringResource(R.string.total), fontWeight = FontWeight.Bold); Text(money(b.long("total")), fontWeight = FontWeight.Bold) }
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text(stringResource(R.string.balance)); Text(money(b.long("balance"))) }
        error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
        if (c?.can("tourism.edit") == true) Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) { next.forEach { s -> OutlinedButton(onClick = { call("bookings/$id/status/$s") }) { Text(s.replace('_', ' ')) } } }
        if (c?.can("tourism.assign") == true && status !in setOf("cancelled", "no_show", "completed")) {
            HorizontalDivider()
            Text(stringResource(R.string.assign_guide), fontWeight = FontWeight.SemiBold)
            b.objs("assignments").forEach { a -> Text(resources.firstOrNull { it.str("id") == a.str("resource_id") }?.str("name") ?: "", style = MaterialTheme.typography.bodySmall) }
            Picker(stringResource(R.string.assign_guide), resources.filter { it.str("is_active") != "false" }.map { it.str("id")!! to "${it.str("name")} · ${it.str("kind")}" }, null, { call("bookings/$id/assign", buildJsonObject { put("resource_id", it) }) })
        }
        if (b.long("balance") > 0 && status !in setOf("cancelled", "no_show", "completed") && c?.can("tourism.create") == true) {
            HorizontalDivider()
            Field(amount, { amount = it.filter(Char::isDigit) }, stringResource(R.string.amount), number = true)
            OutlinedButton(modifier = Modifier.fillMaxWidth(), enabled = (amount.toLongOrNull() ?: 0) > 0, onClick = { call("bookings/$id/payments", buildJsonObject { put("amount", amount.toLong()); put("method_type", "cash"); put("idempotency_key", newId()) }) }) { Text(stringResource(R.string.pay_cash)) }
            if (c.paymentMethods.any { it.str("type") == "momo" }) {
                Field(phone, { phone = it.filter(Char::isDigit) }, stringResource(R.string.momo_phone), phone = true)
                OutlinedButton(modifier = Modifier.fillMaxWidth(), enabled = phone.length >= 9 && (amount.toLongOrNull() ?: 0) > 0, onClick = { call("bookings/$id/payments", buildJsonObject { put("amount", amount.toLong()); put("method_type", "momo"); put("payer_phone", phone); put("idempotency_key", newId()) }) }) { Text(stringResource(R.string.pay_momo)) }
            }
        }
    }
}
