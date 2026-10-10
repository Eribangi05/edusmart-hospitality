package com.edusmartconsult.hospitality

import com.edusmartconsult.hospitality.data.AuthStore
import com.edusmartconsult.hospitality.data.net.Api
import com.edusmartconsult.hospitality.data.net.ApiException
import com.edusmartconsult.hospitality.data.net.SessionEvent
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.withTimeout
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Assert.fail
import org.junit.Before
import org.junit.Test

private class FakeStore(override var baseUrl: String) : AuthStore {
    override val deviceId = "dev-1"
    override var accessToken: String? = null
    override var refreshToken: String? = null
    var cleared = false
    override fun clear() { accessToken = null; refreshToken = null; cleared = true }
}

class ApiTest {
    private lateinit var server: MockWebServer
    private lateinit var store: FakeStore
    private lateinit var api: Api

    @Before fun setUp() {
        server = MockWebServer().also { it.start() }
        store = FakeStore(server.url("/api/v1/").toString())
        api = Api(store)
    }

    @After fun tearDown() { runCatching { server.shutdown() } }

    private fun json(code: Int, body: String) = MockResponse().setResponseCode(code).setHeader("Content-Type", "application/json").setBody(body)

    @Test fun loginStoresTokensAndSendsDevice() = runTest {
        server.enqueue(json(200, """{"access_token":"A1","refresh_token":"R1","expires_in":900}"""))
        val r = api.login("a@b.c", "pw")
        assertTrue(r is Api.LoginResult.Success)
        assertEquals("A1", store.accessToken); assertEquals("R1", store.refreshToken)
        val sent = server.takeRequest().body.readUtf8()
        assertTrue(sent.contains("\"platform\":\"android\"")); assertTrue(sent.contains("dev-1"))
    }

    @Test fun twoFactorChallengeIsSurfaced() = runTest {
        server.enqueue(json(202, """{"challenge_id":"c-1"}"""))
        val r = api.login("a@b.c", "pw")
        assertEquals("c-1", (r as Api.LoginResult.TwoFactor).challengeId)
        assertNull(store.accessToken)
    }

    @Test fun errorEnvelopeIsParsed() = runTest {
        server.enqueue(json(422, """{"error":{"code":"invalid_credentials","message":"Invalid email or password."}}"""))
        try { api.login("a@b.c", "bad"); fail() } catch (e: ApiException) { assertEquals("invalid_credentials", e.code); assertEquals(422, e.status) }
    }

    @Test fun expiredAccessTokenIsRefreshedOnceAndRequestRetried() = runTest {
        store.accessToken = "old"; store.refreshToken = "R1"
        server.enqueue(json(401, """{"error":{"code":"unauthenticated","message":"Unauthenticated."}}"""))
        server.enqueue(json(200, """{"access_token":"new","refresh_token":"R2","expires_in":900}"""))
        server.enqueue(json(200, """{"ok":true}"""))
        api.get("me/context")
        assertEquals("new", store.accessToken); assertEquals("R2", store.refreshToken)
        assertEquals("Bearer old", server.takeRequest().getHeader("Authorization"))
        assertEquals("/api/v1/auth/refresh", server.takeRequest().path)
        assertEquals("Bearer new", server.takeRequest().getHeader("Authorization"))
    }

    @Test fun failedRefreshSignsTheUserOut() = runBlocking {
        store.accessToken = "old"; store.refreshToken = "R1"
        server.enqueue(json(401, """{"error":{"code":"unauthenticated","message":"x"}}"""))
        server.enqueue(json(401, """{"error":{"code":"device_revoked","message":"revoked"}}"""))
        val event = kotlinx.coroutines.CoroutineScope(kotlinx.coroutines.Dispatchers.Default).let { scope ->
            val d = kotlinx.coroutines.CompletableDeferred<SessionEvent>()
            val job = scope.launch { d.complete(api.events.first()) }
            try { api.get("me/context"); fail() } catch (e: ApiException) { /* expected */ }
            withTimeout(5000) { d.await() }.also { job.cancel() }
        }
        assertTrue(event is SessionEvent.LoggedOut)
        assertTrue(store.cleared)
    }

    @Test fun deviceRevokedClearsSessionWithoutRefreshAttempt() = runBlocking {
        store.accessToken = "t"; store.refreshToken = "R1"
        server.enqueue(json(401, """{"error":{"code":"device_revoked","message":"This device has been revoked."}}"""))
        try { api.get("orders"); fail() } catch (e: ApiException) { assertEquals("device_revoked", e.code) }
        assertTrue(store.cleared)
        assertEquals(1, server.requestCount)
    }

    @Test fun networkFailureBecomesOfflineException() = runTest {
        server.shutdown()
        try { api.get("health"); fail() } catch (e: ApiException) { assertTrue(e.isOffline); assertFalse(store.cleared) }
    }
}
