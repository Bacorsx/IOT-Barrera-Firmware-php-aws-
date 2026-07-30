package com.example.celulariot

import android.content.Intent
import android.os.Bundle
import android.text.InputFilter
import android.text.Spanned
import android.util.Patterns
import android.widget.Button
import android.widget.EditText
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import com.android.volley.AuthFailureError
import com.android.volley.DefaultRetryPolicy
import com.android.volley.Request
import com.android.volley.RequestQueue
import com.android.volley.toolbox.StringRequest
import com.android.volley.toolbox.Volley
import com.example.celulariot.ui.Alerts
import java.text.Normalizer
import java.util.regex.Pattern

class Act_Registro : AppCompatActivity() {

    private lateinit var nombre: EditText
    private lateinit var apellido: EditText
    private lateinit var email: EditText
    private lateinit var claves: EditText
    private lateinit var btnRegis: Button
    private lateinit var queue: RequestQueue

    companion object {
        private const val BASE_URL = "http://3.214.181.94/"
        private const val MAX_NAME_LEN = 60
        private const val MAX_USER_LEN = 120
        private const val MAX_PASS_LEN = 128
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContentView(R.layout.activity_act_registro)

        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.txtPinRecovery)) { v, insets ->
            val bars = insets.getInsets(WindowInsetsCompat.Type.systemBars())
            v.setPadding(bars.left, bars.top, bars.right, bars.bottom); insets
        }

        nombre   = findViewById(R.id.txtnombre)
        apellido = findViewById(R.id.txtapellido)
        email    = findViewById(R.id.txtemail)
        claves   = findViewById(R.id.txtclave)
        btnRegis = findViewById(R.id.btn_regis)
        queue    = Volley.newRequestQueue(this)

        // -------- InputFilters (longitud + bloqueo de emojis/control chars) -------
        val safeCharsFilter = OnlySafeTextFilter()
        nombre.filters   = arrayOf(InputFilter.LengthFilter(MAX_NAME_LEN), safeCharsFilter)
        apellido.filters = arrayOf(InputFilter.LengthFilter(MAX_NAME_LEN), safeCharsFilter)
        email.filters    = arrayOf(InputFilter.LengthFilter(MAX_USER_LEN), safeCharsFilter)
        claves.filters   = arrayOf(InputFilter.LengthFilter(MAX_PASS_LEN), NoSpacesFilter(), safeCharsFilter)

        btnRegis.setOnClickListener {
            // Normaliza entradas
            val nom = normalizeName(nombre.text.toString())
            val ape = normalizeName(apellido.text.toString())
            val usu = email.text.toString().trim()
            val cla = claves.text.toString()

            // Validaciones
            if (!isValidName(nom)) {
                Alerts.warn(this, "Nombre inválido", "Usa solo letras, espacios, guion o apóstrofe (máx. $MAX_NAME_LEN).")
                return@setOnClickListener
            }
            if (!isValidName(ape)) {
                Alerts.warn(this, "Apellido inválido", "Usa solo letras, espacios, guion o apóstrofe (máx. $MAX_NAME_LEN).")
                return@setOnClickListener
            }
            if (!isValidEmail(usu)) {
                Alerts.warn(this, "Email inválido", "Ingresa un correo válido (máx. $MAX_USER_LEN).")
                return@setOnClickListener
            }
            if (!isStrongPassword(cla)) {
                Alerts.warn(
                    this, "Clave insegura",
                    "Mín. 8 caracteres, con mayúscula, minúscula, dígito y símbolo; sin espacios."
                )
                return@setOnClickListener
            }

            // Rate-limit simple: deshabilitar botón durante envío
            btnRegis.isEnabled = false
            registrarRemoto(nom, ape, usu, cla) {
                btnRegis.isEnabled = true
            }
        }

        supportActionBar?.setDisplayHomeAsUpEnabled(true)
    }

    // ---------------------- Validadores / Sanitizadores --------------------------

    // Acepta letras latinas (con acentos), espacios, guion y apóstrofe; colapsa espacios
    private fun normalizeName(s: String): String {
        val trimmed = s.trim().replace(Regex("\\s+"), " ")
        return trimmed
    }

    // Letras (incluye acentos), espacios, guion/apóstrofe. Nada de números o símbolos extra.
    private val NAME_REGEX: Pattern = Pattern.compile("^[\\p{L}][\\p{L}\\p{M} '\\-]{0,59}$")

    private fun isValidName(s: String): Boolean {
        if (s.isBlank()) return false
        // Normaliza unicode a forma compuesta (evita rarezas)
        val n = Normalizer.normalize(s, Normalizer.Form.NFC)
        return NAME_REGEX.matcher(n).matches()
    }

    private fun isValidEmail(mail: String): Boolean {
        if (mail.isBlank() || mail.length > MAX_USER_LEN) return false
        return Patterns.EMAIL_ADDRESS.matcher(mail).matches()
    }

    // Política de contraseña: >=8, 1 mayúscula, 1 minúscula, 1 dígito, 1 símbolo, sin espacios
    private val PASS_REGEX: Pattern =
        Pattern.compile("^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^\\w\\s]).{8,128}$")

    private fun isStrongPassword(pass: String): Boolean {
        if (pass.isBlank() || pass.length > MAX_PASS_LEN || pass.contains(" ")) return false
        return PASS_REGEX.matcher(pass).matches()
    }

    // Bloquea surrogates (emojis), controles y caracteres invisibles típicos
    private class OnlySafeTextFilter : InputFilter {
        override fun filter(
            source: CharSequence, start: Int, end: Int,
            dest: Spanned, dstart: Int, dend: Int
        ): CharSequence? {
            val sb = StringBuilder()
            for (i in start until end) {
                val ch = source[i]
                val type = Character.getType(ch)
                val isControl = type == Character.CONTROL.toInt() || type == Character.FORMAT.toInt()
                val isSurrogate = Character.isSurrogate(ch)
                if (!isControl && !isSurrogate) sb.append(ch)
            }
            // null = acepta original; cadena = reemplaza; "" = bloquea
            return if (sb.length == end - start) null else sb.toString()
        }
    }

    // Evita espacios en contraseña
    private class NoSpacesFilter : InputFilter {
        override fun filter(
            source: CharSequence, start: Int, end: Int,
            dest: Spanned, dstart: Int, dend: Int
        ): CharSequence? {
            return if (source.any { it == ' ' }) source.toString().replace(" ", "") else null
        }
    }

    // -------------------------- Red (Volley) ------------------------------------

    private fun registrarRemoto(nombre: String, apellido: String, usuario: String, clave: String, onFinally: () -> Unit) {
        val url = "${BASE_URL}apiregusu.php"
        val loading = Alerts.loading(this, "Registrando...")

        val req = object : StringRequest(
            Request.Method.POST, url,
            { resp ->
                loading.dismissWithAnimation()
                // Puedes parsear el JSON: {"ok":1,"IdUsu":...} / {"ok":0,"error":"usuario_existente"}
                if (resp.contains("\"ok\":1")) {
                    Alerts.success(this, "Registro exitoso")
                    startActivity(Intent(this, MainActivity::class.java))
                    finish()
                } else {
                    Alerts.warn(this, "Registro no completado", resp.take(200))
                }
                onFinally()
            },
            { err ->
                loading.dismissWithAnimation()
                Alerts.error(this, "Error en registro", "Revisa conexión o servidor")
                onFinally()
            }
        ) {
            @Throws(AuthFailureError::class)
            override fun getParams(): MutableMap<String, String> =
                hashMapOf(
                    "nombre" to nombre,
                    "apellido" to apellido,
                    "usuario" to usuario,   // email como usuario
                    "clave" to clave
                )
        }.apply {
            // RetryPolicy: limita reintentos para evitar floods
            retryPolicy = DefaultRetryPolicy(
                8000,  // timeout ms
                1,     // reintentos
                1.0f   // backoff
            )
        }

        queue.add(req)
    }
}
