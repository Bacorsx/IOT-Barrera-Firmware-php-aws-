<?php
// /var/www/html/config_mail.php (idempotente)
if (!defined('SMTP_HOST'))       define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT'))       define('SMTP_PORT', 587);
if (!defined('SMTP_USER'))       define('SMTP_USER', 'iecflores2016@gmail.com');
if (!defined('SMTP_PASS'))       define('SMTP_PASS', 'zyzesoskcltdmlpo'); // <-- pon el real
if (!defined('SMTP_FROM'))       define('SMTP_FROM', 'iecflores2016@gmail.com');
if (!defined('SMTP_FROM_NAME'))  define('SMTP_FROM_NAME', 'Soporte CelularIoT');

?>