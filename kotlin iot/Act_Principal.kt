package com.example.celulariot

import android.content.Intent
import android.os.Bundle
import android.widget.Button
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat

private lateinit var btncrudusu: Button
private lateinit var btnsensor: Button
private lateinit var btnequipo: Button
class Act_Principal : AppCompatActivity() {

    companion object {
        const val EXTRA_IDUSU = "IdUsu"
        const val EXTRA_PINSET = "pin_set"
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContentView(R.layout.activity_act_principal)


        btncrudusu = findViewById(R.id.btncrudusu)
        btnsensor  = findViewById(R.id.btnsensor)
        btnequipo  = findViewById(R.id.btnequipo)

        btncrudusu.setOnClickListener {
            val intent = Intent(this, Act_CRUD_Usuario::class.java)
            startActivity(intent)
        }
        btnsensor.setOnClickListener {
            val intent = Intent(this, Act_Sensores::class.java)
            startActivity(intent)
        }
    }
}