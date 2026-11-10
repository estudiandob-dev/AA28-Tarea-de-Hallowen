<?php
/**
 * Pruebas automáticas de la API REST (pruebas de integración de caja negra).
 *
 * Se ejecuta desde la consola con el PHP de XAMPP, con Apache y MySQL encendidos:
 *
 *     C:\xampp\php\php.exe pruebas\probar_api.php
 *
 * Opcional: otra URL base y la contraseña del administrador.
 *
 *     C:\xampp\php\php.exe pruebas\probar_api.php http://localhost/halloween/api/ Halloween2026
 *
 * Las pruebas crean un usuario y un disfraz de prueba (el disfraz queda eliminado al final).
 * Para volver a los datos de ejemplo, importá otra vez database/halloween.sql.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Estas pruebas se ejecutan desde la consola.');
}

$base = rtrim($argv[1] ?? 'http://localhost/halloween/api/', '/') . '/';
$claveAdmin = $argv[2] ?? 'Halloween2026';

$fallas = 0;
$total = 0;

/**
 * Hace una petición HTTP y devuelve [estado, cabeceras, cuerpo, json, milisegundos].
 */
function pedir(string $metodo, string $ruta, $cuerpo = null, ?string $token = null, array $extra = []): array
{
    global $base;
    $cabeceras = ['Accept: application/json'];
    if ($token !== null) {
        $cabeceras[] = 'Authorization: Bearer ' . $token;
    }
    $contenido = null;
    if ($cuerpo !== null) {
        $cabeceras[] = 'Content-Type: ' . ($extra['tipo'] ?? 'application/json');
        $contenido = is_string($cuerpo) ? $cuerpo : json_encode($cuerpo);
    }
    $contexto = stream_context_create(['http' => [
        'method'        => $metodo,
        'header'        => implode("\r\n", $cabeceras),
        'content'       => $contenido,
        'ignore_errors' => true, // para leer también las respuestas 4xx y 5xx
        'timeout'       => 10,
    ]]);

    $inicio = microtime(true);
    $respuesta = @file_get_contents($base . $ruta, false, $contexto);
    $ms = (microtime(true) - $inicio) * 1000;

    $lineas = cabeceras_de_respuesta(isset($http_response_header) ? $http_response_header : []);
    if (!$lineas) {
        fwrite(STDERR, "No se pudo conectar con {$base}. ¿Está encendido Apache?\n");
        exit(2);
    }
    preg_match('#HTTP/\S+\s+(\d{3})#', $lineas[0], $m);
    $mapa = [];
    foreach (array_slice($lineas, 1) as $linea) {
        if (strpos($linea, ':') !== false) {
            [$nombre, $valor] = explode(':', $linea, 2);
            $mapa[strtolower(trim($nombre))] = trim($valor);
        }
    }
    $texto = $respuesta === false ? '' : $respuesta;
    return [(int) ($m[1] ?? 0), $mapa, $texto, json_decode($texto, true), $ms];
}

/** Cabeceras de la última respuesta (PHP 8.4+ tiene una función; antes, la variable automática). */
function cabeceras_de_respuesta(array $variable): array
{
    return function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : $variable;
}

function comprobar(string $descripcion, bool $condicion, string $detalle = ''): void
{
    global $fallas, $total;
    $total++;
    if (!$condicion) {
        $fallas++;
    }
    echo ($condicion ? '  OK     ' : '  FALLA  ') . $descripcion . ($condicion || $detalle === '' ? '' : "  ->  {$detalle}") . PHP_EOL;
}

function seccion(string $titulo): void
{
    echo PHP_EOL . $titulo . PHP_EOL;
}

// Imagen PNG de 2x2 píxeles para probar la carga de fotos.
$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAEklEQVR4XmP8XyXFwMDAxAAGABPzAZfZ9/16AAAAAElFTkSuQmCC';
$sufijo = date('His') . random_int(10, 99);

