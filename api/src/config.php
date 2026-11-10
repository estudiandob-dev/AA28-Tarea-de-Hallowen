<?php
/**
 * Configuración de la API.
 *
 * Los valores de la base de datos son los de una instalación estándar de XAMPP
 * (usuario root sin contraseña). Si tu MySQL es distinto, cambialos acá o creá
 * un archivo config.local.php en esta misma carpeta que devuelva solo las claves
 * que quieras pisar, por ejemplo:
 *
 *     <?php return ['db' => ['clave' => 'mi-clave']];
 */

return [
    'db' => [
        // 127.0.0.1 en lugar de "localhost" fuerza TCP y funciona igual en Windows, Linux y macOS.
        'dsn'     => 'mysql:host=127.0.0.1;port=3306;dbname=halloween;charset=utf8mb4',
        'usuario' => 'root',
        'clave'   => '',
    ],

    // La consigna fija las columnas de la tabla `usuarios`, que no tiene un campo de rol.
    // Por eso el administrador se identifica por su nombre de usuario. El script SQL
    // ya crea este usuario y la API no deja registrar otro con el mismo nombre.
    'admin_usuario' => 'admin',

    // Clave con la que se firman los tokens (JWT HS256). Si publicás el proyecto,
    // generá una nueva, por ejemplo con:  php -r "echo bin2hex(random_bytes(32));"
    'jwt_secreto' => '390abc1640b7fcf93e807392d1a4dcb7a3a0c50bfc5a4ba43f845848da43543a',

    // Duración de la sesión en segundos (4 horas).
    'jwt_duracion' => 4 * 60 * 60,

    // Límites de los datos de un disfraz.
    'disfraz_nombre_max'      => 50,   // igual que varchar(50)
    'disfraz_descripcion_max' => 500,

    // Tamaño máximo del cuerpo JSON de una petición (la foto viaja en base64).
    'cuerpo_max_bytes' => 6 * 1024 * 1024,
];
