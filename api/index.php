<?php
/**
 * API REST del concurso de disfraces de Halloween.
 *
 * Es el único punto de entrada (front controller): el .htaccess de esta carpeta
 * redirige acá todas las rutas, por ejemplo /halloween/api/disfraces/3/votos.
 * Si tu Apache no tiene mod_rewrite, las mismas rutas funcionan como
 * /halloween/api/index.php/disfraces/3/votos.
 */

require __DIR__ . '/src/bootstrap.php';

// --- Ruta pedida, relativa a la carpeta de la API --------------------------------
$rutaUri = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$carpeta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

$resto = $rutaUri;
if ($carpeta !== '' && strpos($resto, $carpeta) === 0) {
    $resto = substr($resto, strlen($carpeta));
}
$base = $carpeta . '/';
if (strpos($resto, '/index.php') === 0) {
    $resto = substr($resto, strlen('/index.php'));
    $base = $carpeta . '/index.php/';
}
$ruta = trim((string) $resto, '/');

// URL base de la API, para armar enlaces como foto_url o el encabezado Location.
define('API_BASE', $base);

// --- Tabla de rutas: método, patrón y función que la atiende ------------------
$rutas = [
    ['GET',    '#^$#',                          'api_indice'],
    ['POST',   '#^usuarios$#',                  'usuarios_registrar'],
    ['POST',   '#^sesion$#',                    'sesion_crear'],
    ['GET',    '#^sesion$#',                    'sesion_ver'],
    ['GET',    '#^disfraces$#',                 'disfraces_listar'],
    ['POST',   '#^disfraces$#',                 'disfraces_crear'],
    ['GET',    '#^disfraces/(\d{1,9})$#',       'disfraces_ver'],
    ['PUT',    '#^disfraces/(\d{1,9})$#',       'disfraces_actualizar'],
    ['PATCH',  '#^disfraces/(\d{1,9})$#',       'disfraces_parchear'],
    ['DELETE', '#^disfraces/(\d{1,9})$#',       'disfraces_eliminar'],
    ['GET',    '#^disfraces/(\d{1,9})/foto$#',  'disfraces_foto'],
    ['POST',   '#^disfraces/(\d{1,9})/votos$#', 'votos_crear'],
    ['DELETE', '#^disfraces/(\d{1,9})/votos$#', 'votos_eliminar'],
];

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($metodo === 'HEAD') {
    $metodo = 'GET';
}

$metodosDeLaRuta = [];
foreach ($rutas as [$metodoRuta, $patron, $accion]) {
    if (!preg_match($patron, $ruta, $coincidencia)) {
        continue;
    }
    if ($metodoRuta === $metodo) {
        $argumentos = array_map('intval', array_slice($coincidencia, 1));
        $accion(...$argumentos);
        exit;
    }
    $metodosDeLaRuta[] = $metodoRuta;
}

if ($metodosDeLaRuta) {
    throw new ErrorHttp(405, "La ruta /{$ruta} no admite {$metodo}.", [], ['Allow' => implode(', ', $metodosDeLaRuta)]);
}
throw new ErrorHttp(404, "No existe la ruta /{$ruta} en la API.");

/** GET /  -> descripción de los endpoints disponibles. */
function api_indice(): void
{
    responder(200, [
        'api' => 'Concurso de disfraces de Halloween',
        'autenticacion' => 'Enviá el token de POST /sesion en el encabezado "Authorization: Bearer <token>".',
        'endpoints' => [
            'POST /usuarios'              => 'Registrarse. Cuerpo: {"nombre", "clave"}. Devuelve un token.',
            'POST /sesion'                => 'Iniciar sesión. Cuerpo: {"nombre", "clave"}. Devuelve un token.',
            'GET /sesion'                 => 'Datos del usuario del token.',
            'GET /disfraces'              => 'Lista de disfraces ordenada por votos. Con token indica cuáles votaste.',
            'GET /disfraces?todos=1'      => 'Incluye los eliminados (admin).',
            'POST /disfraces'             => 'Crear. Cuerpo: {"nombre", "descripcion", "foto"?: data URL}. Requiere sesión.',
            'GET /disfraces/{id}'         => 'Ver un disfraz.',
            'PUT /disfraces/{id}'         => 'Modificar (admin). Cuerpo: {"nombre", "descripcion", "foto"?: data URL | null}.',
            'PATCH /disfraces/{id}'       => 'Restaurar o eliminar (admin). Cuerpo: {"eliminado": false | true}.',
            'DELETE /disfraces/{id}'      => 'Eliminar con baja lógica (admin).',
            'GET /disfraces/{id}/foto'    => 'Imagen del disfraz.',
            'POST /disfraces/{id}/votos'  => 'Votar. Requiere sesión. 409 si ya lo votaste.',
            'DELETE /disfraces/{id}/votos' => 'Quitar tu voto. Requiere sesión.',
        ],
    ]);
}
