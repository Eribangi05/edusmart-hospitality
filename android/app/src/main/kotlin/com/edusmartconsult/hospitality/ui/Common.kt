package com.edusmartconsult.hospitality.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.ProvidableCompositionLocal
import androidx.compose.runtime.compositionLocalOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.State
import androidx.compose.ui.res.stringResource
import com.edusmartconsult.hospitality.AppContainer
import com.edusmartconsult.hospitality.R
import com.edusmartconsult.hospitality.core.str
import com.edusmartconsult.hospitality.data.AppContext
import com.edusmartconsult.hospitality.data.net.ApiException
import kotlinx.serialization.json.JsonObject

val LocalContainer: ProvidableCompositionLocal<AppContainer> = compositionLocalOf { error("no container") }
val LocalSnack: ProvidableCompositionLocal<SnackbarHostState> = compositionLocalOf { error("no snackbar") }

@Composable fun ctx(): AppContext? = LocalContainer.current.store.ctx.collectAsState().value

@Composable
fun <T> rememberDocs(entity: String, transform: (List<JsonObject>) -> List<T>): State<List<T>> {
    val store = LocalContainer.current.store
    val flow = remember(entity) { kotlinx.coroutines.flow.flow { store.observe(entity).collect { emit(transform(it)) } } }
    return flow.collectAsState(initial = emptyList())
}

@Composable fun rememberDocs(entity: String): State<List<JsonObject>> = rememberDocs(entity) { it }

/** Server error text, or the offline notice. */
@Composable
fun errorText(e: Throwable): String = when {
    e is ApiException && e.isOffline -> stringResource(R.string.offline_required)
    e is ApiException -> e.message ?: stringResource(R.string.error_generic)
    else -> e.message ?: stringResource(R.string.error_generic)
}

@Composable
fun Loading(modifier: Modifier = Modifier.fillMaxSize()) {
    Box(modifier, contentAlignment = Alignment.Center) { CircularProgressIndicator() }
}

@Composable
fun Empty(text: String = stringResource(R.string.no_data), modifier: Modifier = Modifier) {
    Box(modifier.fillMaxWidth().padding(32.dp), contentAlignment = Alignment.Center) {
        Text(text, color = MaterialTheme.colorScheme.onSurfaceVariant)
    }
}

@Composable
fun SectionTitle(text: String, modifier: Modifier = Modifier) {
    Text(text, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold, modifier = modifier.padding(vertical = 8.dp))
}

@Composable
fun StatCard(title: String, value: String, modifier: Modifier = Modifier, sub: String? = null) {
    Card(modifier, colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface), elevation = CardDefaults.cardElevation(1.dp)) {
        Column(Modifier.padding(14.dp)) {
            Text(title, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Text(value, style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
            if (sub != null) Text(sub, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}

@Composable
fun ListCard(onClick: (() -> Unit)? = null, modifier: Modifier = Modifier, content: @Composable () -> Unit) {
    Card(modifier.fillMaxWidth().let { if (onClick != null) it.clickable(onClick = onClick) else it }, elevation = CardDefaults.cardElevation(1.dp)) {
        Column(Modifier.padding(14.dp)) { content() }
    }
}

@Composable
fun Field(value: String, onChange: (String) -> Unit, label: String, modifier: Modifier = Modifier.fillMaxWidth(), number: Boolean = false, phone: Boolean = false, password: Boolean = false, singleLine: Boolean = true) {
    OutlinedTextField(
        value = value, onValueChange = onChange, label = { Text(label) }, singleLine = singleLine, modifier = modifier,
        keyboardOptions = KeyboardOptions(keyboardType = when { number -> KeyboardType.Number; phone -> KeyboardType.Phone; password -> KeyboardType.Password; else -> KeyboardType.Text }),
        visualTransformation = if (password) androidx.compose.ui.text.input.PasswordVisualTransformation() else androidx.compose.ui.text.input.VisualTransformation.None,
    )
}

/** Simple dropdown over (id, label) pairs. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun Picker(label: String, options: List<Pair<String, String>>, selected: String?, onSelect: (String) -> Unit, modifier: Modifier = Modifier.fillMaxWidth()) {
    var open by remember { mutableStateOf(false) }
    val current = options.firstOrNull { it.first == selected }?.second ?: ""
    ExposedDropdownMenuBox(expanded = open, onExpandedChange = { open = it }, modifier = modifier) {
        OutlinedTextField(value = current, onValueChange = {}, readOnly = true, label = { Text(label) }, singleLine = true,
            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = open) }, modifier = Modifier.menuAnchor().fillMaxWidth())
        ExposedDropdownMenu(expanded = open, onDismissRequest = { open = false }) {
            options.forEach { (id, text) -> DropdownMenuItem(text = { Text(text) }, onClick = { onSelect(id); open = false }) }
        }
    }
}

@Composable
fun PrimaryButton(text: String, onClick: () -> Unit, modifier: Modifier = Modifier, enabled: Boolean = true) {
    Button(onClick = onClick, enabled = enabled, modifier = modifier) { Text(text) }
}

@Composable
fun ActionButton(text: String, modifier: Modifier, enabled: Boolean = true, onClick: () -> Unit) {
    Button(onClick = onClick, enabled = enabled, modifier = modifier) { Text(text) }
}

@Composable
fun Row2(modifier: Modifier = Modifier, content: @Composable androidx.compose.foundation.layout.RowScope.() -> Unit) {
    Row(modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically, content = content)
}

fun contentPadding() = PaddingValues(16.dp)

/** Label with the company's override ("Guest" -> "Visitor") when set. */
@Composable
fun label(key: String, default: String): String {
    val c = ctx()
    val lang = androidx.compose.ui.platform.LocalConfiguration.current.locales[0]?.language ?: "en"
    return c?.term(key, lang) ?: default
}

@Composable
fun Provide(container: AppContainer, snack: SnackbarHostState, content: @Composable () -> Unit) {
    CompositionLocalProvider(LocalContainer provides container, LocalSnack provides snack, content = content)
}

fun JsonObject.title(): String = str("name") ?: str("number") ?: str("title") ?: str("id")?.take(8) ?: ""
