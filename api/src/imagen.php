<?php
/**
 * Procesamiento de las fotos de los disfraces.
 *
 * La foto llega como data URL en base64 ("data:image/jpeg;base64,...") dentro del JSON.
 * Se guarda en la columna `foto_blob` (tipo BLOB, máximo 65.535 bytes) y su tipo MIME
 * en la columna `foto`.
 *
 * Nunca se confía en lo que dice el cliente: el tipo real se detecta leyendo los bytes.
 * Si PHP tiene la extensión GD (XAMPP la trae), la imagen se vuelve a codificar como JPEG:
 * eso la achica para que entre en el BLOB y además borra metadatos (por ejemplo, la
 * ubicación GPS que guardan las fotos del celular).
 */

const FOTO_MAX_BYTES_BLOB = 65535;
const FOTO_MAX_BYTES_ENTRADA = 4 * 1024 * 1024;
const FOTO_MAX_PIXELES = 25000000;
const FOTO_LADO_MAX = 720;

/** @return array<int,string> tipo de imagen de PHP => MIME permitido */
function foto_tipos_permitidos(): array
{
    return [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG  => 'image/png',
        IMAGETYPE_GIF  => 'image/gif',
        IMAGETYPE_WEBP => 'image/webp',
    ];
}

/**
 * Valida la foto y la deja lista para guardar.
 *
 * @param mixed $valor
 * @return array{0:string,1:string} [mime, bytes]
 */
function procesar_foto($valor): array
{
    $invalida = function (string $mensaje): ErrorHttp {
        return new ErrorHttp(422, $mensaje, ['foto' => $mensaje]);
    };

    if (!is_string($valor) || !preg_match('#^data:image/[a-z0-9.+-]+;base64,([A-Za-z0-9+/=\s]+)$#', $valor, $partes)) {
        throw $invalida('La foto tiene que ser una imagen JPG, PNG, GIF o WebP.');
    }
    $bytes = base64_decode(preg_replace('/\s+/', '', $partes[1]), true);
    if ($bytes === false || $bytes === '') {
        throw $invalida('No se pudo leer la foto. Probá con otra imagen.');
    }
    if (strlen($bytes) > FOTO_MAX_BYTES_ENTRADA) {
        throw $invalida('La foto pesa más de 4 MB. Elegí una imagen más liviana.');
    }

    $info = @getimagesizefromstring($bytes);
    $tipos = foto_tipos_permitidos();
    if ($info === false || !isset($tipos[$info[2]])) {
        throw $invalida('El archivo no es una imagen JPG, PNG, GIF o WebP válida.');
    }
    if ($info[0] * $info[1] > FOTO_MAX_PIXELES) {
        throw $invalida('La foto tiene demasiados píxeles. Elegí una imagen más chica.');
    }
    $mime = $tipos[$info[2]];

    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        $jpeg = foto_recomprimir($bytes);
        if ($jpeg !== null) {
            return ['image/jpeg', $jpeg];
        }
    }

    if (strlen($bytes) > FOTO_MAX_BYTES_BLOB) {
        throw $invalida('La foto tiene que pesar menos de 64 KB.');
    }
    return [$mime, $bytes];
}

/**
 * Redimensiona y recomprime como JPEG hasta que entre en un BLOB.
 * Devuelve null si GD no puede abrir la imagen o no logra achicarla lo suficiente.
 */
function foto_recomprimir(string $bytes): ?string
{
    $original = @imagecreatefromstring($bytes);
    if ($original === false) {
        return null;
    }
    $ancho = imagesx($original);
    $alto = imagesy($original);
    $lado = FOTO_LADO_MAX;

    for ($intento = 0; $intento < 6; $intento++) {
        $escala = min(1, $lado / max($ancho, $alto));
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $lienzo = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        // Fondo blanco para las imágenes con transparencia (JPEG no la admite).
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
        imagecopyresampled($lienzo, $original, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        foreach ([82, 70, 58] as $calidad) {
            ob_start();
            imagejpeg($lienzo, null, $calidad);
            $salida = (string) ob_get_clean();
            if (strlen($salida) <= FOTO_MAX_BYTES_BLOB) {
                return $salida;
            }
        }
        $lado = (int) round($lado * 0.75);
    }
    return null;
}
