package com.edusmartconsult.hospitality.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.FilterChip
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Tab
import androidx.compose.material3.TabRow
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
import androidx.compose.ui.graphics.Color
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
import kotlinx.coroutines.launch
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

@Composable
fun RoomsScreen(onNew: () -> Unit, onOpen: (String) -> Unit) {
    var tab by remember { mutableStateOf(0) }
    val c = ctx()
    Column(Modifier.fillMaxSize()) {
        TabRow(selectedTabIndex = tab) {
            Tab(selected = tab == 0, onClick = { tab = 0 }, text = { Text(stringResource(R.string.rooms_board)) })
            Tab(selected = tab == 1, onClick = { tab = 1 }, text = { Text(stringResource(R.string.reservations)) })
        }
        if (tab == 0) RoomBoard(onOpen) else ReservationList(onNew = onNew.takeIf { c?.can("rooms.create") == true }, onOpen = onOpen)
    }
}

/** Built from synced rooms + reservations, so the board works offline. */
@Composable
private fun RoomBoard(onOpen: (String) -> Unit) {
    val rooms by rememberDocs("rooms") { l -> l.filter { it.str("is_active") != "false" }.sortedBy { it.str("number") } }
    val res by rememberDocs("reservations")
    val today = LocalDate.now().toString()
    val byRoom = res.filter { it.str("room_id") != null && it.str("status") in setOf("confirmed", "pending", "checked_in") && (it.str("check_in") ?: "") <= today && (it.str("check_out") ?: "") > today }.associateBy { it.str("room_id") }
    if (rooms.isEmpty()) { Empty(); return }
    LazyVerticalGrid(GridCells.Adaptive(110.dp), contentPadding = PaddingValues(12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        items(rooms, key = { it.str("id") ?: "" }) { r ->
            val x = byRoom[r.str("id")]
            val hk = r.str("housekeeping_status")
            val (bg, label) = when {
                hk == "out_of_order" -> Color(0xFFE2E8F0) to "OOO"
                x?.str("status") == "checked_in" -> Color(0xFFFFE4E6) to stringResource(R.string.room_occupied)
                x != null -> Color(0xFFFEF3C7) to stringResource(R.string.room_reserved)
                hk == "dirty" -> Color(0xFFFFEDD5) to stringResource(R.string.room_dirty)
                else -> Color(0xFFD1FAE5) to stringResource(R.string.room_vacant)
            }
            Card(onClick = { x?.str("id")?.let(onOpen) }, colors = CardDefaults.cardColors(containerColor = bg, contentColor = Color(0xFF0F172A))) {
                Column(Modifier.padding(12.dp)) {
                    Text(r.str("number") ?: "", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                    Text(label, style = MaterialTheme.typography.labelMedium)
                    Text(x?.str("number") ?: "", style = MaterialTheme.typography.labelSmall, maxLines = 1)
                }
            }
        }
    }
}

@Composable
private fun ReservationList(onNew: (() -> Unit)?, onOpen: (String) -> Unit) {
    val res by rememberDocs("reservations") { l -> l.sortedByDescending { it.str("check_in") } }
    val types by rememberDocs("room_types")
    val typeName = types.associate { it.str("id") to it.str("name") }
    var filter by remember { mutableStateOf("upcoming") }
    val today = LocalDate.now().toString()
    val shown = res.filter { when (filter) { "upcoming" -> it.str("status") in setOf("confirmed", "pending", "checked_in") && (it.str("check_out") ?: "") >= today; else -> true } }
    Column(Modifier.fillMaxSize()) {
        Row(Modifier.padding(12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            FilterChip(selected = filter == "upcoming", onClick = { filter = "upcoming" }, label = { Text(stringResource(R.string.status_open)) })
            FilterChip(selected = filter == "all", onClick = { filter = "all" }, label = { Text("All") })
            if (onNew != null) OutlinedButton(onClick = onNew) { Text(stringResource(R.string.new_reservation)) }
        }
        if (shown.isEmpty()) Empty()
        LazyColumn(contentPadding = PaddingValues(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            items(shown, key = { it.str("id") ?: "" }) { r ->
                ListCard(onClick = { onOpen(r.str("id")!!) }) {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text(r.str("number") ?: "", fontWeight = FontWeight.SemiBold); Text(r.str("status")?.replace('_', ' ') ?: "", color = MaterialTheme.colorScheme.primary) }
                    Text("${r.str("check_in")} → ${r.str("check_out")} · ${typeName[r.str("room_type_id")] ?: ""}", style = MaterialTheme.typography.bodySmall)
                    Text(money(r.long("total")), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
    }
}

@Composable
fun NewReservationScreen(onDone: () -> Unit) {
    val container = LocalContainer.current
    val c = ctx()
    val scope = rememberCoroutineScope()
    val types by rememberDocs("room_types") { l -> l.sortedBy { it.str("name") } }
    var typeId by remember { mutableStateOf<String?>(null) }
    var checkIn by remember { mutableStateOf(LocalDate.now().toString()) }
    var checkOut by remember { mutableStateOf(LocalDate.now().plusDays(1).toString()) }
    var adults by remember { mutableStateOf("2") }
    var children by remember { mutableStateOf("0") }
    var name by remember { mutableStateOf("") }
    var phone by remember { mutableStateOf("") }
    var quote by remember { mutableStateOf<JsonObject?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var busy by remember { mutableStateOf(false) }
    val custom = remember { mutableStateMapOf<String, String>() }
    val offline = stringResource(R.string.offline_required)
    LaunchedEffect(types) { if (typeId == null) typeId = types.firstOrNull()?.str("id") }
    LaunchedEffect(typeId, checkIn, checkOut) {
        quote = null; error = null
        val t = typeId ?: return@LaunchedEffect
        if (checkOut <= checkIn) return@LaunchedEffect
        try { quote = container.api.get("availability", mapOf("room_type_id" to t, "check_in" to checkIn, "check_out" to checkOut)).asObject().obj("data") } catch (e: ApiException) { error = if (e.isOffline) offline else e.message }
    }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Picker(stringResource(R.string.room_type), types.map { it.str("id")!! to (it.str("name") ?: "") }, typeId, { typeId = it })
        DateField(checkIn, { checkIn = it; if (checkOut <= it) checkOut = LocalDate.parse(it).plusDays(1).toString() }, stringResource(R.string.check_in_date))
        DateField(checkOut, { checkOut = it }, stringResource(R.string.check_out_date))
        Row2 { Field(adults, { adults = it.filter(Char::isDigit) }, stringResource(R.string.adults), Modifier.weight(1f), number = true); Field(children, { children = it.filter(Char::isDigit) }, stringResource(R.string.children), Modifier.weight(1f), number = true) }
        Field(name, { name = it }, label("guest", stringResource(R.string.guest_name)))
        Field(phone, { phone = it }, stringResource(R.string.guest_phone), phone = true)
        CustomFieldsForm("reservation", custom)
        quote?.let { q -> Text("${stringResource(R.string.rooms_free, q.int("free"))} · ${q.int("nights")} ${stringResource(R.string.nights)} · ${money(q.long("total"))}", fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.primary) }
        error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
        ActionButton(stringResource(R.string.create), Modifier.fillMaxWidth(), enabled = !busy && name.isNotBlank() && quote != null && (quote?.int("free") ?: 0) > 0, onClick = {
            busy = true; error = null
            scope.launch {
                try {
                    container.api.post("reservations", buildJsonObject {
                        put("id", newId()); put("room_type_id", typeId!!); put("check_in", checkIn); put("check_out", checkOut)
                        put("adults", adults.toIntOrNull() ?: 1); put("children", children.toIntOrNull() ?: 0)
                        put("guest", buildJsonObject { put("name", name); if (phone.isNotBlank()) put("phone", phone) })
                        put("source", "walk_in")
                        val meta = customFieldsMeta("reservation", c, custom); if (meta.isNotEmpty()) put("meta", meta)
                    })
                    container.sync.syncNow(); onDone()
                } catch (e: ApiException) { error = if (e.isOffline) offline else e.message }
                busy = false
            }
        })
    }
}

@Composable
fun ReservationDetailScreen(id: String) {
    val container = LocalContainer.current
    val c = ctx()
    val scope = rememberCoroutineScope()
    var data by remember { mutableStateOf<JsonObject?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var idNumber by remember { mutableStateOf("") }
    var pay by remember { mutableStateOf("") }
    val rooms by rememberDocs("rooms")
    val offline = stringResource(R.string.offline_required)

    suspend fun load() { try { data = container.api.get("reservations/$id").asObject() } catch (e: ApiException) { error = if (e.isOffline) offline else e.message } }
    fun act(path: String, body: JsonObject = JsonObject(emptyMap())) = scope.launch { try { container.api.post("reservations/$id/$path", body); load(); container.sync.syncNow() } catch (e: ApiException) { error = if (e.isOffline) offline else (e.message + (e.details?.str("balance")?.let { " ($it)" } ?: "")) } }
    LaunchedEffect(id) { load() }

    val d = data ?: run { if (error != null) Text(error!!, Modifier.padding(16.dp), color = MaterialTheme.colorScheme.error) else Loading(); return }
    val r = d.obj("data") ?: return
    val folio = d.obj("folio")
    val status = r.str("status")
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text(r.str("number") ?: "", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
        Text("${d.obj("guest")?.str("name") ?: ""} · ${r.str("check_in")} → ${r.str("check_out")} · ${status?.replace('_', ' ')}")
        Text(money(r.long("total")), color = MaterialTheme.colorScheme.primary)
        error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
        if (status == "confirmed" || status == "pending") {
            Picker(stringResource(R.string.assign_room), rooms.filter { it.str("room_type_id") == r.str("room_type_id") }.map { it.str("id")!! to "${it.str("number")} (${it.str("housekeeping_status")})" }, r.str("room_id"), { act("assign", buildJsonObject { put("room_id", it) }) })
            if (c?.settingBool("rooms.require_id_on_checkin", true) == true && d.obj("guest")?.str("id_number") == null) Field(idNumber, { idNumber = it }, stringResource(R.string.guest_id))
            if (c?.can("rooms.check_in") == true) ActionButton(stringResource(R.string.check_in), Modifier.fillMaxWidth(), onClick = { act("check-in", if (idNumber.isNotBlank()) buildJsonObject { put("guest", buildJsonObject { put("id_number", idNumber) }) } else JsonObject(emptyMap())) })
            OutlinedButton(onClick = { act("cancel") }, modifier = Modifier.fillMaxWidth()) { Text(stringResource(R.string.cancel_reservation)) }
        }
        if (status == "checked_in" && c?.can("rooms.check_out") == true) ActionButton(stringResource(R.string.check_out), Modifier.fillMaxWidth(), onClick = { act("check-out") })
        if (folio != null) {
            HorizontalDivider()
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text(stringResource(R.string.folio), fontWeight = FontWeight.SemiBold); Text("${stringResource(R.string.balance)}: ${money(folio.long("balance"))}") }
            folio.objs("entries").forEach { e -> Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text(e.str("description") ?: "", style = MaterialTheme.typography.bodySmall); Text(money(e.long("amount")), style = MaterialTheme.typography.bodySmall) } }
            if (folio.str("status") == "open" && c?.can("rooms.folio") == true) {
                Field(pay, { pay = it.filter(Char::isDigit) }, stringResource(R.string.amount), number = true)
                OutlinedButton(enabled = (pay.toLongOrNull() ?: 0) > 0, modifier = Modifier.fillMaxWidth(), onClick = {
                    scope.launch { try { container.api.post("folios/${folio.str("id")}/payments", buildJsonObject { put("amount", pay.toLong()); put("method_type", "cash"); put("idempotency_key", newId()) }); pay = ""; load() } catch (e: ApiException) { error = if (e.isOffline) offline else e.message } }
                }) { Text(stringResource(R.string.add_payment) + " · " + stringResource(R.string.pay_cash)) }
            }
        }
    }
}
