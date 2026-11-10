<?php
/**
 * Arranque común de la API: configuración, manejo de errores y encabezados.
 */

declare(strict_types=1);

error_reporting(E_ALL);
// Los errores van al log de Apache, nunca a la respuesta (no se filtran rutas ni consultas).
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/**
 * Lee un valor de config.php (y de config.local.php si existe).
 *
 * @return mixed
 */
function config(string $clave)
{
    static $valores = null;
    if ($valores === null) {
        $valores = require __DIR__ . '/config.php';
        $local = __DIR__ . '/config.local.php';
        if (is_file($local)) {
            $valores = array_replace_recursive($valores, require $local);
        }
    }
    return $valores[$clave] ?? null;
}

require __DIR__ . '/http.php';
require __DIR__ . '/db.php';
require __DIR__ . '/jwt.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/validar.php';
require __DIR__ . '/imagen.php';
require __DIR__ . '/controladores/sesion.php';
require __DIR__ . '/controladores/usuarios.php';
require __DIR__ . '/controladores/disfraces.php';
require __DIR__ . '/controladores/votos.php';

// Los avisos de PHP se convierten en excepciones para que terminen en una respuesta 500 limpia.
set_error_handler(function (int $nivel, string $mensaje, string $archivo = '', int $linea = 0): bool {
    if (!(error_reporting() & $nivel)) {
        return false;
    }
    throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
});

set_exception_handler(function (Throwable $e): void {
    if ($e instanceof ErrorHttp) {
        $cuerpo = ['error' => $e->getMessage()];
        if ($e->campos) {
            $cuerpo['campos'] = $e->campos;
        }
        $cabeceras = $e->cabeceras;
        if ($e->estado === 401) {
            $cabeceras['WWW-Authenticate'] = 'Bearer';
        }
        responder($e->estado, $cuerpo, $cabeceras);
    }
    error_log('Halloween API: ' . $e);
    responder(500, ['error' => 'Ocurrió un error interno. El detalle quedó en el log de Apache (xampp/apache/logs/error.log).']);
});

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
// Las respuestas de la API cambian con cada voto: que el navegador no las guarde.
header('Cache-Control: no-store');
