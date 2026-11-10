# Ficha de autoevaluación de calidad (ISO/IEC 25010)

**Proyecto:** Concurso de disfraces de Halloween, versión "La web del terror accesible"
**Normas aplicadas:** ISO/IEC 25010 (calidad del producto), WCAG 2.1 nivel AA (accesibilidad), OWASP Top 10 (seguridad)
**Fecha de la auditoría:** 10 de noviembre de 2026

## Matriz de calidad

| Característica ISO 25010 | Pregunta | Evidencia técnica en el código | Estado |
|---|---|---|---|
| **1. Adecuación funcional** | ¿El backend en PHP procesa y persiste datos en MySQL vía PDO? | `api/src/db.php` abre la conexión PDO a MySQL (`utf8mb4`) y ejecuta todas las consultas con `consulta()`. Los controladores de `api/src/controladores/` registran usuarios, inician sesión, hacen el CRUD de disfraces y guardan los votos en las tablas `usuarios`, `disfraces` y `votos`. Las fotos se persisten en `disfraces.foto_blob`. Las 41 pruebas de `pruebas/probar_api.php` recorren cada función. | [x] Cumple |
| **2. Eficiencia de desempeño** | ¿La API REST responde solicitudes JSON sin latencias desmedidas? | `GET /disfraces` no trae las fotos (usa `LENGTH(foto_blob)`): la lista pesa unos 1,5 KB. Las fotos se piden aparte por `GET /disfraces/{id}/foto` con `ETag`, y si no cambiaron el servidor responde `304` sin reenviarlas. El navegador achica cada foto a menos de 60 KB antes de subirla (`js/imagen.js`). Hay índices en `votos (id_usuario, id_disfraz)`, `votos (id_disfraz)` y `disfraces (eliminado, votos)`. `pruebas/probar_api.php` mide 20 pedidos y exige un p95 menor a 500 ms. El inicio de sesión fallido tarda 0,4 s a propósito, para frenar ataques de fuerza bruta. | [x] Cumple |
| **3. Compatibilidad** | ¿La arquitectura está desacoplada (Frontend JS Vanilla / Backend PHP)? | El frontend son páginas HTML estáticas con módulos de JavaScript sin frameworks (`js/`), que solo se comunican con el backend por `fetch()` y JSON (`js/api.js`). El backend no genera HTML: `api/index.php` es un router REST que responde JSON. La misma API la usan el navegador, `curl` y el script de pruebas. Si mod_rewrite no está disponible, alcanza con cambiar una línea (`API` en `js/api.js`). | [x] Cumple |
| **4. Fiabilidad** | ¿Se capturan excepciones PDO y se devuelven códigos HTTP semánticos (200, 400, 500)? | `api/src/bootstrap.php` tiene un manejador global: cada `ErrorHttp` se convierte en JSON con su código y cualquier otra excepción en un `500` que no muestra detalles (el detalle va al log de Apache). La `PDOException` de restricción (`SQLSTATE 23000`) responde `409` (usuario o voto duplicado) y la falla de conexión `503`. Los votos se guardan en una transacción con `rollBack()` y el contador se recalcula desde la tabla `votos`. Códigos usados: 200, 201, 204, 304, 400, 401, 403, 404, 405, 409, 413, 415, 422, 500 y 503. | [x] Cumple |
| **5. Seguridad (OWASP)** | ¿Las contraseñas usan BCRYPT y los Prepared Statements previenen SQLi? | `password_hash($clave, PASSWORD_DEFAULT)` genera hashes bcrypt (`$2y$10$…` en `database/halloween.sql`) y `password_verify()` los comprueba (`controladores/usuarios.php` y `sesion.php`). Todas las consultas son sentencias preparadas con `PDO::ATTR_EMULATE_PREPARES = false` (`db.php`); la prueba de inyección `admin' -- ` en el login responde 401. Además: el token JWT está firmado con HS256 y se rechazan `alg: none` y los tokens adulterados; el rol se controla en el servidor (403); los datos se insertan con `textContent` y una CSP solo permite scripts propios (XSS); se valida el tipo real de cada imagen y `.htaccess` bloquea `api/src/` y `database/`. | [x] Cumple |
| **6. Usabilidad / Accesibilidad** | ¿Lighthouse marca 100/100 y la interfaz es 100 % operable por teclado? | Se corrigieron 14 puntos de WCAG 2.1 AA (detalle abajo). Una auditoría con las reglas de Lighthouse, más contraste de bordes y placeholders, dio 0 problemas en 10 pantallas, también en un celular de 360 px. Todas las acciones se hacen con Tab, Enter y Escape, y el foco se ve siempre (`:focus-visible`). Las respuestas de `fetch()` se anuncian en regiones `aria-live="polite"` con `.textContent`. Captura de Lighthouse: `docs/lighthouse-accesibilidad.png`. | [x] Cumple |
| **7. Mantenibilidad** | ¿El código está estructurado en capas limpias y versionado en Git? | Capas: presentación (HTML y `css/estilos.css`); cliente (`js/api.js` para la API, `js/sesion.js` para el token, `js/ui.js` con componentes compartidos y un módulo por página); API (`api/index.php` como router, `api/src/controladores/` y servicios separados en `db.php`, `auth.php`, `jwt.php`, `validar.php` e `imagen.php`); datos (`database/halloween.sql`). La configuración está centralizada en `config.php`, hay pruebas automáticas y un `.gitignore` que deja fuera `config.local.php` (contraseñas locales). | [x] Cumple |

