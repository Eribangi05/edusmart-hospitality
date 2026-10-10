package com.edusmartconsult.hospitality.ui

import android.Manifest
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatDelegate
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
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.core.os.LocaleListCompat
import com.edusmartconsult.hospitality.BuildConfig
import com.edusmartconsult.hospitality.R
import com.edusmartconsult.hospitality.print.EscPos
import java.text.DateFormat
import java.util.Date
import kotlinx.coroutines.launch

@Composable
fun MoreScreen(onNavigate: (String) -> Unit) {
    val c = ctx()
    val failed by LocalContainer.current.store.failedCount.collectAsState(initial = 0)
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        if (c?.has("pos", "pos.view") == true) {
            MoreRow(label("orders", stringResource(R.string.orders_title))) { onNavigate("pos/orders") }
            MoreRow(stringResource(R.string.kitchen_screen)) { onNavigate("pos/kitchen") }
        }
        if (c?.has("housekeeping", "housekeeping.view") == true) MoreRow(stringResource(R.string.my_tasks)) { onNavigate("tasks") }
        if (c?.hasModule("staff") == true) MoreRow(stringResource(R.string.clock)) { onNavigate("clock") }
        MoreRow(stringResource(R.string.outbox_title) + if (failed > 0) " ⚠ ${stringResource(R.string.failed_changes, failed)}" else "") { onNavigate("outbox") }
        MoreRow(stringResource(R.string.settings)) { onNavigate("settings") }
    }
}

@Composable
private fun MoreRow(text: String, onClick: () -> Unit) { ListCard(onClick = onClick) { Text(text, style = MaterialTheme.typography.titleMedium) } }

@Composable
fun SettingsScreen() {
    val container = LocalContainer.current
    val store = container.store
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val pending by store.pendingCount.collectAsState(initial = 0)
    val status by container.sync.status.collectAsState()
    var confirmLogout by remember { mutableStateOf(false) }
    var printer by remember { mutableStateOf(container.session.printerAddress) }
    var printers by remember { mutableStateOf<List<Pair<String, String>>>(emptyList()) }
    val permission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted -> if (granted) printers = EscPos.pairedPrinters(context) }
    val current = AppCompatDelegate.getApplicationLocales().toLanguageTags().ifBlank { "" }.substringBefore(',').substringBefore('-')

    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Picker(stringResource(R.string.language), listOf("" to stringResource(R.string.lang_system), "en" to "English", "fr" to "Français", "rw" to "Kinyarwanda"), current, { AppCompatDelegate.setApplicationLocales(LocaleListCompat.forLanguageTags(it)) })
        HorizontalDivider()
        Text(stringResource(R.string.printer), fontWeight = FontWeight.SemiBold)
        Text(printer?.let { addr -> EscPos.pairedPrinters(context).firstOrNull { it.first == addr }?.second ?: addr } ?: stringResource(R.string.printer_none), color = MaterialTheme.colorScheme.onSurfaceVariant)
        OutlinedButton(onClick = {
            if (Build.VERSION.SDK_INT >= 31) permission.launch(Manifest.permission.BLUETOOTH_CONNECT) else printers = EscPos.pairedPrinters(context)
        }) { Text(stringResource(R.string.printer_choose)) }
        printers.forEach { (addr, name) -> OutlinedButton(onClick = { container.session.printerAddress = addr; printer = addr; printers = emptyList() }, modifier = Modifier.fillMaxWidth()) { Text(name) } }
        HorizontalDivider()
        Text(stringResource(R.string.sync_status), fontWeight = FontWeight.SemiBold)
        Text(stringResource(R.string.last_synced, if (status.lastSyncAt > 0) DateFormat.getDateTimeInstance(DateFormat.SHORT, DateFormat.SHORT).format(Date(status.lastSyncAt)) else stringResource(R.string.never)))
        if (pending > 0) Text(stringResource(R.string.pending_changes, pending), color = MaterialTheme.colorScheme.primary)
        OutlinedButton(onClick = { scope.launch { container.sync.syncNow() } }) { Text(stringResource(R.string.sync_now)) }
        HorizontalDivider()
        PrimaryButton(stringResource(R.string.logout), { if (pending > 0) confirmLogout = true else scope.launch { container.signOut() } }, Modifier.fillMaxWidth())
        Text(stringResource(R.string.version, BuildConfig.VERSION_NAME), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
    }
    if (confirmLogout) AlertDialog(onDismissRequest = { confirmLogout = false }, title = { Text(stringResource(R.string.logout)) }, text = { Text(stringResource(R.string.logout_warning, pending)) },
        confirmButton = { TextButton(onClick = { confirmLogout = false; scope.launch { container.signOut() } }) { Text(stringResource(R.string.logout_confirm)) } },
        dismissButton = { TextButton(onClick = { confirmLogout = false }) { Text(stringResource(R.string.cancel)) } })
}

/** Everything waiting to sync, and anything the server rejected (with the reason) so staff can retry or discard it. */
@Composable
fun OutboxScreen() {
    val store = LocalContainer.current.store
    val scope = rememberCoroutineScope()
    val items by store.outbox.collectAsState(initial = emptyList())
    if (items.isEmpty()) { Empty(stringResource(R.string.outbox_empty)); return }
    LazyColumn(contentPadding = PaddingValues(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        items(items, key = { it.seq }) { o ->
            ListCard {
                Text("${o.entity} · ${o.op}", fontWeight = FontWeight.SemiBold)
                Text(if (o.status == "failed") (o.error ?: "") else stringResource(R.string.syncing), style = MaterialTheme.typography.bodySmall, color = if (o.status == "failed") MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant)
                if (o.status == "failed") Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedButton(onClick = { scope.launch { store.retry(o.seq) } }) { Text(stringResource(R.string.retry)) }
                    TextButton(onClick = { scope.launch { store.discard(o) } }) { Text(stringResource(R.string.discard)) }
                }
            }
        }
    }
}
