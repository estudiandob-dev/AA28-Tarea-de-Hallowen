<?php
/**
 * Utilidades HTTP: respuestas JSON, errores con código de estado y lectura del cuerpo.
 */

/**
 * Error que se convierte en una respuesta JSON con el código HTTP indicado.
 * `campos` lleva los errores de validación por campo (útil para los formularios).
 */
class ErrorHttp extends Exception
{
    /** @var int */
    public $estado;
    /** @var array<string,string> */
    public $campos;
    /** @var array<string,string> */
    public $cabeceras;

    public function __construct(int $estado, string $mensaje, array $campos = [], array $cabeceras = [])
    {
        parent::__construct($mensaje);
        $this->estado = $estado;
        $this->campos = $campos;
        $this->cabeceras = $cabeceras;
    }
}

/**
 * Envía la respuesta y termina la ejecución.
 *
 * @param mixed $datos Se serializa como JSON. Con null (o estado 204) no se envía cuerpo.
 */
function responder(int $estado, $datos = null, array $cabeceras = []): void
{
    http_response_code($estado);
    foreach ($cabeceras as $nombre => $valor) {
        header($nombre . ': ' . $valor);
    }
    if ($estado === 204 || $datos === null) {
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Lee el cuerpo de la petición como JSON y devuelve un arreglo asociativo.
 */
function leer_json(): array
{
    $tipo = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
    if (stripos($tipo, 'application/json') !== 0) {
        throw new ErrorHttp(415, 'Enviá los datos en formato JSON (Content-Type: application/json).');
    }

    $maximo = (int) config('cuerpo_max_bytes');
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $maximo) {
        throw new ErrorHttp(413, 'Los datos enviados son demasiado grandes.');
    }
    $crudo = (string) file_get_contents('php://input', false, null, 0, $maximo + 1);
    if (strlen($crudo) > $maximo) {
        throw new ErrorHttp(413, 'Los datos enviados son demasiado grandes.');
    }
    if (trim($crudo) === '') {
        return [];
    }

    try {
        $datos = json_decode($crudo, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new ErrorHttp(400, 'El cuerpo de la petición no es un JSON válido.');
    }
    if (!is_array($datos)) {
        throw new ErrorHttp(400, 'El cuerpo de la petición tiene que ser un objeto JSON.');
    }
    return $datos;
}
