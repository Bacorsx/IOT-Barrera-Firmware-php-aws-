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
import com.android.volley.toolbox.StringRequest
import com.android.volley.toolbox.Volley
import com.example.celulariot.ui.Alerts
import org.json.JSONObject

class MainActivity : AppCompatActivity() {

    private lateinit var btnIngresar: Button
    private lateinit var btnRegistrar: Button
    private lateinit var btnRecuperar: Button
    private lateinit var usu: EditText
    private lateinit var clave: EditText
    private lateinit var queue: RequestQueue

    companion object {
        private const val BASE_URL = "http://3.214.181.94/"
        private const val MAX_USER_LEN = 120
        private const val MAX_PASS_LEN = 128
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContentView(R.layout.activity_main)

        ViewCompat.setOnApplyWindowInsetsListener(findViewById(android.R.id.content)) { v, insets ->
            val systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars())
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom)
            insets
        }

        usu = findViewById(R.id.usuario)
        clave = findViewById(R.id.clave)
        btnIngresar = findViewById(R.id.btningresar)
        btnRecuperar = findViewById(R.id.btnrecuperarcontrasena)
        btnRegistrar = findViewById(R.id.btnregistrar)

        queue = Volley.newRequestQueue(this)

        // Filtros de higiene
        val safe = OnlySafeTextFilter()
        usu.filters = arrayOf(InputFilter.LengthFilter(MAX_USER_LEN), safe)
        clave.filters = arrayOf(InputFilter.LengthFilter(MAX_PASS_LEN), NoSpacesFilter(), safe)

        clave.setOnEditorActionListener { _, actionId, _ ->
            if (actionId == EditorInfo.IME_ACTION_DONE) {
                intentarLogin(); true
            } else false
        }
        btnIngresar.setOnClickListener { intentarLogin() }
        btnRecuperar.setOnClickListener { startActivity(Intent(this, Recuperar_Cont::class.java)) }
        btnRegistrar.setOnClickListener { startActivity(Intent(this, Act_Registro::class.java)) }
    }

    private fun intentarLogin() {
        val user = usu.text.toString().trim().lowercase()
        val pass = clave.text.toString()

        if (user.isBlank() || pass.isBlank()) {
            Alerts.warn(this, "Campos vacíos", "Ingresa usuario y contraseña")
            return
        }
        if (user.length > MAX_USER_LEN) {
            Alerts.warn(this, "Usuario muy largo", "Máximo $MAX_USER_LEN caracteres")
            return
        }
        if (!isValidUser(user)) {
            Alerts.warn(this, "Usuario inválido", "Email o usuario (a-z, 0-9, . _ -)")
            return
        }
        if (pass.length > MAX_PASS_LEN) {
            Alerts.warn(this, "Contraseña muy larga", "Máximo $MAX_PASS_LEN")
            return
        }
        if (pass.contains(" ")) {
            Alerts.warn(this, "Contraseña inválida", "Sin espacios")
            return
        }

        btnIngresar.isEnabled = false
        autenticarPOST(user, pass) { btnIngresar.isEnabled = true }
    }

    // POST tipo form (compat con PHP actual)
    private fun autenticarPOST(user: String, pass: String, onFinally: () -> Unit) {
        val url = "${BASE_URL}apiconsultausu.php"
        val loading = Alerts.loading(this, "Autenticando…")

        val req = object : StringRequest(
            Request.Method.POST, url,
            { body ->
                loading.dismissWithAnimation()
                try {
                    val j = JSONObject(body)
                    val ok = j.optInt("ok", 0)
                    if (ok == 1) {
                        // tomamos IdUsu si viene (por compatibilidad con Act_Principal)
                        val idUsu = j.optInt("IdUsu", 0)
                        startActivity(
                            Intent(this, Act_Principal::class.java)
                                .putExtra("IdUsu", idUsu)
                        )
                        finish()
                    } else {
                        val msg = j.optString("error_desc",
                            j.optString("msg", "Usuario o contraseña incorrectos"))
                        Alerts.warn(this, "Credenciales inválidas", msg)
                    }
                } catch (e: Exception) {
                    Alerts.error(this, "Respuesta inválida",
                        e.message ?: "JSON no parseable")
                }
                onFinally()
            },
            { error ->
                loading.dismissWithAnimation()
                val status = error.networkResponse?.statusCode
                val bodyTxt = error.networkResponse?.data?.let { String(it) }
                Alerts.error(this, "Error de red", "Código: $status\n$bodyTxt")
                onFinally()
            }
        ) {
            override fun getParams(): MutableMap<String, String> =
                hashMapOf("usu" to user, "pass" to pass)
        }.apply {
            retryPolicy = DefaultRetryPolicy(8000, 1, 1.0f)
            setShouldCache(false)
        }

        queue.add(req)
    }

    // --------- Filtros / validaciones ----------
    private class OnlySafeTextFilter : InputFilter {
        override fun filter(
            source: CharSequence, start: Int, end: Int,
            dest: Spanned, dstart: Int, dend: Int
        ): CharSequence? {
            val out = StringBuilder(end - start)
            for (i in start until end) {
                val ch = source[i]
                val t = Character.getType(ch)
                val isControl = t == Character.CONTROL.toInt() || t == Character.FORMAT.toInt()
                val isSurrogate = Character.isSurrogate(ch)
                if (!isControl && !isSurrogate) out.append(ch)
            }
            return if (out.length == end - start) null else out.toString()
        }
    }

    private class NoSpacesFilter : InputFilter {
        override fun filter(
            source: CharSequence, start: Int, end: Int,
            dest: Spanned, dstart: Int, dend: Int
        ): CharSequence? =
            if (source.any { it == ' ' }) source.toString().replace(" ", "") else null
    }

    private fun isValidUser(u: String): Boolean {
        val usernameRegex = Regex("^[A-Za-z0-9._-]{3,60}$")
        return Patterns.EMAIL_ADDRESS.matcher(u).matches() || usernameRegex.matches(u)
    }
}
