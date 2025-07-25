<?php
/**
 * Conexión a la base de datos usando mysqli y variables de entorno.
 * Este archivo está preparado para un entorno de producción como Render.
 */

// Desactivar la muestra de errores en producción por seguridad.
// Render maneja los logs, no necesitas mostrar errores al usuario.
// ini_set('display_errors', 0);
// error_reporting(0);

// --- Configuración de la conexión usando variables de entorno ---
// getenv() lee las variables que configurarás en el panel de Render.
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD'); 
$dbname = getenv('DB_NAME');
$port = getenv('DB_PORT') ?: 3306; // El puerto por defecto de MySQL es 3306. Render te dará uno.

// --- Crear la conexión ---
// Primero, configuramos el modo de reporte de errores para que lance excepciones.
// Esto es más limpio que usar 'if ($conn->connect_error)'.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Creamos la instancia de la conexión
    $conn = new mysqli($host, $user, $password, $dbname, $port);

    // Establecemos el juego de caracteres a utf8mb4 para soportar emojis y caracteres especiales.
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    // Si la conexión falla, no muestres el error detallado al usuario.
    // Lo registramos para que tú lo veas en los logs de Render.
    error_log("Error de conexión a la base de datos: " . $e->getMessage());

    // Mostramos un mensaje genérico y terminamos la ejecución.
    http_response_code(503); // Service Unavailable
    die("Lo sentimos, el sitio está experimentando problemas técnicos. Por favor, inténtelo de nuevo más tarde.");
}

// ¡Listo! La variable $conn ya está disponible para el resto de tus scripts.
// No es necesario un 'echo "Conexión exitosa"'.
?>