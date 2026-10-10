package com.edusmartconsult.hospitality.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material3.Checkbox
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshots.SnapshotStateMap
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.runtime.LaunchedEffect
import com.edusmartconsult.hospitality.R
import com.edusmartconsult.hospitality.core.bool
import com.edusmartconsult.hospitality.core.obj
import com.edusmartconsult.hospitality.core.objs
import com.edusmartconsult.hospitality.core.str
import com.edusmartconsult.hospitality.core.strs
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneOffset
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject

/** Read-only text field that opens a date picker. Value is ISO yyyy-MM-dd. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DateField(value: String, onChange: (String) -> Unit, label: String, modifier: Modifier = Modifier.fillMaxWidth()) {
    var open by remember { mutableStateOf(false) }
    val source = remember { MutableInteractionSource() }
    val pressed by source.collectIsPressedAsState()
    LaunchedEffect(pressed) { if (pressed) open = true }
    OutlinedTextField(value = value, onValueChange = {}, readOnly = true, label = { Text(label) }, singleLine = true, modifier = modifier, interactionSource = source)
    if (open) {
        val initial = runCatching { LocalDate.parse(value).atStartOfDay().toInstant(ZoneOffset.UTC).toEpochMilli() }.getOrNull()
        val state = rememberDatePickerState(initialSelectedDateMillis = initial)
        DatePickerDialog(onDismissRequest = { open = false },
            confirmButton = { TextButton(onClick = { state.selectedDateMillis?.let { onChange(Instant.ofEpochMilli(it).atZone(ZoneOffset.UTC).toLocalDate().toString()) }; open = false }) { Text(stringResource(R.string.confirm_ok)) } },
            dismissButton = { TextButton(onClick = { open = false }) { Text(stringResource(R.string.cancel)) } }) { DatePicker(state = state) }
    }
}

/**
 * Renders the company's own custom fields for an entity (defined in the web console) and
 * returns their values as the `meta` object the server validates.
 */
@Composable
fun CustomFieldsForm(entity: String, values: SnapshotStateMap<String, String>) {
    val c = ctx()
    val lang = LocalConfiguration.current.locales[0]?.language ?: "en"
    val fields = c?.customFieldsFor(entity)?.filter { it.bool("is_active", true) } ?: return
    if (fields.isEmpty()) return
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        fields.forEach { f ->
            val key = f.str("key") ?: return@forEach
            val label = (f.obj("label")?.str(lang) ?: f.obj("label")?.str("en") ?: key) + if (f.bool("required")) " *" else ""
            when (f.str("type")) {
                "select" -> Picker(label, f.strs("options").map { it to it }, values[key], { values[key] = it })
                "boolean" -> Row(verticalAlignment = androidx.compose.ui.Alignment.CenterVertically) { Checkbox(checked = values[key] == "true", onCheckedChange = { values[key] = it.toString() }); Text(label) }
                "date" -> DateField(values[key] ?: "", { values[key] = it }, label)
                "number" -> Field(values[key] ?: "", { values[key] = it.filter { ch -> ch.isDigit() || ch == '.' } }, label, number = true)
                else -> Field(values[key] ?: "", { values[key] = it }, label, singleLine = f.str("type") != "textarea")
            }
        }
    }
}

fun customFieldsMeta(entity: String, c: com.edusmartconsult.hospitality.data.AppContext?, values: Map<String, String>): JsonObject = buildJsonObject {
    c?.customFieldsFor(entity)?.forEach { f ->
        val key = f.str("key") ?: return@forEach
        val v = values[key]?.takeIf { it.isNotBlank() } ?: return@forEach
        val el: JsonElement = when (f.str("type")) {
            "number" -> JsonPrimitive(v.toDoubleOrNull() ?: 0.0)
            "boolean" -> JsonPrimitive(v == "true")
            else -> JsonPrimitive(v)
        }
        put(key, el)
    }
}
