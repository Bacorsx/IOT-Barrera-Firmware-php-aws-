package com.example.celulariot

import android.content.Intent
import android.os.Bundle
import android.text.InputFilter
import android.text.Spanned
import android.text.method.PasswordTransformationMethod
import android.view.View
import android.widget.Button
import android.widget.EditText
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import com.android.volley.AuthFailureError
import com.android.volley.Request
import com.android.volley.RequestQueue
import com.android.volley.toolbox.StringRequest
import com.android.volley.toolbox.Volley
import com.example.celulariot.ui.Alerts
import org.json.JSONObject
import java.text.Normalizer
import java.util.regex.Pattern

class Act_modificar_eliminar : AppCompatActivity() {

    private lateinit var txtId: EditText
    private lateinit var txtNombre: EditText
    private lateinit var txtApellido: EditText
    private lateinit var txtClave: EditText
    private lateinit var btnModificar: Button
    private lateinit var btnEliminar: Button
    private lateinit var queue: RequestQueue

    companion object {
        private const val BASE_URL = "http://3.214.181.94/"
        private const val MAX_NAME_LEN = 60
        private const val MAX_PASS_LEN = 128

        private val NAME_REGEX: Pattern =
            Pattern.compile("^[\\p{L}][\\p{L}\\p{M} '\\-]{0,59}$")

        private val PASS_REGEX: Pattern =
            Pattern.compile("^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^\\w\\s]).{8,128}$")
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContentView(R.layout.activity_act_modificar_eliminar)

        // usa el root para insets (evita NPE por ids inexistentes)
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(android.R.id.content)) { v, insets ->
            val b = insets.getInsets(WindowInsetsCompat.Type.systemBars())
            v.setPadding(b.left, b.top, b.right, b.bottom); insets
        }

        txtId        = findViewById(R.id.txtid)
        txtNombre    = findViewById(R.id.txtnombremod)
        txtApellido  = findViewById(R.id.txtapellidomod)
        txtClave     = findViewById(R.id.txtclavemod)
        btnModificar = findViewById(R.id.btn_modificar)
        btnEliminar  = findViewById(R.id.btn_eliminar)
        queue = Volley.newRequestQueue(this)

        val safeText = OnlySafeTextFilter()
        txtNombre.filters   = arrayOf(InputFilter.LengthFilter(MAX_NAME_LEN), safeText)
        txtApellido.filters = arrayOf(InputFilter.LengthFilter(MAX_NAME_LEN), safeText)
        txtClave.filters    = arrayOf(InputFilter.LengthFilter(MAX_PASS_LEN), NoSpacesFilter(), safeText)
        txtClave.transformationMethod = PasswordTransformationMethod.getInstance()

        // ----- datos entrantes -----
        val idUsu = intent.getIntExtra("Id", 0)
        val fromRecovery = intent.getBooleanExtra("fromRecovery", false)
        val nombreIn = intent.getStringExtra("Nombre") ?: ""
        val apellidoIn = intent.getStringExtra("Apellido") ?: ""

        txtId.setText(idUsu.toString())
        txtNombre.setText(nombreIn)
        txtApellido.setText(apellidoIn)

        // UI especial para recuperación
        if (fromRecovery) {
            supportActionBar?.title = "Cambiar contraseña"
            btnEliminar.visibility = View.GONE
            txtClave.requestFocus()
        } else {
            supportActionBar?.setDisplayHomeAsUpEnabled(true)
        }

        btnModificar.setOnClickListener {
            val nom = normalizeName(txtNombre.text.toString())
            val ape = normalizeName(txtApellido.text.toString())
            val nuevaClave = txtClave.text.toString()

            if (idUsu <= 0) {
                Alerts.error(this, "Id inválido"); return@setOnClickListener
            }

            // ----- validaciones según flujo -----
            if (fromRecovery) {
                // nombre/apellido opcionales; si están escritos, valida forma
                if (nom.isNotBlank() && !isValidName(nom)) {
                    Alerts.warn(this, "Nombre inválido",
                        "Usa solo letras, espacios, guion y apóstrofe (máx. $MAX_NAME_LEN).")
                    return@setOnClickListener
                }
                if (ape.isNotBlank() && !isValidName(ape)) {
                    Alerts.warn(this, "Apellido inválido",
                        "Usa solo letras, espacios, guion y apóstrofe (máx. $MAX_NAME_LEN).")
                    return@setOnClickListener
                }
                // contraseña requerida y fuerte
                if (!isStrongPassword(nuevaClave)) {
                    Alerts.warn(this, "Contraseña insegura",
                        "Mín. 8, con mayúscula, minúscula, dígito y símbolo; sin espacios.")
                    return@setOnClickListener
                }
            } else {
                // flujo normal: nombre y apellido obligatorios si se modifica
                if (!isValidName(nom) || !isValidName(ape)) {
                    Alerts.warn(this, "Datos inválidos",
                        "Revisa nombre y apellido (máx. $MAX_NAME_LEN).")
                    return@setOnClickListener
                }
                if (nuevaClave.isNotBlank() && !isStrongPassword(nuevaClave)) {
                    Alerts.warn(this, "Contraseña insegura",
                        "Mín. 8, con mayúscula, minúscula, dígito y símbolo; sin espacios.")
                    return@setOnClickListener
                }
            }

            setButtonsEnabled(false)
            modificarRemoto(
                idUsu,
                if (fromRecovery && nom.isBlank()) "" else nom,
                if (fromRecovery && ape.isBlank()) "" else ape,
                nuevaClave
            ) { setButtonsEnabled(true) }
        }

