<?php
/**
 * Recurso /sesion
 *   POST /sesion  -> iniciar sesión (devuelve un token)
 *   GET  /sesion  -> datos del usuario del token
 *
 * Cerrar sesión no necesita un endpoint: el token no se guarda en el servidor,
 * así que alcanza con que el navegador lo descarte (y vence solo).
 */

// Hash de una contraseña aleatoria. Se usa cuando el usuario no existe para que
// la respuesta tarde lo mismo y no se pueda averiguar qué nombres están registrados.
const HASH_SIN_USUARIO = '$2y$10$qg4Shfq6k9oe7hwD5QaZrOaXWLJNGHz6NK0KxLu0Xm6K3mK0HVqie';

/** Arma la respuesta con el token para un usuario autenticado. */
function sesion_respuesta(int $id, string $nombre): array
{
    $ahora = time();
    $vence = $ahora + (int) config('jwt_duracion');
    $token = jwt_crear(
        ['sub' => (string) $id, 'nombre' => $nombre, 'iat' => $ahora, 'exp' => $vence],
        (string) config('jwt_secreto')
    );

    return [
        'token'   => $token,
        'vence'   => $vence,
        'usuario' => ['id' => $id, 'nombre' => $nombre, 'admin' => es_admin($nombre)],
    ];
}

function sesion_crear(): void
{
    $datos = leer_json();
    $nombre = texto_limpio($datos['nombre'] ?? '');
    $clave = is_string($datos['clave'] ?? null) ? $datos['clave'] : '';

    $errores = [];
    if ($nombre === '') {
        $errores['nombre'] = 'Escribí tu nombre de usuario.';
    }
    if ($clave === '') {
        $errores['clave'] = 'Escribí tu contraseña.';
    }
    if ($errores) {
        throw new ErrorHttp(422, 'Completá usuario y contraseña.', $errores);
    }

    $fila = consulta('SELECT id, nombre, clave FROM usuarios WHERE nombre = ?', [$nombre])->fetch();
    $hash = $fila ? (string) $fila['clave'] : HASH_SIN_USUARIO;

    if (!password_verify($clave, $hash) || !$fila) {
        // Una pequeña demora hace más lento probar contraseñas a la fuerza.
        usleep(400000);
        throw new ErrorHttp(401, 'Usuario o contraseña incorrectos.');
    }

    // Si PHP cambia el algoritmo por defecto, el hash se actualiza al ingresar.
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        consulta('UPDATE usuarios SET clave = ? WHERE id = ?', [password_hash($clave, PASSWORD_DEFAULT), (int) $fila['id']]);
    }

    responder(200, sesion_respuesta((int) $fila['id'], (string) $fila['nombre']));
}

function sesion_ver(): void
{
    responder(200, requerir_usuario());
}
