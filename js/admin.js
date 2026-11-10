// Panel de administración: CRUD de disfraces.
// La página verifica el rol al cargar, pero la protección real está en la API:
// cada endpoint de administración responde 403 si el token no es del administrador.
import { pedir, pedirImagen } from './api.js';
import { usuarioActual } from './sesion.js';
import { comprimirImagen } from './imagen.js';
import {
  el, pintarCuenta, avisar, mostrarAvisoPendiente, limpiarErrores, mostrarErrores,
  contarCaracteres, irALogin, textoVotos, ocupar, estaOcupado,
} from './ui.js';

const estado = document.getElementById('estado');
const panel = document.getElementById('panel');
const filas = document.getElementById('filas');
const resumen = document.getElementById('resumen');

const dialogo = document.getElementById('dialogo-disfraz');
const formulario = document.getElementById('formulario');
const titulo = document.getElementById('dialogo-titulo');
const guardar = document.getElementById('guardar');
const miniatura = document.getElementById('miniatura');
const quitarCaja = document.getElementById('quitar-foto-caja');
const quitar = document.getElementById('quitar-foto');

const dialogoEliminar = document.getElementById('dialogo-eliminar');
const eliminarTexto = document.getElementById('eliminar-texto');

let disfraces = [];
let editando = null; // disfraz que se edita, o null si se agrega uno nuevo
let fotoNueva = null;
const fotosLocales = new Map(); // id -> URL local de la miniatura (fotos protegidas)

function manejarError(error) {
  if (error.estado === 401) {
    irALogin('Tu sesión venció. Ingresá de nuevo.');
  } else {
    avisar(error.message, 'error');
  }
}

async function verificarAdmin() {
  const usuario = usuarioActual();
  if (!usuario) {
    irALogin('Ingresá con la cuenta de administrador.');
    return false;
  }
  try {
    const confirmado = await pedir('GET', 'sesion');
    if (confirmado.admin) return true;
    estado.replaceChildren(
      `Esta página es solo para el administrador del concurso. Ingresaste como «${confirmado.nombre}». `,
    );
    const volver = el('a', '', 'Volver al muro');
    volver.href = 'index.html';
    estado.append(volver);
  } catch (error) {
    if (error.estado === 401) irALogin('Tu sesión venció. Ingresá de nuevo.');
    else estado.textContent = error.message;
  }
  return false;
}

function celdaFoto(disfraz) {
  const celda = el('td', 'tabla__foto');
  if (!disfraz.foto_url) {
    celda.append(el('span', 'tabla__sin-foto', 'Sin foto'));
    return celda;
  }
  const img = el('img');
  img.alt = '';
  img.width = 48;
  img.height = 60;
  if (disfraz.eliminado) {
    // La foto de un eliminado no es pública: se pide con el token.
    pedirImagen(disfraz.foto_url)
      .then((url) => { fotosLocales.set(disfraz.id, url); img.src = url; })
      .catch(() => { img.replaceWith(el('span', 'tabla__sin-foto', 'Sin foto')); });
  } else {
    img.src = disfraz.foto_url;
  }
  celda.append(img);
  return celda;
}

function botonAccion(texto, accion, disfraz, clase = 'boton boton--chico boton--secundario') {
  const boton = el('button', clase, texto);
  boton.type = 'button';
  boton.dataset.accion = accion;
  boton.dataset.id = String(disfraz.id);
  boton.setAttribute('aria-label', `${texto} «${disfraz.nombre}»`);
  return boton;
}

function crearFila(disfraz) {
  const fila = el('tr', disfraz.eliminado ? 'tabla__fila--eliminada' : '');

  const datos = el('td', 'tabla__disfraz');
  datos.append(el('strong', '', disfraz.nombre), el('span', 'tabla__descripcion', disfraz.descripcion));

  const votos = el('td', 'tabla__numero', String(disfraz.votos));
  votos.title = disfraz.votos === 0 ? textoVotos(0) : `${disfraz.votos} ${textoVotos(disfraz.votos)}`;

  const estadoCelda = el('td');
  estadoCelda.append(el('span', `etiqueta ${disfraz.eliminado ? 'etiqueta--eliminado' : 'etiqueta--publicado'}`, disfraz.eliminado ? 'Eliminado' : 'Publicado'));

  const acciones = el('td', 'tabla__acciones');
  acciones.append(botonAccion('Editar', 'editar', disfraz));
  acciones.append(disfraz.eliminado
    ? botonAccion('Restaurar', 'restaurar', disfraz)
    : botonAccion('Eliminar', 'eliminar', disfraz, 'boton boton--chico boton--peligro-suave'));

  fila.append(celdaFoto(disfraz), datos, votos, estadoCelda, acciones);
  return fila;
}

/**
 * Vuelve a dibujar la tabla. Como las filas se reemplazan, el botón que tenía el foco
 * desaparece: `enfocar` indica qué botón de la tabla nueva debe recibirlo, así quien
 * usa el teclado sigue en la misma fila.
 */
async function cargar(enfocar = null) {
  try {
    disfraces = await pedir('GET', 'disfraces?todos=1');
  } catch (error) {
    manejarError(error);
    return;
  }
  fotosLocales.forEach((url) => URL.revokeObjectURL(url));
  fotosLocales.clear();

  filas.replaceChildren(...disfraces.map(crearFila));
  const eliminados = disfraces.filter((d) => d.eliminado).length;
  const publicados = disfraces.length - eliminados;
  resumen.textContent = `${publicados} ${publicados === 1 ? 'publicado' : 'publicados'}, ${eliminados} ${eliminados === 1 ? 'eliminado' : 'eliminados'}.`;
  if (!disfraces.length) {
    const fila = el('tr');
    const celda = el('td', 'tabla__vacia', 'Todavía no hay disfraces. Usá «Agregar disfraz» para cargar el primero.');
    celda.colSpan = 5;
    fila.append(celda);
    filas.append(fila);
  }
  if (enfocar) {
    const boton = filas.querySelector(`button[data-id="${enfocar.id}"][data-accion="${enfocar.accion}"]`)
      ?? filas.querySelector(`button[data-id="${enfocar.id}"]`);
    boton?.focus();
  }
}