        btnEliminar.setOnClickListener {
            if (idUsu <= 0) { Alerts.error(this, "Id inválido"); return@setOnClickListener }
            Alerts.confirm(this, "¿Eliminar usuario?", "Esta acción no se puede deshacer.") {
                setButtonsEnabled(false)
                eliminarRemoto(idUsu) { setButtonsEnabled(true) }
            }
        }
    }

    // ------------ Validadores / Sanitizadores ------------
    private fun normalizeName(raw: String): String =
        Normalizer.normalize(raw.trim().replace(Regex("\\s+"), " "), Normalizer.Form.NFC)

    private fun isValidName(s: String): Boolean =
        s.isNotEmpty() && s.length <= MAX_NAME_LEN && NAME_REGEX.matcher(s).matches()

    private fun isStrongPassword(p: String): Boolean =
        p.isNotBlank() && p.length <= MAX_PASS_LEN && !p.contains(" ") && PASS_REGEX.matcher(p).matches()

    private class OnlySafeTextFilter : InputFilter {
        override fun filter(source: CharSequence, start: Int, end: Int,
                            dest: Spanned, dstart: Int, dend: Int): CharSequence? {
            val out = StringBuilder(end - start)
            for (i in start until end) {
                val ch = source[i]
                val type = Character.getType(ch)
                val isControl = type == Character.CONTROL.toInt() || type == Character.FORMAT.toInt()
                val isSurrogate = Character.isSurrogate(ch)
                if (!isControl && !isSurrogate) out.append(ch)
            }
            return if (out.length == end - start) null else out.toString()
        }
    }

    private class NoSpacesFilter : InputFilter {
        override fun filter(source: CharSequence, start: Int, end: Int,
                            dest: Spanned, dstart: Int, dend: Int): CharSequence? =
            if (source.any { it == ' ' }) source.toString().replace(" ", "") else null
    }

    private fun setButtonsEnabled(enabled: Boolean) {
        btnModificar.isEnabled = enabled
        btnEliminar.isEnabled  = enabled
    }

    // ---------------------- Requests ----------------------
    private fun modificarRemoto(
        idUsu: Int, nombre: String, apellido: String, claveOpcional: String, onFinally: () -> Unit
    ) {
        val url = "${BASE_URL}apimodusu.php"
        val loading = Alerts.loading(this, "Actualizando…")
        val req = object : StringRequest(
            Request.Method.POST, url,
            { body -> // <-- usar body
                loading.dismissWithAnimation()
                try {
                    val ok = try { JSONObject(body).optInt("ok", 0) == 1 } catch (_: Exception) { true }
                    if (ok) {
                        Alerts.success(this, "Actualizado correctamente")
                        setResult(RESULT_OK, Intent().apply {
                            putExtra("action", "updated")
                            putExtra("id", idUsu)
                            putExtra("nombre", nombre)
                            putExtra("apellido", apellido)
                        })
                        finish()
                    } else {
                        Alerts.warn(this, "Sin cambios", "El servidor no aplicó modificaciones")
                    }
                } finally { onFinally() }
            },
            { err -> // <-- ErrorListener separado por coma
                loading.dismissWithAnimation()
                err.printStackTrace()
                Alerts.error(this, "Error al modificar", "Revisa conexión o servidor")
                onFinally()
            }
        ) {
            @Throws(AuthFailureError::class)
            override fun getParams(): MutableMap<String, String> {
                val p = hashMapOf("IdUsu" to idUsu.toString())
                if (nombre.isNotBlank())   p["nombre"] = nombre
                if (apellido.isNotBlank()) p["apellido"] = apellido
                if (claveOpcional.isNotBlank()) p["clave"] = claveOpcional
                return p
            }
            override fun getBodyContentType(): String =
                "application/x-www-form-urlencoded; charset=UTF-8"
        }
        queue.add(req)
    }

    private fun eliminarRemoto(idUsu: Int, onFinally: () -> Unit) {
        val url = "${BASE_URL}apidelusu.php"
        val loading = Alerts.loading(this, "Eliminando…")
        val req = object : StringRequest(
            Request.Method.POST, url,
            { body -> // <-- usar body
                loading.dismissWithAnimation()
                val ok = try { JSONObject(body).optInt("ok", 0) == 1 } catch (_: Exception) { true }
                if (ok) {
                    Alerts.success(this, "Usuario eliminado")
                    setResult(RESULT_OK, Intent().apply {
                        putExtra("action", "deleted")
                        putExtra("id", idUsu)
                    })
                    finish()
                } else {
                    Alerts.warn(this, "No se eliminó", "El servidor no confirmó la eliminación")
                }
                onFinally()
            },
            { err -> // <-- coma antes del error
                loading.dismissWithAnimation()
                err.printStackTrace()
                Alerts.error(this, "Error al eliminar", "Revisa conexión o servidor")
                onFinally()
            }
        ) {
            @Throws(AuthFailureError::class)
            override fun getParams(): MutableMap<String, String> =
                hashMapOf("IdUsu" to idUsu.toString())

            override fun getBodyContentType(): String =
                "application/x-www-form-urlencoded; charset=UTF-8"
        }
        queue.add(req)
    }
}