echo "Probando la API en {$base}" . PHP_EOL;

seccion('Rutas y formato');
[$estado, , , $json] = pedir('GET', '');
comprobar('GET / responde 200 con la lista de endpoints', $estado === 200 && isset($json['endpoints']), "estado {$estado}");
if ($estado === 503) {
    echo PHP_EOL . 'La API no llega a MySQL: encendelo en XAMPP e importá database/halloween.sql.' . PHP_EOL;
    exit(1);
}
[$estado] = pedir('GET', 'no-existe');
comprobar('Una ruta inexistente responde 404', $estado === 404, "estado {$estado}");
[$estado, $cab] = pedir('PUT', 'disfraces');
comprobar('Un método no permitido responde 405 con el encabezado Allow', $estado === 405 && isset($cab['allow']), "estado {$estado}");
[$estado] = pedir('POST', 'sesion', '{roto', null);
comprobar('Un JSON mal formado responde 400', $estado === 400, "estado {$estado}");
[$estado] = pedir('POST', 'sesion', 'nombre=x', null, ['tipo' => 'application/x-www-form-urlencoded']);
comprobar('Un cuerpo que no es JSON responde 415', $estado === 415, "estado {$estado}");

seccion('Disfraces (lectura pública)');
[$estado, , , $lista] = pedir('GET', 'disfraces');
$votos = is_array($lista) ? array_column($lista, 'votos') : [];
$ordenados = $votos;
rsort($ordenados);
comprobar('GET /disfraces responde 200 con una lista', $estado === 200 && is_array($lista) && count($lista) > 0, "estado {$estado}");
comprobar('La lista viene ordenada por votos, de mayor a menor', $votos === $ordenados);
$primero = $lista[0] ?? null;
[$estado] = pedir('GET', 'disfraces/999999');
comprobar('Un disfraz inexistente responde 404', $estado === 404, "estado {$estado}");
if ($primero && $primero['foto_url']) {
    $rutaFoto = substr($primero['foto_url'], strpos($primero['foto_url'], '/api/') + 5);
    [$estado, $cab, $bytes] = pedir('GET', $rutaFoto);
    comprobar('La foto se sirve como imagen desde la base', $estado === 200 && strpos($cab['content-type'] ?? '', 'image/') === 0 && strlen($bytes) > 0, "estado {$estado}");
}

seccion('Registro e inicio de sesión');
[$estado, , , $json] = pedir('POST', 'usuarios', ['nombre' => 'a b', 'clave' => '123']);
comprobar('Registro con datos inválidos responde 422 con el error de cada campo', $estado === 422 && isset($json['campos']['nombre'], $json['campos']['clave']), "estado {$estado}");
[$estado] = pedir('POST', 'usuarios', ['nombre' => 'ADMIN', 'clave' => 'una-clave-larga']);
comprobar('No se puede registrar el nombre del administrador', $estado === 422, "estado {$estado}");
$usuario = 'prueba' . $sufijo;
[$estado, $cab, , $json] = pedir('POST', 'usuarios', ['nombre' => $usuario, 'clave' => 'clave-de-prueba']);
comprobar('Registro válido responde 201 con un token', $estado === 201 && !empty($json['token']) && isset($cab['location']), "estado {$estado}");
$token = $json['token'] ?? '';
[$estado] = pedir('POST', 'usuarios', ['nombre' => strtoupper($usuario), 'clave' => 'clave-de-prueba']);
comprobar('Un nombre repetido (aunque cambien las mayúsculas) responde 409', $estado === 409, "estado {$estado}");
[$estado, $cab] = pedir('POST', 'sesion', ['nombre' => $usuario, 'clave' => 'otra']);
comprobar('Contraseña incorrecta responde 401', $estado === 401 && ($cab['www-authenticate'] ?? '') === 'Bearer', "estado {$estado}");
[$estado, , , $json] = pedir('POST', 'sesion', ['nombre' => $usuario, 'clave' => 'clave-de-prueba']);
comprobar('Contraseña correcta responde 200 con un token', $estado === 200 && !empty($json['token']), "estado {$estado}");
[$estado, , , $json] = pedir('GET', 'sesion', null, $token);
comprobar('GET /sesion devuelve el usuario del token (sin permisos de admin)', $estado === 200 && $json['nombre'] === $usuario && $json['admin'] === false, "estado {$estado}");
[$estado] = pedir('GET', 'sesion');
comprobar('GET /sesion sin token responde 401', $estado === 401, "estado {$estado}");
$partes = explode('.', $token);
$carga = json_decode(base64_decode(strtr($partes[1] ?? '', '-_', '+/')), true) ?: [];
$carga['sub'] = '1';
$adulterado = ($partes[0] ?? '') . '.' . rtrim(strtr(base64_encode(json_encode($carga)), '+/', '-_'), '=') . '.' . ($partes[2] ?? '');
[$estado] = pedir('GET', 'sesion', null, $adulterado);
comprobar('Un token adulterado (cambiando el usuario) responde 401', $estado === 401, "estado {$estado}");

