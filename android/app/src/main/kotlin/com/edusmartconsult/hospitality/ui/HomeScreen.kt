package com.edusmartconsult.hospitality.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AssistChip
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.edusmartconsult.hospitality.R
import com.edusmartconsult.hospitality.core.AppJson
import com.edusmartconsult.hospitality.core.asObject
import com.edusmartconsult.hospitality.core.dbl
import com.edusmartconsult.hospitality.core.int
import com.edusmartconsult.hospitality.core.long
import com.edusmartconsult.hospitality.core.money
import com.edusmartconsult.hospitality.core.obj
import com.edusmartconsult.hospitality.data.local.KvEntity
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonObject

@OptIn(androidx.compose.foundation.layout.ExperimentalLayoutApi::class)
@Composable
fun HomeScreen(onNavigate: (String) -> Unit) {
    val container = LocalContainer.current
    val c = ctx()
    var d by remember { mutableStateOf<JsonObject?>(null) }
    // show the last known numbers instantly, then refresh when online
    LaunchedEffect(Unit) {
        container.db.kv().get("dashboard")?.let { d = runCatching { AppJson.parseToJsonElement(it).asObject() }.getOrNull() }
        runCatching {
            val res = container.api.get("reports/dashboard").asObject().obj("data")
            if (res != null) { d = res; container.db.kv().put(KvEntity("dashboard", AppJson.encodeToString(JsonElement.serializer(), res))) }
        }
    }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(c?.userName ?: "", style = MaterialTheme.typography.titleLarge)
        val x = d
        if (x != null) {
            SectionTitle(stringResource(R.string.dashboard_today))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                StatCard(stringResource(R.string.collected), money(x.obj("collected")?.long("total") ?: 0), Modifier.weight(1f))
                StatCard(label("orders", stringResource(R.string.orders)), (x.obj("orders")?.int("paid_today") ?: 0).toString(), Modifier.weight(1f), sub = stringResource(R.string.open_orders, x.obj("orders")?.int("open") ?: 0))
            }
            if (c?.hasModule("rooms") == true) {
                val r = x.obj("rooms")
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    StatCard(stringResource(R.string.occupancy), "${r?.int("occupied") ?: 0}/${r?.int("total") ?: 0}", Modifier.weight(1f), sub = "${r?.dbl("occupancy_pct") ?: 0.0}%")
                    StatCard(stringResource(R.string.arrivals), (r?.int("arrivals") ?: 0).toString(), Modifier.weight(1f), sub = stringResource(R.string.departures) + ": " + (r?.int("departures") ?: 0))
                }
            }
            val low = x["low_stock"]
            if (low != null || x.int("tasks_open") > 0) {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    if (low != null) StatCard(stringResource(R.string.low_stock), x.int("low_stock").toString(), Modifier.weight(1f))
                    StatCard(stringResource(R.string.tasks_open), x.int("tasks_open").toString(), Modifier.weight(1f))
                }
            }
        }
        SectionTitle(stringResource(R.string.quick_actions))
        androidx.compose.foundation.layout.FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            if (c?.has("pos", "pos.create") == true) AssistChip(onClick = { onNavigate("pos") }, label = { Text(stringResource(R.string.new_order)) })
            if (c?.has("rooms", "rooms.create") == true) AssistChip(onClick = { onNavigate("rooms/new") }, label = { Text(stringResource(R.string.new_reservation)) })
            if (c?.has("tourism", "tourism.create") == true) AssistChip(onClick = { onNavigate("tours/new") }, label = { Text(stringResource(R.string.new_booking)) })
            if (c?.hasModule("housekeeping") == true) AssistChip(onClick = { onNavigate("tasks") }, label = { Text(stringResource(R.string.my_tasks)) })
            if (c?.hasModule("staff") == true) AssistChip(onClick = { onNavigate("clock") }, label = { Text(stringResource(R.string.clock)) })
        }
    }
}
