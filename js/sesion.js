// Sesión del usuario en el navegador.
// La API es stateless: el token JWT que devuelve POST /sesion se guarda acá y
// se manda en cada petición. Cerrar sesión es simplemente borrarlo.

const CLAVE = 'halloween.sesion';

function decodificarCarga(token) {
  try {
    const parte = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
    return JSON.parse(atob(parte));
  } catch {
    return null;
  }
}

function leer() {
  try {
    const datos = JSON.parse(localStorage.getItem(CLAVE));
    if (!datos || !datos.token || !datos.usuario) return null;
    const carga = decodificarCarga(datos.token);
    // Si el token ya venció no tiene sentido mandarlo: la API lo rechazaría.
    if (!carga || Date.now() >= carga.exp * 1000) {
      cerrarSesion();
      return null;
    }
    return datos;
  } catch {
    return null;
  }
}

export function guardarSesion({ token, usuario }) {
  try {
    localStorage.setItem(CLAVE, JSON.stringify({ token, usuario }));
  } catch {
    // Sin almacenamiento (modo privado estricto): la sesión dura lo que dure la página.
  }
}

export function cerrarSesion() {
  try {
    localStorage.removeItem(CLAVE);
  } catch {
    /* nada que borrar */
  }
}

export function obtenerToken() {
  return leer()?.token ?? null;
}

/** @returns {{id:number, nombre:string, admin:boolean} | null} */
export function usuarioActual() {
  return leer()?.usuario ?? null;
}
