<?php
/**
 * cron_test.php - Sonda para verificar que el cron del hosting dispara.
 *
 * NO envía emails ni toca Sheets. Solo appenda una línea con timestamp a
 * `cron_test.log` cada vez que se ejecuta. Sirve para confirmar, de forma
 * aislada y segura, que el scheduler de cPanel realmente corre las tareas
 * programadas a su hora.
 *
 * ─── CÓMO USAR ────────────────────────────────────────────────────────────
 *   1. Subir este archivo al mismo directorio que los otros PHP del backend
 *      (ej. /gs/ en el host).
 *   2. En cPanel → Cron Jobs, crear un job cada minuto:
 *        * * * * * /usr/bin/php /home/USER/public_html/gs/cron_test.php
 *      (ajustar la ruta de php y del archivo según el host).
 *   3. Esperar 1-2 minutos.
 *   4. Leer el resultado por web:
 *        https://app.iedeoccidente.com/gs/cron_test.log
 *      o abrir cron_test.php en el navegador (muestra el log acumulado).
 *   5. Si aparecen líneas con timestamps que avanzan cada minuto → el cron
 *      dispara. Confirmado el mecanismo.
 *   6. BORRAR el cron job de prueba y este archivo cuando termines.
 *
 * ─── MODO DE EJECUCIÓN ────────────────────────────────────────────────────
 *   - Desde CLI/cron: appenda una línea al log y termina.
 *   - Desde web (GET): muestra el contenido del log (para inspección fácil
 *     sin File Manager ni terminal). No appenda en modo web para que las
 *     visitas del navegador no ensucien la prueba.
 */

const LOG_FILE = __DIR__ . '/cron_test.log';

$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    // Ejecución real del cron: registrar el disparo.
    $line = sprintf(
        "[%s] cron disparó (sapi=cli, pid=%d)\n",
        date('Y-m-d H:i:s'),
        getmypid()
    );
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    echo $line;
    exit(0);
}

// Ejecución web: solo mostrar el log para inspección.
header('Content-Type: text/plain; charset=utf-8');
if (file_exists(LOG_FILE)) {
    echo "=== cron_test.log ===\n";
    echo file_get_contents(LOG_FILE);
} else {
    echo "cron_test.log aún no existe. El cron no ha disparado todavía\n";
    echo "(o la ruta del cron job apunta a otro lugar).\n";
}
