package com.edusmartconsult.hospitality.ui

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val Brand = Color(0xFF059669)
private val Light = lightColorScheme(primary = Brand, onPrimary = Color.White, primaryContainer = Color(0xFFD1FAE5), onPrimaryContainer = Color(0xFF064E3B),
    secondary = Color(0xFF0F766E), background = Color(0xFFF8FAFC), surface = Color.White, surfaceVariant = Color(0xFFF1F5F9), error = Color(0xFFDC2626))
private val Dark = darkColorScheme(primary = Color(0xFF34D399), onPrimary = Color(0xFF022C22), primaryContainer = Color(0xFF065F46), onPrimaryContainer = Color(0xFFD1FAE5),
    background = Color(0xFF0F172A), surface = Color(0xFF111827), surfaceVariant = Color(0xFF1F2937), error = Color(0xFFF87171))

@Composable
fun HospitalityTheme(content: @Composable () -> Unit) {
    MaterialTheme(colorScheme = if (isSystemInDarkTheme()) Dark else Light, content = content)
}
