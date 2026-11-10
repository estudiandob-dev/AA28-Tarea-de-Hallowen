<?php
/**
 * Validación y limpieza de datos de entrada.
 */

/** Cantidad de caracteres (no de bytes) de un texto UTF-8. */
function largo(string $texto): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($texto, 'UTF-8');
    }
    return (int) preg_match_all('/./us', $texto);
}

function minusculas(string $texto): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
}

/**
 * Devuelve el valor como texto sin espacios al borde y sin caracteres de control.
 * Si no es un texto, devuelve ''. Con $multilinea se conservan los saltos de línea.
 *
 * @param mixed $valor
 */
function texto_limpio($valor, bool $multilinea = false): string
{
    if (!is_string($valor)) {
        return '';
    }
    $patron = $multilinea ? '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
    $limpio = preg_replace($patron, '', $valor);
    if ($multilinea) {
        $limpio = str_replace("\r", '', (string) $limpio);
        // Como mucho dos saltos de línea seguidos.
        $limpio = preg_replace("/\n{3,}/", "\n\n", $limpio);
    }
    return trim((string) $limpio);
}

/**
 * Valida nombre y descripción de un disfraz.
 *
 * @return array{0:string,1:string,2:array<string,string>} [nombre, descripcion, errores]
 */
function validar_textos_disfraz(array $datos): array
{
    $nombre = texto_limpio($datos['nombre'] ?? '');
    $descripcion = texto_limpio($datos['descripcion'] ?? '', true);
    $errores = [];

    $nombreMax = (int) config('disfraz_nombre_max');
    if (largo($nombre) < 2) {
        $errores['nombre'] = 'Escribí un nombre de al menos 2 caracteres.';
    } elseif (largo($nombre) > $nombreMax) {
        $errores['nombre'] = "El nombre puede tener hasta {$nombreMax} caracteres.";
    }

    $descripcionMax = (int) config('disfraz_descripcion_max');
    if (largo($descripcion) < 5) {
        $errores['descripcion'] = 'Escribí una descripción de al menos 5 caracteres.';
    } elseif (largo($descripcion) > $descripcionMax) {
        $errores['descripcion'] = "La descripción puede tener hasta {$descripcionMax} caracteres.";
    }

    return [$nombre, $descripcion, $errores];
}
