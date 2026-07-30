package com.example.celulariot

import android.content.Intent
import android.os.Bundle
import android.widget.Button
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import kotlin.jvm.java

private lateinit var btnregusuario: Button
private lateinit var btnlistusu: Button

class Act_CRUD_Usuario : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContentView(R.layout.activity_act_crud_usuario)
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.txtPinRecovery)) { v, insets ->
            val systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars())
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom)
            insets
        }

    btnregusuario= findViewById(R.id.btnregusuario)
    btnlistusu= findViewById(R.id.btnlistusu)

    btnregusuario.setOnClickListener {
        val intent = Intent(this, Act_Registro::class.java)
        startActivity(intent)
    }
    btnlistusu.setOnClickListener {
        val listado = Intent(this, Act_Listar::class.java)
        startActivity(listado)
    }
    }
}