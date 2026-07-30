package com.example.celulariot

import android.icu.text.SimpleDateFormat
import android.icu.util.Calendar
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.widget.ImageView
import android.widget.TextView
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import com.android.volley.Request
import com.android.volley.RequestQueue
import com.android.volley.VolleyError
import com.android.volley.toolbox.JsonObjectRequest
import com.android.volley.toolbox.Volley
import org.json.JSONException
import org.json.JSONObject

lateinit var fecha: TextView
lateinit var temp: TextView
lateinit var humedad: TextView
lateinit var image_humedad: ImageView
lateinit var image_temp: ImageView
lateinit var datos: RequestQueue
val mHandler = Handler(Looper.getMainLooper())

class Act_Sensores : AppCompatActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContentView(R.layout.activity_act_sensores)

        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.txtPinRecovery)) { v, insets ->
            val systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars())
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom)
            insets
        }

        fecha = findViewById(R.id.txt_fecha)
        temp = findViewById(R.id.txt_temp)
        humedad = findViewById(R.id.txt_humedad)
        image_temp = findViewById(R.id.image_temp)
        image_humedad = findViewById(R.id.image_humedad)

        datos = Volley.newRequestQueue(this)
        mHandler.post(refrescar)
    }

    fun fecha_hora(): String {
        val c: Calendar = Calendar.getInstance()
        val sdf = SimpleDateFormat("dd MMMM YYYY, hh:mm:ss a")
        return sdf.format(c.time)
    }

    private fun obtenerDatos() {
        val url = "https://www.pnk.cl/muestra_datos.php"
        val request = JsonObjectRequest(
            Request.Method.GET, url, null,
            { response: JSONObject ->
                try {
                    val temperatura = response.getString("temperatura").toFloat()
                    val humedadVal = response.getString("humedad")

                    // Mostrar texto
                    temp.text = "$temperatura °C"
                    humedad.text = "$humedadVal %"

                    // Cambiar imagen según temperatura
                    if (temperatura >= 25.0f) {
                        image_temp.setImageResource(R.drawable.temperatura_alta)
                    } else {
                        image_temp.setImageResource(R.drawable.temperatura_baja)
                    }

                } catch (e: JSONException) {
                    e.printStackTrace()
                }
            },
            { error: VolleyError ->
                error.printStackTrace()
            }
        )
        datos.add(request)
    }

    private val refrescar = object : Runnable {
        override fun run() {
            fecha.text = fecha_hora()
            obtenerDatos()
            mHandler.postDelayed(this, 1000)
        }
    }
}