### Mejoras pendientes para un despliegue real

El proyecto corre en XAMPP de forma local. Antes de publicarlo en internet habría que:

- Servirlo por **HTTPS**: el token viaja en cada petición.
- Mover el secreto JWT y la clave de MySQL a `config.local.php`, que no se sube al repositorio.
- Agregar un **límite de intentos de login** por IP. Hoy cada intento fallido solo tarda 0,4 s más.

## Accesibilidad (WCAG 2.1 nivel AA)

### Qué se corrigió

| # | Problema encontrado | Criterio WCAG | Corrección |
|---|---|---|---|
| 1 | El enlace de la foto del disfraz que va ganando no tenía nombre accesible: Lighthouse lo marca como `link-name`. | 2.4.4, 4.1.2 | `aria-label="Ver el disfraz que va ganando: …"` (`js/index.js`). |
| 2 | Los bordes de los campos tenían un contraste de 1,45:1 y casi no se distinguían del panel. | 1.4.11 | Color `--borde-campo` (#8a7aa6): 3,87:1. |
| 3 | El texto de ejemplo (placeholder) tenía 3,72:1. | 1.4.3 | `::placeholder` con el color `--niebla`: 8,28:1. |
| 4 | En la tabla de administración, los disfraces eliminados usaban `opacity`: el texto quedaba en 3,22:1. | 1.4.3 | Solo se apaga la foto; el texto pasa a gris con 7,24:1. |
| 5 | El aviso flotante aparecía con una transición de opacidad y, en plena animación, el texto tenía 2,5:1. | 1.4.3 | Se muestra con `visibility` y solo se anima el desplazamiento. |
| 6 | Al votar o guardar, el botón se deshabilitaba con `disabled` y perdía el foco: con teclado había que volver a empezar desde arriba. | 2.1.1, 2.4.3 | `aria-disabled="true"` mientras espera (`ocupar()` en `js/ui.js`). |
| 7 | En administración, después de editar, eliminar o restaurar, la tabla se redibujaba y el foco se perdía. | 2.4.3 | `cargar({ id, accion })` devuelve el foco al botón de la misma fila. |
| 8 | El botón "Quitar voto" se anunciaba como "Quitar tu voto a…", que no coincide con el texto visible. | 2.5.3 | Nombre accesible "Quitar voto a…". |
| 9 | Los nombres de los disfraces no eran títulos: un lector de pantalla no podía saltar entre ellos. | 1.3.1, 2.4.6 | Cada nombre es un `<h3>` dentro del `<figcaption>`, respetando el orden h1, h2, h3. |
| 10 | El sello "Va ganando 4 votos" se leía "Va ganando4votos". | 1.3.1 | Espacios entre las partes. |
| 11 | Con la contraseña incorrecta, el foco quedaba en el botón y no en el campo a corregir. | 3.3.1 | El foco vuelve al campo Contraseña. |
| 12 | El enlace "Ir al contenido" movía la vista, pero no el foco. | 2.4.1 | `<main id="contenido" tabindex="-1">`. |
| 13 | El estado de carga del muro y del panel no se anunciaba. | 4.1.3 | `role="status"` y `aria-live="polite"` en `#estado`; `aria-busy` en la lista mientras carga. |
| 14 | La casilla "Quitar la foto actual" medía 17 px. | 2.5.5 (buena práctica) | 24 × 24 px. |

### Lo que ya estaba bien y se verificó

- `lang="es-AR"`, un `<title>` por página y `<label for>` en cada campo, con los errores asociados por `aria-describedby` y `aria-invalid`.
- `alt` en todas las imágenes; las decorativas tienen `alt=""`.
- No hay `<div>` interactivos: todo lo que se toca es `<button>` o `<a>`. Los diálogos son `<dialog>` nativos (Escape los cierra y el foco vuelve al botón que los abrió).
- Hay un foco visible en todos los elementos (`:focus-visible` con contorno de 3 px) y no se usa `tabindex` positivo.
- `prefers-reduced-motion` desactiva las animaciones.
- Las respuestas de `fetch()` se muestran en `#aviso` (`role="status"`, `aria-live="polite"`) y siempre se insertan con `.textContent`, nunca con `innerHTML`.

### Contraste de colores

| Texto | Fondo | Contraste | Mínimo AA |
|---|---|---|---|
| Texto principal `#f2e8d5` | Noche `#211631` | 14,12:1 | 4,5:1 |
| Texto principal `#f2e8d5` | Panel `#2d2043` | 12,35:1 | 4,5:1 |
| Texto secundario `#bcaed3` | Noche `#211631` | 8,28:1 | 4,5:1 |
| Texto secundario `#bcaed3` | Panel `#2d2043` | 7,24:1 | 4,5:1 |
| Texto de botón `#1d1428` | Calabaza `#ff7a1a` | 6,81:1 | 4,5:1 |
| Calabaza `#ff7a1a` (foco, enlaces) | Noche `#211631` | 6,58:1 | 3:1 |
| Error `#ff7b8a` | Panel `#2d2043` | 6,04:1 | 4,5:1 |
| Confirmación `#a3dc86` | Noche `#211631` | 10,74:1 | 4,5:1 |
| Borde de campo `#8a7aa6` | Panel `#2d2043` | 3,87:1 | 3:1 (1.4.11) |

## Prueba de navegación por teclado

Teclas: **Tab** avanza, **Shift + Tab** retrocede, **Enter** activa enlaces y botones (también **Espacio** en los botones) y **Escape** cierra los diálogos.

| Página | Orden del foco con Tab |
|---|---|
| `index.html` | Ir al contenido, logo, Disfraces, Subí el tuyo, Ingresar, Crear cuenta, Subí tu disfraz, foto del que va ganando y el botón Votar de cada disfraz. |
| `login.html` | Ir al contenido, menú, Usuario, Contraseña, Ingresar, Creá una. Enter dentro de un campo envía el formulario. |
| `subir.html` | Ir al contenido, menú, Salir, Nombre, Descripción, Foto (Enter abre el selector de archivos), Publicar disfraz. |
| `admin.html` | Ir al contenido, menú, Administrar, Salir, Agregar disfraz y, por cada fila, Editar y Eliminar (o Restaurar). El diálogo de eliminar abre con el foco en Cancelar. |

Casos comprobados automáticamente:

- El primer Tab muestra "Ir al contenido" y Enter lleva el foco al contenido.
- Votar y quitar el voto con Enter deja el foco en el mismo botón.
- Con la contraseña incorrecta, el foco vuelve al campo Contraseña.
- Escape cierra el diálogo de edición y el foco vuelve a Editar.
- Guardar con Enter deja el foco en la misma fila.
- Eliminar deja el foco en Restaurar, y restaurar lo deja en Eliminar.

## Pruebas automáticas de la API

Con Apache y MySQL encendidos:

```
C:\xampp\php\php.exe pruebas\probar_api.php
```

El script hace 41 comprobaciones de caja negra sobre la API:

- Rutas y formato: 404, 405, 400 y 415.
- Lectura pública de los disfraces.
- Registro e inicio de sesión: 422, 409, 401 y token adulterado.
- Votos: 201, 409 por voto duplicado y quitar el voto.
- Alta de disfraces con foto.
- Permisos de administrador: 403, edición, baja lógica y restauración.
- Inyección SQL y archivos protegidos.
- Tiempos de respuesta.

Al final muestra el resultado y devuelve el código de salida 0 si todo pasó. Crea un usuario y un disfraz de prueba; para volver a los datos de ejemplo, importá de nuevo `database/halloween.sql`.