// --- Agregar y editar --------------------------------------------------------

function mostrarMiniatura(src) {
  miniatura.hidden = !src;
  if (src) miniatura.src = src;
  else miniatura.removeAttribute('src');
}

function abrirFormulario(disfraz = null) {
  editando = disfraz;
  fotoNueva = null;
  formulario.reset();
  limpiarErrores(formulario);

  titulo.textContent = disfraz ? 'Editar disfraz' : 'Agregar disfraz';
  guardar.textContent = disfraz ? 'Guardar cambios' : 'Agregar';
  formulario.nombre.value = disfraz?.nombre ?? '';
  formulario.descripcion.value = disfraz?.descripcion ?? '';
  formulario.nombre.dispatchEvent(new Event('input'));
  formulario.descripcion.dispatchEvent(new Event('input'));

  const fotoActual = disfraz?.foto_url ? (fotosLocales.get(disfraz.id) ?? disfraz.foto_url) : null;
  mostrarMiniatura(fotoActual);
  quitarCaja.hidden = !fotoActual;
  document.getElementById('etiqueta-foto').firstChild.textContent = fotoActual ? 'Reemplazar foto ' : 'Foto ';

  dialogo.showModal();
}

formulario.foto.addEventListener('change', async () => {
  const salida = document.getElementById('error-foto');
  salida.textContent = '';
  formulario.foto.removeAttribute('aria-invalid');
  fotoNueva = null;
  const archivo = formulario.foto.files[0];
  if (!archivo) return;
  try {
    fotoNueva = await comprimirImagen(archivo);
    quitar.checked = false;
    mostrarMiniatura(fotoNueva);
  } catch (error) {
    salida.textContent = error.message;
    formulario.foto.setAttribute('aria-invalid', 'true');
    formulario.foto.value = '';
  }
});

quitar.addEventListener('change', () => {
  if (quitar.checked) {
    fotoNueva = null;
    formulario.foto.value = '';
  }
});

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

  const cuerpo = { nombre, descripcion };
  if (fotoNueva) cuerpo.foto = fotoNueva;
  else if (editando && quitar.checked) cuerpo.foto = null;

  if (estaOcupado(guardar)) return;
  ocupar(guardar, true);
  try {
    if (editando) {
      await pedir('PUT', `disfraces/${editando.id}`, cuerpo);
      avisar(`Guardaste los cambios de «${nombre}».`);
      dialogo.close();
      await cargar({ id: editando.id, accion: 'editar' });
    } else {
      await pedir('POST', 'disfraces', cuerpo);
      avisar(`Agregaste «${nombre}».`);
      dialogo.close(); // el foco vuelve solo al botón "Agregar disfraz"
      await cargar();
    }
  } catch (error) {
    if (error.estado === 401) {
      manejarError(error);
      return;
    }
    mostrarErrores(formulario, error.campos, error.message);
  } finally {
    ocupar(guardar, false);
  }
});

dialogo.querySelector('[data-cerrar]').addEventListener('click', () => dialogo.close());

// --- Eliminar y restaurar -----------------------------------------------------

function confirmarEliminacion(disfraz) {
  let votos = `Sus ${disfraz.votos} votos quedan guardados`;
  if (disfraz.votos === 0) votos = 'No tiene votos';
  if (disfraz.votos === 1) votos = 'Su único voto queda guardado';
  eliminarTexto.textContent = `«${disfraz.nombre}» deja de verse en el concurso. ${votos} y lo podés restaurar cuando quieras.`;
  dialogoEliminar.returnValue = '';
  dialogoEliminar.showModal();
  return new Promise((resolver) => {
    dialogoEliminar.addEventListener('close', () => resolver(dialogoEliminar.returnValue === 'eliminar'), { once: true });
  });
}

async function eliminar(disfraz) {
  if (!(await confirmarEliminacion(disfraz))) return;
  try {
    await pedir('DELETE', `disfraces/${disfraz.id}`);
    avisar(`Eliminaste «${disfraz.nombre}».`);
    await cargar({ id: disfraz.id, accion: 'restaurar' });
  } catch (error) {
    manejarError(error);
  }
}

async function restaurar(disfraz) {
  try {
    await pedir('PATCH', `disfraces/${disfraz.id}`, { eliminado: false });
    avisar(`Restauraste «${disfraz.nombre}».`);
    await cargar({ id: disfraz.id, accion: 'eliminar' });
  } catch (error) {
    manejarError(error);
  }
}

filas.addEventListener('click', (evento) => {
  const boton = evento.target.closest('button[data-accion]');
  if (!boton) return;
  const disfraz = disfraces.find((d) => d.id === Number(boton.dataset.id));
  if (!disfraz) return;
  if (boton.dataset.accion === 'editar') abrirFormulario(disfraz);
  if (boton.dataset.accion === 'eliminar') eliminar(disfraz);
  if (boton.dataset.accion === 'restaurar') restaurar(disfraz);
});

document.getElementById('agregar').addEventListener('click', () => abrirFormulario());
contarCaracteres(formulario.nombre, document.getElementById('contador-nombre'));
contarCaracteres(formulario.descripcion, document.getElementById('contador-descripcion'));

pintarCuenta();
mostrarAvisoPendiente();
if (await verificarAdmin()) {
  estado.hidden = true;
  panel.hidden = false;
  await cargar();
}
