package com.edusmartconsult.hospitality.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Sync
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.PointOfSale
import androidx.compose.material.icons.filled.Hotel
import androidx.compose.material.icons.filled.Explore
import androidx.compose.material.icons.filled.MoreHoriz
import androidx.compose.material3.Badge
import androidx.compose.material3.BadgedBox
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.edusmartconsult.hospitality.AppContainer
import com.edusmartconsult.hospitality.R
import kotlinx.coroutines.launch

@Composable
fun AppRoot(container: AppContainer) {
    val loggedIn by container.loggedIn.collectAsState()
    val snack = remember { SnackbarHostState() }
    Provide(container, snack) {
        if (!loggedIn) LoginScreen() else MainShell()
    }
}

private data class Dest(val route: String, val labelRes: Int, val icon: ImageVector)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun MainShell() {
    val container = LocalContainer.current
    val store = container.store
    val c = ctx()
    val nav = rememberNavController()
    val scope = rememberCoroutineScope()
    val snack = LocalSnack.current
    val backStack by nav.currentBackStackEntryAsState()
    val route = backStack?.destination?.route ?: "home"
    val pending by store.pendingCount.collectAsState(initial = 0)
    val failed by store.failedCount.collectAsState(initial = 0)
    val syncStatus by container.sync.status.collectAsState()

    // refresh permissions/modules whenever the shell opens (they can change on the server)
    LaunchedEffect(Unit) { runCatching { store.refreshContext() }; container.sync.syncNow() }

    val dests = buildList {
        add(Dest("home", R.string.nav_home, Icons.Filled.Home))
        if (c?.has("pos", "pos.view") == true) add(Dest("pos", R.string.nav_pos, Icons.Filled.PointOfSale))
        if (c?.has("rooms", "rooms.view") == true) add(Dest("rooms", R.string.nav_rooms, Icons.Filled.Hotel))
        if (c?.has("tourism", "tourism.view") == true) add(Dest("tours", R.string.nav_tours, Icons.Filled.Explore))
        add(Dest("more", R.string.nav_more, Icons.Filled.MoreHoriz))
    }
    val topLevel = dests.any { it.route == route }
    val title = when {
        route.startsWith("pos/order/") -> stringResource(R.string.orders_title)
        route == "pos/orders" -> stringResource(R.string.orders_title)
        route == "pos/kitchen" -> stringResource(R.string.kitchen_screen)
        route.startsWith("rooms/") -> stringResource(R.string.nav_rooms)
        route.startsWith("tours/") -> stringResource(R.string.nav_tours)
        route == "tasks" -> stringResource(R.string.my_tasks)
        route == "clock" -> stringResource(R.string.clock)
        route == "settings" -> stringResource(R.string.settings)
        route == "outbox" -> stringResource(R.string.outbox_title)
        else -> c?.tenantName ?: stringResource(R.string.nav_home)
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(title) },
                navigationIcon = { if (!topLevel) IconButton(onClick = { nav.popBackStack() }) { Icon(Icons.AutoMirrored.Filled.ArrowBack, stringResource(R.string.back)) } },
                actions = {
                    IconButton(onClick = { scope.launch { if (!container.sync.syncNow()) snack.showSnackbar(container.sync.status.value.lastError ?: "") } }) {
                        BadgedBox(badge = { if (pending + failed > 0) Badge { Text((pending + failed).toString()) } }) { Icon(Icons.Filled.Sync, stringResource(R.string.sync_now)) }
                    }
                },
            )
        },
        bottomBar = {
            NavigationBar {
                dests.forEach { d ->
                    NavigationBarItem(
                        selected = route == d.route || (d.route != "home" && route.startsWith(d.route + "/")),
                        onClick = { nav.navigate(d.route) { popUpTo("home"); launchSingleTop = true } },
                        icon = { Icon(d.icon, null) }, label = { Text(stringResource(d.labelRes)) },
                    )
                }
            }
        },
        snackbarHost = { SnackbarHost(LocalSnack.current) },
    ) { pad ->
        Column(Modifier.padding(pad).fillMaxSize()) {
            if (syncStatus.lastError == "offline" || (pending > 0 && syncStatus.lastError != null)) {
                Text(stringResource(R.string.offline_banner), style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onPrimaryContainer,
                    modifier = Modifier.fillMaxWidth().background(MaterialTheme.colorScheme.primaryContainer).padding(horizontal = 16.dp, vertical = 6.dp))
            }
            if (c?.tenantStatus == "suspended") {
                Text(stringResource(R.string.subscription_banner), color = MaterialTheme.colorScheme.onError, modifier = Modifier.fillMaxWidth().background(MaterialTheme.colorScheme.error).padding(horizontal = 16.dp, vertical = 6.dp))
            }
            Box(Modifier.weight(1f)) { Routes(nav) }
        }
    }
}

@Composable
private fun Routes(nav: NavHostController) {
    NavHost(nav, startDestination = "home") {
        composable("home") { HomeScreen(onNavigate = { nav.navigate(it) }) }
        composable("pos") { PosScreen(onOrders = { nav.navigate("pos/orders") }, onOpen = { nav.navigate("pos/order/$it") }) }
        composable("pos/orders") { OrdersScreen(onOpen = { nav.navigate("pos/order/$it") }) }
        composable("pos/order/{id}", listOf(navArgument("id") { type = NavType.StringType })) { OrderDetailScreen(it.arguments?.getString("id") ?: "") }
        composable("pos/kitchen") { KitchenScreen() }
        composable("rooms") { RoomsScreen(onNew = { nav.navigate("rooms/new") }, onOpen = { nav.navigate("rooms/res/$it") }) }
        composable("rooms/new") { NewReservationScreen(onDone = { nav.popBackStack() }) }
        composable("rooms/res/{id}", listOf(navArgument("id") { type = NavType.StringType })) { ReservationDetailScreen(it.arguments?.getString("id") ?: "") }
        composable("tours") { ToursScreen(onNew = { nav.navigate("tours/new") }, onOpen = { nav.navigate("tours/booking/$it") }) }
        composable("tours/new") { NewBookingScreen(onDone = { nav.popBackStack() }) }
        composable("tours/booking/{id}", listOf(navArgument("id") { type = NavType.StringType })) { BookingDetailScreen(it.arguments?.getString("id") ?: "") }
        composable("tasks") { TasksScreen() }
        composable("clock") { ClockScreen() }
        composable("more") { MoreScreen(onNavigate = { nav.navigate(it) }) }
        composable("settings") { SettingsScreen() }
        composable("outbox") { OutboxScreen() }
    }
}
