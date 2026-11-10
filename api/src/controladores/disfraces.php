<?php
/**
 * Recurso /disfraces
 *   GET    /disfraces             -> lista (públicos, ordenados por votos)
 *   GET    /disfraces?todos=1     -> lista incluyendo eliminados (solo admin)
 *   POST   /disfraces             -> crear (cualquier usuario con sesión)
 *   GET    /disfraces/{id}        -> ver uno
 *   PUT    /disfraces/{id}        -> modificar (solo admin)
 *   PATCH  /disfraces/{id}        -> cambiar solo "eliminado", p. ej. restaurar (solo admin)
 *   DELETE /disfraces/{id}        -> eliminar, baja lógica con eliminado = 1 (solo admin)
 *   GET    /disfraces/{id}/foto   -> la imagen guardada en la base
 */

const DISFRAZ_COLUMNAS = 'd.id, d.nombre, d.descripcion, d.votos, d.eliminado, LENGTH(d.foto_blob) AS foto_bytes,
    (SELECT COUNT(*) FROM votos v WHERE v.id_disfraz = d.id AND v.id_usuario = :usuario) AS votado';

/** Convierte una fila de la base en el JSON que devuelve la API. */
function disfraz_json(array $fila, ?array $usuario): array
{
    $id = (int) $fila['id'];
    $bytesFoto = (int) $fila['foto_bytes'];
    $disfraz = [
        'id'          => $id,
        'nombre'      => $fila['nombre'],
        'descripcion' => $fila['descripcion'],
        'votos'       => (int) $fila['votos'],
        // ?v= cambia cuando cambia la foto, así el navegador no muestra una vieja.
        'foto_url'    => $bytesFoto > 0 ? API_BASE . "disfraces/{$id}/foto?v={$bytesFoto}" : null,
        'votado'      => (int) $fila['votado'] > 0,
    ];
    if ($usuario !== null && $usuario['admin']) {
        $disfraz['eliminado'] = (int) $fila['eliminado'] === 1;
    }
    return $disfraz;
}

/** Busca un disfraz visible para el usuario; los eliminados solo los ve el admin. */
function disfraz_buscar(int $id, ?array $usuario): array
{
    $fila = consulta(
        'SELECT ' . DISFRAZ_COLUMNAS . ' FROM disfraces d WHERE d.id = :id',
        ['usuario' => $usuario['id'] ?? 0, 'id' => $id]
    )->fetch();

    $visible = $fila && ((int) $fila['eliminado'] === 0 || ($usuario !== null && $usuario['admin']));
    if (!$visible) {
        throw new ErrorHttp(404, 'No existe un disfraz con ese id.');
    }
    return $fila;
}

function disfraces_listar(): void
{
    $usuario = usuario_actual();
    $todos = ($_GET['todos'] ?? '') === '1';
    if ($todos && !($usuario !== null && $usuario['admin'])) {
        throw new ErrorHttp(403, 'Solo el administrador puede ver los disfraces eliminados.');
    }

    $sql = 'SELECT ' . DISFRAZ_COLUMNAS . ' FROM disfraces d'
        . ($todos ? '' : ' WHERE d.eliminado = 0')
        . ' ORDER BY d.eliminado ASC, d.votos DESC, d.id ASC';
    $filas = consulta($sql, ['usuario' => $usuario['id'] ?? 0])->fetchAll();

    responder(200, array_map(function (array $fila) use ($usuario): array {
        return disfraz_json($fila, $usuario);
    }, $filas));
}

function disfraces_ver(int $id): void
{
    $usuario = usuario_actual();
    responder(200, disfraz_json(disfraz_buscar($id, $usuario), $usuario));
}

function disfraces_crear(): void
{
    $usuario = requerir_usuario();
    $datos = leer_json();

    [$nombre, $descripcion, $errores] = validar_textos_disfraz($datos);
    $mime = '';
    $bytes = '';
    if (isset($datos['foto']) && $datos['foto'] !== '') {
        try {
            [$mime, $bytes] = procesar_foto($datos['foto']);
        } catch (ErrorHttp $e) {
            $errores += $e->campos;
        }
    }
    if ($errores) {
        throw new ErrorHttp(422, 'Revisá los datos del disfraz.', $errores);
    }

    consulta(
        'INSERT INTO disfraces (nombre, descripcion, votos, foto, foto_blob, eliminado) VALUES (?, ?, 0, ?, ?, 0)',
        [$nombre, $descripcion, $mime, new Binario($bytes)]
    );
    $id = (int) db()->lastInsertId();

    responder(201, disfraz_json(disfraz_buscar($id, $usuario), $usuario), ['Location' => API_BASE . "disfraces/{$id}"]);
}

