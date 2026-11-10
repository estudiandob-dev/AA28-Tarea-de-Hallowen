// Página para que cualquier usuario con sesión suba su disfraz con foto.
import { pedir } from './api.js';
import { usuarioActual } from './sesion.js';
import { comprimirImagen } from './imagen.js';
import {
  pintarCuenta, mostrarAvisoPendiente, limpiarErrores, mostrarErrores, contarCaracteres,
  crearPolaroid, guardarAviso, irALogin, ocupar, estaOcupado,
} from './ui.js';

if (!usuarioActual()) {
  irALogin('Ingresá para subir tu disfraz.');
}

const formulario = document.getElementById('formulario');
const previa = document.getElementById('previa');
const previaDescripcion = document.getElementById('previa-descripcion');
let foto = null; // data URL ya comprimida

function pintarPrevia() {
  const nombre = formulario.nombre.value.trim() || 'Nombre del disfraz';
  const polaroid = crearPolaroid({ nombre, foto_url: foto }, { carga: 'eager' });
  polaroid.classList.add('polaroid--grande');
  previa.replaceChildren(polaroid);
  previaDescripcion.textContent = formulario.descripcion.value.trim();
}

formulario.foto.addEventListener('change', async () => {
  const salida = document.getElementById('error-foto');
  salida.textContent = '';
  formulario.foto.removeAttribute('aria-invalid');
  foto = null;
  const archivo = formulario.foto.files[0];
  if (archivo) {
    try {
      foto = await comprimirImagen(archivo);
    } catch (error) {
      salida.textContent = error.message;
      formulario.foto.setAttribute('aria-invalid', 'true');
      formulario.foto.value = '';
    }
  }
  pintarPrevia();
});

formulario.nombre.addEventListener('input', pintarPrevia);
formulario.descripcion.addEventListener('input', pintarPrevia);
contarCaracteres(formulario.nombre, document.getElementById('contador-nombre'));
contarCaracteres(formulario.descripcion, document.getElementById('contador-descripcion'));

formulario.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  limpiarErrores(formulario);

  const nombre = formulario.nombre.value.trim();
  const descripcion = formulario.descripcion.value.trim();
  const errores = {};
  if (nombre.length < 2) errores.nombre = 'Escribí un nombre de al menos 2 caracteres.';
  if (descripcion.length < 5) errores.descripcion = 'Escribí una descripción de al menos 5 caracteres.';
  if (Object.keys(errores).length) {
    mostrarErrores(formulario, errores);
    return;
  }

  const boton = formulario.querySelector('button[type="submit"]');
  if (estaOcupado(boton)) return;
  ocupar(boton, true, 'Publicando…');
  try {
    const cuerpo = { nombre, descripcion };
    if (foto) cuerpo.foto = foto;
    const creado = await pedir('POST', 'disfraces', cuerpo);
    guardarAviso(`Publicaste «${creado.nombre}».`);
    location.href = `index.html#disfraz-${creado.id}`;
  } catch (error) {
    if (error.estado === 401) {
      irALogin('Tu sesión venció. Ingresá de nuevo para publicar.');
      return;
    }
    mostrarErrores(formulario, error.campos, error.message);
    ocupar(boton, false, 'Publicar disfraz');
  }
});

pintarCuenta();
mostrarAvisoPendiente();
pintarPrevia();
