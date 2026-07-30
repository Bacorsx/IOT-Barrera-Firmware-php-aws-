package com.example.celulariot

import android.content.Intent
import android.os.Bundle
import android.widget.AdapterView
import android.widget.ArrayAdapter
import android.widget.ListView
import android.widget.SearchView
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import com.android.volley.Request
import com.android.volley.RequestQueue
import com.android.volley.Response
import com.android.volley.toolbox.StringRequest
import com.android.volley.toolbox.Volley
import org.json.JSONArray
import java.util.Locale
import android.app.Activity
import androidx.activity.result.contract.ActivityResultContracts

class Act_Listar : AppCompatActivity() {

    private lateinit var listado: ListView
    private lateinit var buscador: SearchView
    private lateinit var datos: RequestQueue
    private lateinit var editarLauncher: androidx.activity.result.ActivityResultLauncher<Intent>

    private lateinit var adapter: ArrayAdapter<Usuario>
    private val items = mutableListOf<Usuario>()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContentView(R.layout.activity_act_listar)

        // Usa el root del layout, no un id de otra pantalla
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(android.R.id.content)) { v, insets ->
            val sys = insets.getInsets(WindowInsetsCompat.Type.systemBars())
            v.setPadding(sys.left, sys.top, sys.right, sys.bottom)
            insets
        }

        listado = findViewById(R.id.lista)
        buscador = findViewById(R.id.busqueda)
        datos = Volley.newRequestQueue(this)

        adapter = object : ArrayAdapter<Usuario>(this, android.R.layout.simple_list_item_1, items) {}
        listado.adapter = adapter

        cargaLista()

        // Filtro
        buscador.setOnQueryTextListener(object : SearchView.OnQueryTextListener {
            override fun onQueryTextSubmit(query: String?): Boolean {
                adapter.filter.filter(query)
                return true
            }
            override fun onQueryTextChange(newText: String?): Boolean {
                adapter.filter.filter(newText?.lowercase(Locale.ROOT))
                return true
            }
        })

        // RESULT: recarga SIEMPRE desde servidor (evita inconsistencias con filtro/caché)
        editarLauncher = registerForActivityResult(
            ActivityResultContracts.StartActivityForResult()
        ) { res ->
            if (res.resultCode == Activity.RESULT_OK) {
                cargaLista()
            }
        }

        listado.onItemClickListener = AdapterView.OnItemClickListener { _, _, pos, _ ->
            val u = adapter.getItem(pos) ?: return@OnItemClickListener
            val intent = Intent(this, Act_modificar_eliminar::class.java).apply {
                putExtra("Id", u.id)
                putExtra("Nombre", u.nombre)
                putExtra("Apellido", u.apellido)
                putExtra("Usuario", u.usuario)
            }
            editarLauncher.launch(intent)
        }

        supportActionBar?.apply {
            setDisplayHomeAsUpEnabled(true)
            setDisplayShowHomeEnabled(true)
        }
    }

    // Por si vuelves con back sin setResult:
    override fun onResume() {
        super.onResume()
        // Si prefieres evitar doble carga, puedes quitar esta línea.
        // La dejo porque es robusta y barata.
        // (Si notas 2 requests seguidas al volver con RESULT_OK, comenta esta llamada.)
        // cargaLista()
    }

    private fun cargaLista() {
        // cache-buster
        val url = "http://3.214.181.94/consulta.php?t=${System.currentTimeMillis()}"

        val request = StringRequest(
            Request.Method.GET, url,
            { response ->
                try {
                    val json = JSONArray(response)
                    items.clear()
                    for (i in 0 until json.length()) {
                        val obj = json.getJSONObject(i)
                        val id = obj.optInt("IdUsu", 0)
                        val nombre = obj.optString("nombre", "")
                        val apellido = obj.optString("apellido", "")
                        val usuario = obj.optString("usuario", "")
                        items += Usuario(id, nombre, apellido, usuario)
                    }
                    adapter.notifyDataSetChanged()

                    // Reaplica el filtro vigente
                    val q = buscador.query?.toString()
                    if (!q.isNullOrBlank()) adapter.filter.filter(q)

                } catch (e: Exception) {
                    e.printStackTrace()
                }
            },
            { it.printStackTrace() }
        ).apply {
            // Evita respuestas viejas
            setShouldCache(false)
        }

        // Limpia caché de la cola y dispara
        datos.cache.clear()
        datos.add(request)
    }
}

data class Usuario(
    val id: Int,
    val nombre: String,
    val apellido: String,
    val usuario: String
) {
    override fun toString(): String = "$nombre $apellido | $usuario"
}
