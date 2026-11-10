<?php
/**
 * Tokens JWT firmados con HMAC-SHA256 (HS256).
 *
 * La API es stateless: no guarda sesiones en el servidor. Al iniciar sesión se
 * entrega un token firmado que el navegador envía en cada petición con el
 * encabezado "Authorization: Bearer <token>". Si alguien modifica el contenido
 * del token, la firma deja de coincidir y la API lo rechaza.
 */

function base64url_codificar(string $datos): string
{
    return rtrim(strtr(base64_encode($datos), '+/', '-_'), '=');
}

function base64url_decodificar(string $texto)
{
    $relleno = strlen($texto) % 4;
    if ($relleno > 0) {
        $texto .= str_repeat('=', 4 - $relleno);
    }
    return base64_decode(strtr($texto, '-_', '+/'), true);
}

function jwt_crear(array $carga, string $secreto): string
{
    $cabecera = base64url_codificar(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $cuerpo = base64url_codificar(json_encode($carga, JSON_UNESCAPED_UNICODE));
    $firma = base64url_codificar(hash_hmac('sha256', $cabecera . '.' . $cuerpo, $secreto, true));
    return $cabecera . '.' . $cuerpo . '.' . $firma;
}

/**
 * Devuelve la carga del token si la firma es válida y no venció; si no, null.
 */
function jwt_verificar(string $token, string $secreto): ?array
{
    $partes = explode('.', $token);
    if (count($partes) !== 3) {
        return null;
    }
    [$cabecera, $cuerpo, $firma] = $partes;

    $datosCabecera = json_decode((string) base64url_decodificar($cabecera), true);
    // Solo se acepta HS256: evita el ataque de cambiar "alg" a "none".
    if (!is_array($datosCabecera) || ($datosCabecera['alg'] ?? '') !== 'HS256') {
        return null;
    }

    $esperada = base64url_codificar(hash_hmac('sha256', $cabecera . '.' . $cuerpo, $secreto, true));
    // hash_equals compara en tiempo constante (no filtra información por tiempos de respuesta).
    if (!hash_equals($esperada, $firma)) {
        return null;
    }

    $carga = json_decode((string) base64url_decodificar($cuerpo), true);
    if (!is_array($carga) || !isset($carga['exp']) || time() >= (int) $carga['exp']) {
        return null;
    }
    return $carga;
}
