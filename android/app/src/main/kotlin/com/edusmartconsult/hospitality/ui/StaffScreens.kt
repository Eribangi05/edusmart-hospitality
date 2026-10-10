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
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.edusmartconsult.hospitality.R
import com.edusmartconsult.hospitality.core.str
import kotlinx.coroutines.launch

/** Housekeeping and maintenance tasks assigned to me. Works offline: status changes sync later. */
@Composable
fun TasksScreen() {
    val container = LocalContainer.current
    val c = ctx()
    val scope = rememberCoroutineScope()
    val me = c?.userId
    val tasks by rememberDocs("work_tasks") { l ->
        l.filter { (it.str("assigned_to") == me || c?.can("housekeeping.assign") == true) && it.str("status") in setOf("pending", "in_progress", "done") }
            .sortedBy { when (it.str("priority")) { "urgent" -> 0; "high" -> 1; "normal" -> 2; else -> 3 } }
    }
    val rooms by rememberDocs("rooms")
    val roomNo = rooms.associate { it.str("id") to it.str("number") }
    if (tasks.isEmpty()) { Empty(stringResource(R.string.no_tasks)); return }
    LazyColumn(contentPadding = PaddingValues(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        items(tasks, key = { it.str("id") ?: "" }) { t ->
            val status = t.str("status")
            ListCard {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text(t.str("title") ?: "", fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                    when (t.str("priority")) { "urgent" -> Text(stringResource(R.string.priority_urgent), color = MaterialTheme.colorScheme.error); "high" -> Text(stringResource(R.string.priority_high), color = MaterialTheme.colorScheme.error); else -> {} }
                }
                roomNo[t.str("room_id")]?.let { Text("${stringResource(R.string.nav_rooms)} $it", style = MaterialTheme.typography.bodySmall) }
                t.str("description")?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
                Row(Modifier.padding(top = 8.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    if (status == "pending") OutlinedButton(onClick = { scope.launch { container.store.taskAction(t.str("id")!!, "start") } }) { Text(stringResource(R.string.task_start)) }
                    if (status == "pending" || status == "in_progress") PrimaryButton(stringResource(R.string.task_complete), { scope.launch { container.store.taskAction(t.str("id")!!, "complete") } })
                    if (status == "done" && c?.can("housekeeping.inspect") == true) PrimaryButton(stringResource(R.string.task_inspect), { scope.launch { container.store.taskAction(t.str("id")!!, "inspect") } })
                }
            }
        }
    }
}

@Composable
fun ClockScreen() {
    val container = LocalContainer.current
    val scope = rememberCoroutineScope()
    var last by remember { mutableStateOf<Pair<String, String>?>(null) }
    var tick by remember { mutableStateOf(0) }
    LaunchedEffect(tick) { last = container.store.lastClock() }
    Column(Modifier.fillMaxSize().padding(24.dp), verticalArrangement = Arrangement.spacedBy(16.dp, androidx.compose.ui.Alignment.CenterVertically), horizontalAlignment = androidx.compose.ui.Alignment.CenterHorizontally) {
        last?.let { (type, at) -> Text(stringResource(R.string.last_clock, "${type.uppercase()} ${at.replace('T', ' ').take(16)}"), style = MaterialTheme.typography.titleMedium) }
        PrimaryButton(stringResource(R.string.clock_in), { scope.launch { container.store.clock("in"); tick++ } }, Modifier.fillMaxWidth())
        OutlinedButton(onClick = { scope.launch { container.store.clock("out"); tick++ } }, modifier = Modifier.fillMaxWidth()) { Text(stringResource(R.string.clock_out)) }
    }
}
