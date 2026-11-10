// Cliente de la API REST.
import { obtenerToken, cerrarSesion } from './sesion.js';

// Carpeta de la API relativa a las páginas. Si tu Apache no tiene mod_rewrite,
// cambiala por 'api/index.php' y todo sigue funcionando.
export const API = 'api';

export class ErrorApi extends Error {
  constructor(estado, mensaje, campos = {}) {
    super(mensaje);
    this.estado = estado;
    this.campos = campos;
  }
}

const MENSAJES = {
  401: 'Tenés que iniciar sesión para hacer esto.',
  403: 'No tenés permiso para hacer esto.',
  404: 'No se encontró lo que buscabas.',
  500: 'El servidor tuvo un error. Probá de nuevo en un momento.',
};

/**
 * Hace una petición a la API y devuelve el JSON de la respuesta.
 * Si la respuesta es un error, lanza ErrorApi con el mensaje que mandó el servidor.
 */
export async function pedir(metodo, ruta, cuerpo) {
  const cabeceras = { Accept: 'application/json' };
  const token = obtenerToken();
  if (token) cabeceras.Authorization = `Bearer ${token}`;

  const opciones = { method: metodo, headers: cabeceras };
  if (cuerpo !== undefined) {
    cabeceras['Content-Type'] = 'application/json';
    opciones.body = JSON.stringify(cuerpo);
  }

  let respuesta;
  try {
    respuesta = await fetch(`${API}/${ruta}`, opciones);
  } catch {
    throw new ErrorApi(0, 'No hay conexión con el servidor. Revisá que Apache esté encendido en XAMPP.');
  }

  if (respuesta.status === 204) return null;

  const esJson = (respuesta.headers.get('Content-Type') || '').includes('application/json');
  const datos = esJson ? await respuesta.json().catch(() => null) : null;

  if (!respuesta.ok) {
    // Token vencido o inválido: se descarta para que la página vuelva a modo anónimo.
    if (respuesta.status === 401 && token) cerrarSesion();
    const mensaje = datos?.error
      ?? (esJson ? null : 'La API no respondió en JSON. Revisá que mod_rewrite esté activo (ver README).')
      ?? MENSAJES[respuesta.status]
      ?? `Error ${respuesta.status}.`;
    throw new ErrorApi(respuesta.status, mensaje, datos?.campos ?? {});
  }
  if (!esJson) {
    throw new ErrorApi(respuesta.status, 'La API no respondió en JSON. Revisá que mod_rewrite esté activo (ver README).');
  }
  return datos;
}

/**
 * Descarga una imagen protegida (con el token) y devuelve una URL local para <img>.
 * La usa el panel de administración para ver las fotos de disfraces eliminados.
 */
export async function pedirImagen(url) {
  const token = obtenerToken();
  const respuesta = await fetch(url, { headers: token ? { Authorization: `Bearer ${token}` } : {} });
  if (!respuesta.ok) throw new ErrorApi(respuesta.status, 'No se pudo cargar la foto.');
  return URL.createObjectURL(await respuesta.blob());
}
