🚧 Proyecto IoT: Sistema de Control de Acceso con Barrera Automatizada
Proyecto estudiantil de automatización e IoT que integra un sistema embebido (ESP8266), una aplicación móvil Android y un backend en PHP con base de datos MySQL, para controlar el acceso a un área restringida mediante tarjetas RFID o PIN desde la app.

📖 Descripción General
Este proyecto nace como parte de un trabajo académico para demostrar la integración de tecnologías IoT, desarrollo móvil y servicios web. Simula un sistema de control de acceso para una empresa o estacionamiento, donde:

Un dispositivo físico (ESP8266) lee tarjetas RFID, controla un servo motor (barrera) y muestra estados con LEDs.

Una app Android permite abrir la barrera remotamente usando un PIN.

Un backend en PHP centraliza la lógica de negocio, gestiona usuarios y registra todos los intentos de acceso en una base de datos MySQL.

El sistema funciona de manera autónoma: el ESP8266 consulta periódicamente al servidor si hay un nuevo intento de acceso (desde RFID o app) y actúa en consecuencia.

✨ Características Principales
🔐 Autenticación dual: RFID (tarjeta/llavero) y PIN desde la app.

🌐 Comunicación en la nube: Todos los dispositivos se comunican vía HTTP con un servidor PHP alojado en AWS.

📱 App Android nativa: Escrita en Kotlin, permite gestionar usuarios y abrir la barrera.

🗄️ Base de datos centralizada: Registra usuarios, PINs y todos los intentos de acceso.

💡 Feedback visual: Anillo de LEDs NeoPixel en el ESP8266 indica estado (conectando, acceso permitido/denegado).

⚙️ Servo motor: Abre y cierra la barrera automáticamente tras validación.

🗂️ Estructura del Repositorio
text
IOT-Barrera-Firmware-php-aws-/
├── a.ino                      # Firmware para ESP8266 (IDE Arduino)
├── kotlin iot/                # Código fuente de la App Android (Kotlin)
└── php/                       # Backend API REST (PHP)
    ├── db.php                 # Conexión a la base de datos
    ├── apiregusu.php          # Registrar usuario
    ├── apiconsultausu.php     # Consultar usuario
    ├── apimodusu.php          # Modificar usuario
    ├── apidelusu.php          # Eliminar usuario
    ├── apirecuperar*.php      # Recuperación de PIN/contraseña
    ├── insertar_intento_directo.php  # Registrar intento desde RFID
    ├── registrar_acceso_app_pin.php  # Registrar intento desde app
    ├── listar_intentos_acceso.php    # Historial de accesos
    └── ... (otros endpoints)
🛠️ Tecnologías Utilizadas
Componente	Tecnología
Firmware	C++ (Arduino) para ESP8266
Backend	PHP 7+ (sin framework)
Base de Datos	MySQL / MariaDB
App Móvil	Kotlin (Android Studio)
Comunicación	HTTP / REST
Hardware	NodeMCU ESP8266, MFRC522 RFID, Servo SG90, NeoPixel Ring
🔧 Instalación y Configuración
1. Requisitos Previos
Hardware: NodeMCU, módulo RFID RC522, servo motor, anillo NeoPixel.

Software: Arduino IDE (con librerías: ESP8266WiFi, MFRC522, Adafruit_NeoPixel, Servo).

Servidor: PHP 7+ y MySQL (puedes usar XAMPP local o AWS).

2. Configurar el Backend (PHP + BD)
Importa el script SQL (no incluido, pero puedes generar las tablas según los endpoints).

Edita php/db.php con tus credenciales de MySQL.

Sube la carpeta php/ a tu servidor web (por ejemplo, en AWS EC2 o en un hosting compartido).

Asegúrate de que los endpoints sean accesibles públicamente (o desde la red local).

3. Configurar el Firmware (ESP8266)
Abre a.ino en Arduino IDE.

Cambia las constantes SSID y PASSWORD con los datos de tu red WiFi.

Cambia las URLs SERVER_URL y SERVER_LAST_URL apuntando a tus archivos PHP alojados en el servidor.

Conecta el hardware según los pines definidos en el código (D1, D2, D4, D0).

Carga el firmware al ESP8266.

4. Configurar la App Android
Abre la carpeta kotlin iot/ en Android Studio.

Ajusta la URL base de la API en el código (por ejemplo, en las clases de red).

Compila y ejecuta en tu dispositivo Android.

🚀 Uso del Sistema
Registro de usuarios: Desde la app o mediante llamadas a la API, se registran usuarios con su PIN.

Acceso por RFID: Acerca una tarjeta/llavero al lector. El ESP8266 envía el UID al servidor. Si el usuario está registrado, se abre la barrera.

Acceso desde la app: Introduce tu PIN en la app y pulsa "Abrir". El servidor valida y envía la orden al ESP8266 a través del polling.

Historial: Todos los intentos (exitosos o fallidos) quedan registrados en la BD.

📊 Diagrama de Flujo del Sistema
text
┌─────────────┐      ┌─────────────┐      ┌─────────────┐
│   ESP8266   │      │   Servidor  │      │  App Android│
│  (Hardware) │      │  (PHP + DB) │      │  (Kotlin)   │
└─────────────┘      └─────────────┘      └─────────────┘
      │                      │                      │
      │ (1) RFID detectado   │                      │
      ├─────────────────────►│                      │
      │                      │ (2) Validar usuario  │
      │                      │ y registrar intento  │
      │                      │                      │
      │                      │ (3) PIN desde app    │
      │                      │◄─────────────────────┤
      │                      │                      │
      │ (4) Polling cada 1s  │                      │
      ├─────────────────────►│                      │
      │◄───── (5) Resultado  │                      │
      │        (PERMITIDO/)  │                      │
      │                      │                      │
      │ (6) Abrir/cerrar     │                      │
      │      barrera         │                      │
      │                      │                      │
⚠️ Consideraciones de Seguridad (Importante)
Credenciales expuestas: En el código a.ino aparecen el SSID y contraseña de WiFi en texto plano. No subas este archivo a un repositorio público sin ofuscarlos.

Autenticación API: Los endpoints PHP no implementan autenticación ni HTTPS. En un entorno real, deberías agregar tokens o API keys y usar SSL.

Inyección SQL: Los archivos PHP no utilizan consultas preparadas (se recomienda migrar a PDO o mysqli con bind).

🤝 Contribuciones
Este es un proyecto estudiantil, pero las contribuciones son bienvenidas. Si deseas mejorar el código, añadir funcionalidades o corregir errores, abre un issue o envía un pull request.

📄 Licencia
Este proyecto se distribuye bajo la licencia MIT. Puedes usarlo, modificarlo y distribuirlo libremente.

📧 Contacto
Desarrollado por Bacorsx (GitHub). Para dudas o sugerencias, abre un issue en este repositorio.

🙏 Agradecimientos
A los profesores y compañeros que guiaron este proyecto, y a la comunidad open-source por las librerías y herramientas utilizadas.