seccion('Votos');
$idVoto = $primero['id'] ?? 1;
[$estado] = pedir('POST', "disfraces/{$idVoto}/votos");
comprobar('Votar sin sesión responde 401', $estado === 401, "estado {$estado}");
[$estado, , , $json] = pedir('POST', "disfraces/{$idVoto}/votos", null, $token);
comprobar('Votar responde 201 y suma un voto', $estado === 201 && $json['votado'] === true && $json['votos'] === $primero['votos'] + 1, "estado {$estado}");
[$estado] = pedir('POST', "disfraces/{$idVoto}/votos", null, $token);
comprobar('Votar dos veces el mismo disfraz responde 409', $estado === 409, "estado {$estado}");
[$estado, , , $json] = pedir('GET', "disfraces/{$idVoto}", null, $token);
comprobar('El disfraz figura como votado por el usuario', $estado === 200 && $json['votado'] === true, "estado {$estado}");
[$estado, , , $json] = pedir('DELETE', "disfraces/{$idVoto}/votos", null, $token);
comprobar('Quitar el voto responde 200 y resta el voto', $estado === 200 && $json['votos'] === $primero['votos'], "estado {$estado}");
[$estado] = pedir('DELETE', "disfraces/{$idVoto}/votos", null, $token);
comprobar('Quitar un voto que no existe responde 404', $estado === 404, "estado {$estado}");

seccion('Alta de disfraces');
[$estado, , , $json] = pedir('POST', 'disfraces', ['nombre' => 'X', 'descripcion' => '', 'foto' => 'data:image/png;base64,AAAA'], $token);
comprobar('Datos inválidos responden 422 con error en nombre, descripción y foto', $estado === 422 && count($json['campos'] ?? []) === 3, "estado {$estado}");
[$estado] = pedir('POST', 'disfraces', ['nombre' => 'Sin sesión', 'descripcion' => 'No debería crearse.']);
comprobar('Crear sin sesión responde 401', $estado === 401, "estado {$estado}");
[$estado, $cab, , $json] = pedir('POST', 'disfraces', ['nombre' => '[prueba] ' . $sufijo, 'descripcion' => 'Disfraz creado por las pruebas automáticas.', 'foto' => $png], $token);
comprobar('Crear con foto responde 201 con la URL de la foto', $estado === 201 && !empty($json['foto_url']) && isset($cab['location']), "estado {$estado}");
$idNuevo = $json['id'] ?? 0;

