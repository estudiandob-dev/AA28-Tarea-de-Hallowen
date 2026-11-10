<?php
/**
 * Conexión a MySQL con PDO. Todas las consultas usan sentencias preparadas,
 * así los datos del usuario nunca se mezclan con el SQL (previene inyección SQL).
 */

/** Envuelve datos binarios (la foto) para enviarlos como BLOB. */
class Binario
{
    /** @var string */
    public $bytes;

    public function __construct(string $bytes)
    {
        $this->bytes = $bytes;
    }
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = config('db');
    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    if (strpos($c['dsn'], 'mysql:') === 0) {
        // Preparación real en el servidor: los parámetros viajan separados de la consulta.
        $opciones[PDO::ATTR_EMULATE_PREPARES] = false;
    }

    try {
        $pdo = new PDO($c['dsn'], $c['usuario'], $c['clave'], $opciones);
    } catch (PDOException $e) {
        error_log('Halloween API - no se pudo conectar a la base: ' . $e->getMessage());
        throw new ErrorHttp(
            503,
            'No se pudo conectar a la base de datos. Revisá que MySQL esté encendido en XAMPP y que hayas importado database/halloween.sql.'
        );
    }
    return $pdo;
}

/**
 * Ejecuta una consulta preparada.
 *
 * @param array<int|string,mixed> $parametros Posicionales (?) o con nombre (:nombre).
 */
function consulta(string $sql, array $parametros = []): PDOStatement
{
    $sentencia = db()->prepare($sql);
    foreach ($parametros as $clave => $valor) {
        $marcador = is_int($clave) ? $clave + 1 : $clave;
        if ($valor instanceof Binario) {
            $sentencia->bindValue($marcador, $valor->bytes, PDO::PARAM_LOB);
        } elseif (is_int($valor)) {
            $sentencia->bindValue($marcador, $valor, PDO::PARAM_INT);
        } elseif ($valor === null) {
            $sentencia->bindValue($marcador, null, PDO::PARAM_NULL);
        } else {
            $sentencia->bindValue($marcador, (string) $valor, PDO::PARAM_STR);
        }
    }
    $sentencia->execute();
    return $sentencia;
}

/** true si la excepción es una violación de clave única o foránea (SQLSTATE 23000). */
function es_violacion_de_restriccion(PDOException $e): bool
{
    return (string) $e->getCode() === '23000';
}