/**
 * Reemplaza nombre y descripción. La foto es opcional:
 *   - si no viene la clave "foto", se conserva la actual;
 *   - "foto": null la quita;
 *   - "foto": "data:image/...;base64,..." la reemplaza.
 */
function disfraces_actualizar(int $id): void
{
    $usuario = requerir_admin();
    disfraz_buscar($id, $usuario);
    $datos = leer_json();

    [$nombre, $descripcion, $errores] = validar_textos_disfraz($datos);
    $cambiarFoto = array_key_exists('foto', $datos);
    $mime = '';
    $bytes = '';
    if ($cambiarFoto && $datos['foto'] !== null && $datos['foto'] !== '') {
        try {
            [$mime, $bytes] = procesar_foto($datos['foto']);
        } catch (ErrorHttp $e) {
            $errores += $e->campos;
        }
    }
    if ($errores) {
        throw new ErrorHttp(422, 'Revisá los datos del disfraz.', $errores);
    }

    if ($cambiarFoto) {
        consulta(
            'UPDATE disfraces SET nombre = ?, descripcion = ?, foto = ?, foto_blob = ? WHERE id = ?',
            [$nombre, $descripcion, $mime, new Binario($bytes), $id]
        );
    } else {
        consulta('UPDATE disfraces SET nombre = ?, descripcion = ? WHERE id = ?', [$nombre, $descripcion, $id]);
    }

    responder(200, disfraz_json(disfraz_buscar($id, $usuario), $usuario));
}

/** Cambio parcial: por ahora solo admite {"eliminado": false} para restaurar (o true para eliminar). */
function disfraces_parchear(int $id): void
{
    $usuario = requerir_admin();
    disfraz_buscar($id, $usuario);
    $datos = leer_json();

    if (!array_key_exists('eliminado', $datos) || !is_bool($datos['eliminado'])) {
        $mensaje = 'Enviá {"eliminado": false} para restaurar el disfraz o true para eliminarlo.';
        throw new ErrorHttp(422, $mensaje, ['eliminado' => $mensaje]);
    }

    consulta('UPDATE disfraces SET eliminado = ? WHERE id = ?', [$datos['eliminado'] ? 1 : 0, $id]);
    responder(200, disfraz_json(disfraz_buscar($id, $usuario), $usuario));
}

/**
 * Baja lógica: el disfraz deja de mostrarse pero sus votos quedan guardados
 * y el administrador lo puede restaurar.
 */
function disfraces_eliminar(int $id): void
{
    $usuario = requerir_admin();
    disfraz_buscar($id, $usuario);
    consulta('UPDATE disfraces SET eliminado = 1 WHERE id = ?', [$id]);
    responder(204);
}

/**
 * Devuelve la imagen binaria con su tipo MIME. La usan las etiquetas <img>.
 * La foto de un disfraz eliminado solo la puede pedir el administrador (con token).
 */
function disfraces_foto(int $id): void
{
    $fila = consulta('SELECT foto, foto_blob, eliminado FROM disfraces WHERE id = ?', [$id])->fetch();
    if ($fila && is_resource($fila['foto_blob'])) {
        $fila['foto_blob'] = stream_get_contents($fila['foto_blob']);
    }
    if (!$fila || (string) $fila['foto_blob'] === '') {
        throw new ErrorHttp(404, 'Ese disfraz no tiene foto.');
    }
    if ((int) $fila['eliminado'] === 1) {
        $usuario = usuario_actual();
        if ($usuario === null || !$usuario['admin']) {
            throw new ErrorHttp(404, 'Ese disfraz no tiene foto.');
        }
    }

    $bytes = (string) $fila['foto_blob'];
    $mime = in_array($fila['foto'], foto_tipos_permitidos(), true) ? $fila['foto'] : 'application/octet-stream';
    $etag = '"' . md5($bytes) . '"';

    header('Cache-Control: private, no-cache');
    header('ETag: ' . $etag);
    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($bytes));
    header("Content-Security-Policy: default-src 'none'");
    echo $bytes;
    exit;
}
