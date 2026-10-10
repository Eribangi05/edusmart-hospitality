package com.edusmartconsult.hospitality

import android.os.Bundle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AppCompatActivity
import com.edusmartconsult.hospitality.ui.AppRoot
import com.edusmartconsult.hospitality.ui.HospitalityTheme

class MainActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        enableEdgeToEdge()
        super.onCreate(savedInstanceState)
        val container = (application as App).container
        setContent { HospitalityTheme { AppRoot(container) } }
    }
}
