package com.edusmartconsult.hospitality

import android.app.Application
import com.edusmartconsult.hospitality.data.sync.SyncWorker

class App : Application() {
    lateinit var container: AppContainer
        private set

    override fun onCreate() {
        super.onCreate()
        container = AppContainer(this)
        SyncWorker.schedulePeriodic(this)
    }
}
