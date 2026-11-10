<?php
/**
 * Recurso /usuarios
 *   POST /usuarios -> registrarse (y quedar con la sesión iniciada)
 */

function usuarios_registrar(): void
{
    $datos = leer_json();
    $nombre = texto_limpio($datos['nombre'] ?? '');
    $clave = is_string($datos['clave'] ?? null) ? $datos['clave'] : '';

    $errores = [];
    if (!preg_match('/^[\p{L}\p{N}_.\-]{3,30}$/u', $nombre)) {
        $errores['nombre'] = 'Usá entre 3 y 30 letras, números, puntos o guiones, sin espacios.';
    } elseif (minusculas($nombre) === minusculas((string) config('admin_usuario'))) {
        $errores['nombre'] = 'Ese nombre de usuario no está disponible.';
    }

    if (largo($clave) < 8) {
        $errores['clave'] = 'La contraseña tiene que tener al menos 8 caracteres.';
    } elseif (strlen($clave) > 72) {
        // bcrypt solo usa los primeros 72 bytes: más largo daría una falsa sensación de seguridad.
        $errores['clave'] = 'La contraseña puede tener hasta 72 caracteres.';
    }

    if ($errores) {
        throw new ErrorHttp(422, 'Revisá los datos del formulario.', $errores);
    }

    try {
        consulta(
            'INSERT INTO usuarios (nombre, clave) VALUES (?, ?)',
            [$nombre, password_hash($clave, PASSWORD_DEFAULT)]
        );
    } catch (PDOException $e) {
        // El índice único de `nombre` evita duplicados incluso si dos personas se registran a la vez.
        if (es_violacion_de_restriccion($e)) {
            $mensaje = 'Ese nombre de usuario ya está en uso. Probá con otro.';
            throw new ErrorHttp(409, $mensaje, ['nombre' => $mensaje]);
        }
        throw $e;
    }

    $id = (int) db()->lastInsertId();
    responder(201, sesion_respuesta($id, $nombre), ['Location' => API_BASE . 'sesion']);
}