seccion('Administración');
[$estado, , , $json] = pedir('POST', 'sesion', ['nombre' => 'admin', 'clave' => $claveAdmin]);
$tokenAdmin = $json['token'] ?? '';
comprobar('El administrador inicia sesión', $estado === 200 && ($json['usuario']['admin'] ?? false) === true, "estado {$estado} (¿cambiaste la contraseña? pasala como segundo argumento)");
[$estado] = pedir('PUT', "disfraces/{$idNuevo}", ['nombre' => 'Cambio', 'descripcion' => 'Un usuario común no puede.'], $token);
comprobar('Un usuario común no puede modificar (403)', $estado === 403, "estado {$estado}");
[$estado] = pedir('GET', 'disfraces?todos=1', null, $token);
comprobar('Un usuario común no puede ver los eliminados (403)', $estado === 403, "estado {$estado}");
[$estado, , , $json] = pedir('PUT', "disfraces/{$idNuevo}", ['nombre' => '[prueba] editado', 'descripcion' => 'Modificado por el administrador.'], $tokenAdmin);
comprobar('El admin modifica y la foto se conserva', $estado === 200 && $json['nombre'] === '[prueba] editado' && !empty($json['foto_url']), "estado {$estado}");
[$estado] = pedir('DELETE', "disfraces/{$idNuevo}", null, $tokenAdmin);
comprobar('El admin elimina (204)', $estado === 204, "estado {$estado}");
[$estado] = pedir('GET', "disfraces/{$idNuevo}");
comprobar('El eliminado ya no es público (404)', $estado === 404, "estado {$estado}");
[$estado] = pedir('POST', "disfraces/{$idNuevo}/votos", null, $token);
comprobar('No se puede votar un eliminado (404)', $estado === 404, "estado {$estado}");
[$estado, , , $json] = pedir('PATCH', "disfraces/{$idNuevo}", ['eliminado' => false], $tokenAdmin);
comprobar('El admin restaura (200)', $estado === 200 && $json['eliminado'] === false, "estado {$estado}");
pedir('DELETE', "disfraces/{$idNuevo}", null, $tokenAdmin); // limpieza

seccion('Seguridad');
[$estado, , $texto] = pedir('GET', 'disfraces?id=1%27%20OR%20%271%27=%271');
comprobar('Un intento de inyección SQL en la URL no rompe la API', $estado === 200 && json_decode($texto) !== null, "estado {$estado}");
[$estado] = pedir('POST', 'sesion', ['nombre' => "admin' -- ", 'clave' => 'x']);
comprobar('Inyección SQL en el login responde 401 (no entra)', $estado === 401, "estado {$estado}");
$rutaRaiz = preg_replace('#api/$#', '', $base);
$contexto = stream_context_create(['http' => ['ignore_errors' => true]]);
foreach (['api/src/config.php', 'database/halloween.sql'] as $protegido) {
    @file_get_contents($rutaRaiz . $protegido, false, $contexto);
    $primera = cabeceras_de_respuesta(isset($http_response_header) ? $http_response_header : [])[0] ?? '';
    preg_match('#\s(\d{3})\b#', $primera, $m);
    comprobar("{$protegido} no se puede descargar (403)", ($m[1] ?? '') === '403', $primera);
}

seccion('Desempeño');
$tiempos = [];
for ($i = 0; $i < 20; $i++) {
    $tiempos[] = pedir('GET', 'disfraces')[4];
}
sort($tiempos);
$promedio = array_sum($tiempos) / count($tiempos);
$p95 = $tiempos[(int) floor(0.95 * (count($tiempos) - 1))];
comprobar(sprintf('GET /disfraces: promedio %.1f ms, p95 %.1f ms (límite 500 ms)', $promedio, $p95), $p95 < 500);
[, , $bytes, , $ms] = pedir('GET', 'disfraces');
comprobar(sprintf('La lista pesa %.1f KB: las fotos viajan aparte', strlen($bytes) / 1024), strlen($bytes) < 50 * 1024);

echo PHP_EOL . str_repeat('-', 60) . PHP_EOL;
echo "Resultado: " . ($total - $fallas) . " de {$total} pruebas correctas." . PHP_EOL;
exit($fallas === 0 ? 0 : 1);
