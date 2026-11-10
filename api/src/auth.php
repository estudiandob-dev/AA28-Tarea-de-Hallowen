<?php
/**
 * Autenticación y autorización.
 */

function es_admin(string $nombreUsuario): bool
{
    return $nombreUsuario === config('admin_usuario');
}

/** Lee el token del encabezado Authorization (o null si no vino). */
function token_de_la_peticion(): ?string
{
    $cabecera = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

    // Con PHP como módulo de Apache (XAMPP) el encabezado a veces solo aparece acá.
    if ($cabecera === '' && function_exists('apache_request_headers')) {
        foreach ((array) apache_request_headers() as $nombre => $valor) {
            if (strcasecmp((string) $nombre, 'Authorization') === 0) {
                $cabecera = (string) $valor;
                break;
            }
        }
    }

    if ($cabecera === '') {
        return null;
    }
    if (!preg_match('/^Bearer\s+(\S+)$/i', $cabecera, $coincidencia)) {
        throw new ErrorHttp(401, 'El encabezado Authorization tiene que ser "Bearer <token>".');
    }
    return $coincidencia[1];
}

/**
 * Usuario que hace la petición, o null si es anónima.
 * Un token inválido o vencido responde 401 para que el cliente sepa que debe volver a ingresar.
 *
 * @return array{id:int,nombre:string,admin:bool}|null
 */
function usuario_actual(): ?array
{
    static $resuelto = false;
    static $usuario = null;
    if ($resuelto) {
        return $usuario;
    }

    $token = token_de_la_peticion();
    if ($token !== null) {
        $carga = jwt_verificar($token, (string) config('jwt_secreto'));
        if ($carga === null || !isset($carga['sub'])) {
            throw new ErrorHttp(401, 'Tu sesión venció o no es válida. Ingresá de nuevo.');
        }
        // Se vuelve a leer de la base: si el usuario ya no existe, el token deja de servir.
        $fila = consulta('SELECT id, nombre FROM usuarios WHERE id = ?', [(int) $carga['sub']])->fetch();
        if (!$fila) {
            throw new ErrorHttp(401, 'Tu sesión venció o no es válida. Ingresá de nuevo.');
        }
        $usuario = [
            'id'     => (int) $fila['id'],
            'nombre' => $fila['nombre'],
            'admin'  => es_admin($fila['nombre']),
        ];
    }

    $resuelto = true;
    return $usuario;
}

/** @return array{id:int,nombre:string,admin:bool} */
function requerir_usuario(): array
{
    $usuario = usuario_actual();
    if ($usuario === null) {
        throw new ErrorHttp(401, 'Tenés que iniciar sesión para hacer esto.');
    }
    return $usuario;
}

/** @return array{id:int,nombre:string,admin:bool} */
function requerir_admin(): array
{
    $usuario = requerir_usuario();
    if (!$usuario['admin']) {
        throw new ErrorHttp(403, 'Solo el administrador puede hacer esto.');
    }
    return $usuario;
}
