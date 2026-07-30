package com.example.celulariot

import android.content.Intent
import android.os.Bundle
import android.text.InputFilter
import android.text.Spanned
import android.util.Patterns
import android.view.inputmethod.EditorInfo
import android.widget.Button
import android.widget.EditText
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import com.android.volley.DefaultRetryPolicy
import com.android.volley.Request
import com.android.volley.RequestQueue
import com.android.volley.toolbox.JsonObjectRequest
import com.android.volley.toolbox.Volley
import com.example.celulariot.ui.Alerts
import org.json.JSONObject

class Recuperar_Cont : AppCompatActivity() {

    private lateinit var txtUsuario: EditText
    private lateinit var txtPin: EditText
    private lateinit var btnEnviar: Button   // Enviar PIN al correo
    private lateinit var btnPin: Button      // Validar PIN e ingresar
    private lateinit var queue: RequestQueue

    companion object {
        private const val BASE_URL = "http://3.214.181.94/"
        private const val EMAIL_ENDPOINT = "apirecuperar_email.php"
        private const val PIN_ENDPOINT   = "apirecuperar_pin.php"

        private const val MAX_USER_LEN = 100
        private const val PIN_LEN = 6
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContentView(R.layout.activity_recuperar_cont)
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(android.R.id.content)) { v, insets ->
            val systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars())
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom)
            insets
        }

        queue = Volley.newRequestQueue(this)

        txtUsuario = findViewById(R.id.txtUsuario)
        txtPin     = findViewById(R.id.txtPin)
        btnEnviar  = findViewById(R.id.btnEnviar) // debe decir algo como "Enviar PIN"
        btnPin     = findViewById(R.id.btnPin)    // botón nuevo en tu layout: "Validar PIN"

        // filtros
        txtUsuario.filters = arrayOf(InputFilter.LengthFilter(MAX_USER_LEN))
        txtPin.filters     = arrayOf(InputFilter.LengthFilter(PIN_LEN), DigitsOnlyFilter())

        // 1) Enviar PIN al correo
        btnEnviar.setOnClickListener {
            val usu = txtUsuario.text.toString().trim().lowercase()
            if (usu.isEmpty()) {
                Alerts.warn(this, "Faltan datos", "Ingresa tu correo (usuario).")
                return@setOnClickListener
            }
            if (!Patterns.EMAIL_ADDRESS.matcher(usu).matches()) {
                Alerts.warn(this, "Correo inválido", "Revisa el formato del correo.")
                return@setOnClickListener
            }
            enviarPinPorCorreo(usu)
        }

        // 2) Validar PIN e ingresar
        btnPin.setOnClickListener {
            val usu = txtUsuario.text.toString().trim().lowercase()
            val pin = txtPin.text.toString().trim()

            if (usu.isEmpty()) {
                Alerts.warn(this, "Faltan datos", "Ingresa tu correo.")
                return@setOnClickListener
            }
            if (!Patterns.EMAIL_ADDRESS.matcher(usu).matches()) {
                Alerts.warn(this, "Correo inválido", "Revisa el formato del correo.")
                return@setOnClickListener
            }
            if (pin.length != PIN_LEN || !pin.all { it.isDigit() }) {
                Alerts.warn(this, "PIN inválido", "El PIN debe tener 6 dígitos.")
                return@setOnClickListener
            }
            validarPinYEntrar(usu, pin)
        }

        // permitir validar con acción del teclado en el campo PIN
        txtPin.setOnEditorActionListener { _, actionId, _ ->
            if (actionId == EditorInfo.IME_ACTION_DONE) {
                btnPin.performClick(); true
            } else false
        }
    }

    /** Llama apirecuperar_email.php para que el servidor genere y envíe el PIN al correo */
    private fun enviarPinPorCorreo(usu: String) {
        val loading = Alerts.loading(this, "Enviando PIN…")
        val url = BASE_URL + EMAIL_ENDPOINT
        val payload = JSONObject(mapOf("usu" to usu))

        val req = JsonObjectRequest(
            Request.Method.POST, url, payload,
            { json ->
                loading.dismissWithAnimation()
                val ok = json.optInt("ok", 0)
                if (ok == 1) {
                    Alerts.info(
                        this,
                        "PIN enviado",
                        "Revisa tu correo y escribe aquí el PIN de 6 dígitos."
                    )
                } else {
                    val msg = json.optString("error_desc",
                        json.optString("notice", "No se pudo enviar el correo. Intenta de nuevo."))
                    Alerts.error(this, "No se pudo enviar", msg)
                }
            },
            { e ->
                loading.dismissWithAnimation()
                Alerts.error(
                    this,
                    "Error de red",
                    "Código: ${e.networkResponse?.statusCode ?: -1}"
                )
            }
        ).apply {
            retryPolicy = DefaultRetryPolicy(8000, 1, 1.0f)
            setShouldCache(false)
        }
        queue.add(req)
    }

    /** Llama apirecuperar_pin.php para validar el PIN y luego ingresa a la app para cambiar la clave */
    private fun validarPinYEntrar(usu: String, pin: String) {
        val loading = Alerts.loading(this, "Validando PIN…")
        val url = BASE_URL + PIN_ENDPOINT
        val payload = JSONObject(mapOf("usu" to usu, "pin" to pin))

        val req = JsonObjectRequest(
            Request.Method.POST, url, payload,
            { json ->
                loading.dismissWithAnimation()
                val ok = json.optInt("ok", 0)
                if (ok == 1) {
                    // Idealmente el backend devuelve IdUsu y quizá un flag must_change_pwd
                    val idUsu = json.optInt("IdUsu", 0)

                    startActivity(
                        Intent(this, Act_modificar_eliminar::class.java)
                            .putExtra("Id", idUsu)              // lo único obligatorio
                            .putExtra("Nombre", "")             // opcional (si no tienes el dato)
                            .putExtra("Apellido", "")           // opcional (si no tienes el dato)
                            .putExtra("fromRecovery", true)     // para que la otra pantalla permita cambiar solo la clave
                    )
                    finish()
                } else {
                    val msg = json.optString("error_desc", "PIN incorrecto o vencido.")
                    Alerts.error(this, "PIN inválido", msg)
                }
            },
            { e ->
                loading.dismissWithAnimation()
                Alerts.error(
                    this,
                    "Error de red",
                    "Código: ${e.networkResponse?.statusCode ?: -1}"
                )
            }
        ).apply {
            retryPolicy = DefaultRetryPolicy(8000, 1, 1.0f)
            setShouldCache(false)
        }
        queue.add(req)
    }

    private class DigitsOnlyFilter : InputFilter {
        override fun filter(
            source: CharSequence, start: Int, end: Int,
            dest: Spanned, dstart: Int, dend: Int
        ): CharSequence? {
            for (i in start until end) if (!source[i].isDigit()) return ""
            return null
        }
    }
}
