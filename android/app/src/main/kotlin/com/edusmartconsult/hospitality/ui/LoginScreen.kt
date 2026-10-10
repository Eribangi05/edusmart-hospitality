package com.edusmartconsult.hospitality.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.edusmartconsult.hospitality.BuildConfig
import com.edusmartconsult.hospitality.R
import com.edusmartconsult.hospitality.data.net.Api
import com.edusmartconsult.hospitality.data.net.ApiException
import kotlinx.coroutines.launch

@Composable
fun LoginScreen() {
    val container = LocalContainer.current
    val scope = rememberCoroutineScope()
    val notice by container.notice.collectAsState()
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var challenge by remember { mutableStateOf<String?>(null) }
    var code by remember { mutableStateOf("") }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var server by remember { mutableStateOf(container.session.baseUrl) }
    val wrongCreds = stringResource(R.string.invalid_credentials)
    val generic = stringResource(R.string.error_generic)
    val offline = stringResource(R.string.offline_required)

    fun fail(e: Exception) {
        error = when {
            e is ApiException && e.isOffline -> offline
            e is ApiException && e.code == "invalid_credentials" -> wrongCreds
            e is ApiException -> e.message
            else -> generic
        }
    }

    Scaffold { pad ->
        Column(Modifier.padding(pad).fillMaxSize().verticalScroll(rememberScrollState()).padding(24.dp), verticalArrangement = Arrangement.spacedBy(14.dp, Alignment.CenterVertically)) {
            Text("Edusmart Hospitality", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.primary)
            Text(stringResource(R.string.login_hint), style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            val n = when (notice) { "device_revoked" -> stringResource(R.string.signed_out_revoked); "session_expired", "unauthenticated" -> stringResource(R.string.signed_out_expired); else -> null }
            if (n != null) Text(n, color = MaterialTheme.colorScheme.error)
            if (challenge == null) {
                Field(email, { email = it.trim() }, stringResource(R.string.email))
                Field(password, { password = it }, stringResource(R.string.password), password = true)
            } else {
                Text(stringResource(R.string.two_factor_title), style = MaterialTheme.typography.titleMedium)
                Field(code, { code = it.filter(Char::isDigit).take(6) }, stringResource(R.string.two_factor_code), number = true)
            }
            error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
            PrimaryButton(
                text = if (challenge == null) stringResource(R.string.sign_in) else stringResource(R.string.verify),
                enabled = !busy && (if (challenge == null) email.isNotBlank() && password.isNotBlank() else code.length == 6),
                modifier = Modifier.fillMaxWidth(),
                onClick = {
                    busy = true; error = null
                    if (server.isNotBlank()) container.session.baseUrl = server
                    scope.launch {
                        try {
                            if (challenge == null) {
                                when (val r = container.api.login(email, password)) {
                                    is Api.LoginResult.TwoFactor -> challenge = r.challengeId
                                    Api.LoginResult.Success -> container.onLoggedIn()
                                }
                            } else {
                                container.api.twoFactor(challenge!!, code)
                                container.onLoggedIn()
                            }
                        } catch (e: Exception) { fail(e) }
                        busy = false
                    }
                },
            )
            var showServer by remember { mutableStateOf(BuildConfig.DEBUG) }
            if (!showServer) {
                androidx.compose.material3.TextButton(onClick = { showServer = true }) { Text(stringResource(R.string.server_url)) }
            } else {
                Field(server, { server = it }, stringResource(R.string.server_url))
                Text(stringResource(R.string.server_hint), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}
