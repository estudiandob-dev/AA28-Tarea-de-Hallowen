<?php
/**
 * Votos de un disfraz
 *   POST   /disfraces/{id}/votos -> votar (cada usuario, una vez por disfraz)
 *   DELETE /disfraces/{id}/votos -> quitar el propio voto
 *
 * El voto duplicado se impide en dos niveles: la API responde 409 y, por debajo,
 * el índice único (id_usuario, id_disfraz) de la tabla `votos` lo rechaza siempre.
 */

/** Recalcula la columna `votos` contando la tabla `votos`, para que nunca se desincronicen. */
function votos_recalcular(int $idDisfraz): int
{
    consulta(
        'UPDATE disfraces SET votos = (SELECT COUNT(*) FROM votos WHERE id_disfraz = :contar) WHERE id = :id',
        ['contar' => $idDisfraz, 'id' => $idDisfraz]
    );
    return (int) consulta('SELECT votos FROM disfraces WHERE id = ?', [$idDisfraz])->fetchColumn();
}

/** Solo se vota un disfraz publicado (no eliminado). */
function votos_disfraz_publicado(int $id): void
{
    $existe = consulta('SELECT id FROM disfraces WHERE id = ? AND eliminado = 0', [$id])->fetch();
    if (!$existe) {
        throw new ErrorHttp(404, 'No existe un disfraz con ese id.');
    }
}

function votos_crear(int $idDisfraz): void
{
    $usuario = requerir_usuario();
    votos_disfraz_publicado($idDisfraz);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        consulta('INSERT INTO votos (id_usuario, id_disfraz) VALUES (?, ?)', [$usuario['id'], $idDisfraz]);
        $total = votos_recalcular($idDisfraz);
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        if (es_violacion_de_restriccion($e)) {
            throw new ErrorHttp(409, 'Ya votaste este disfraz.');
        }
        throw $e;
    }

    responder(201, ['id' => $idDisfraz, 'votos' => $total, 'votado' => true]);
}

function votos_eliminar(int $idDisfraz): void
{
    $usuario = requerir_usuario();
    votos_disfraz_publicado($idDisfraz);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $borrados = consulta(
            'DELETE FROM votos WHERE id_usuario = ? AND id_disfraz = ?',
            [$usuario['id'], $idDisfraz]
        )->rowCount();
        $total = votos_recalcular($idDisfraz);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    if ($borrados === 0) {
        throw new ErrorHttp(404, 'No habías votado este disfraz.');
    }
    responder(200, ['id' => $idDisfraz, 'votos' => $total, 'votado' => false]);
}
