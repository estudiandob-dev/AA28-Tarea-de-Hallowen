// Página principal: muro de disfraces y votación.
import { pedir } from './api.js';
import { usuarioActual } from './sesion.js';
import {
  el, pintarCuenta, avisar, mostrarAvisoPendiente, textoVotos, crearPolaroid, irALogin, ocupar, estaOcupado,
} from './ui.js';

const lista = document.getElementById('fotos');
const estado = document.getElementById('estado');
const lider = document.getElementById('lider');

/** @type {Array<{id:number,nombre:string,descripcion:string,votos:number,foto_url:string|null,votado:boolean}>} */
let disfraces = [];

function pintarVotos(contenedor, disfraz) {
  contenedor.replaceChildren();
  if (disfraz.votos > 0) contenedor.append(el('span', 'votos__numero', String(disfraz.votos)), ' ');
  contenedor.append(el('span', 'votos__texto', textoVotos(disfraz.votos)));
}

function pintarBoton(boton, disfraz) {
  boton.textContent = disfraz.votado ? 'Quitar voto' : 'Votar';
  boton.className = disfraz.votado ? 'boton boton--secundario' : 'boton';
  // El nombre accesible empieza con el texto visible del botón (WCAG 2.5.3).
  boton.setAttribute('aria-label', `${disfraz.votado ? 'Quitar voto a' : 'Votar a'} ${disfraz.nombre}`);
}

function crearTarjeta(disfraz) {
  const item = el('li', 'tarjeta');
  item.id = `disfraz-${disfraz.id}`;
  item.dataset.id = String(disfraz.id);

  const pie = el('div', 'tarjeta__pie');
  const votos = el('p', 'votos');
  pintarVotos(votos, disfraz);
  const nota = el('p', 'tarjeta__nota', disfraz.votado ? 'Lo votaste' : '');
  const conteo = el('div', 'tarjeta__conteo');
  conteo.append(votos, nota);

  const boton = el('button');
  boton.type = 'button';
  pintarBoton(boton, disfraz);
  pie.append(conteo, boton);

  item.append(crearPolaroid(disfraz, { titulo: 'h3' }), el('p', 'tarjeta__descripcion', disfraz.descripcion), pie);
  return item;
}

function actualizarTarjeta(disfraz) {
  const item = document.getElementById(`disfraz-${disfraz.id}`);
  if (!item) return;
  const votos = item.querySelector('.votos');
  pintarVotos(votos, disfraz);
  votos.classList.remove('votos--cambio');
  void votos.offsetWidth; // reinicia la animación
  votos.classList.add('votos--cambio');
  item.querySelector('.tarjeta__nota').textContent = disfraz.votado ? 'Lo votaste' : '';
  pintarBoton(item.querySelector('button'), disfraz);
}

function pintarLider() {
  lider.replaceChildren();
  const primero = disfraces.reduce((mejor, d) => (!mejor || d.votos > mejor.votos ? d : mejor), null);

  if (!primero || primero.votos === 0) {
    const vacio = el('div', 'lider-vacio');
    vacio.append(el('p', 'lider-vacio__texto', 'Todavía nadie va ganando. El primer voto define la noche.'));
    lider.append(vacio);
    return;
  }

  const polaroid = crearPolaroid(primero, { carga: 'eager' });
  polaroid.classList.add('polaroid--grande');
  const sello = el('p', 'sello');
  // Los espacios entre partes hacen que se lea "Va ganando 4 votos" y no "Va ganando4votos".
  sello.append(el('span', 'sello__arriba', 'Va ganando'), ' ', el('span', 'sello__numero', String(primero.votos)), ' ', el('span', 'sello__abajo', textoVotos(primero.votos)));
  const enlace = el('a', 'portada__enlace-lider');
  enlace.href = `#disfraz-${primero.id}`;
  // Un enlace que solo contiene una <figure> no tiene nombre accesible: se lo damos.
  enlace.setAttribute('aria-label', `Ver el disfraz que va ganando: ${primero.nombre}`);
  enlace.append(polaroid);
  lider.append(sello, enlace);
}

function pintar() {
  lista.replaceChildren(...disfraces.map(crearTarjeta));
  pintarLider();

  if (!disfraces.length) {
    estado.replaceChildren('Todavía no hay disfraces en el muro. ');
    const enlace = el('a', '', 'Subí el primero');
    enlace.href = 'subir.html';
    estado.append(enlace, '.');
    estado.hidden = false;
  } else {
    estado.hidden = true;
  }

  // Si se llegó con #disfraz-12 (por ejemplo, después de subir uno), se lo destaca.
  const destino = /^#disfraz-\d+$/.test(location.hash) && document.querySelector(location.hash);
  if (destino) {
    destino.classList.add('tarjeta--destacada');
    destino.scrollIntoView({ block: 'center' });
    // El foco va al botón de ese disfraz, para seguir desde ahí con el teclado.
    destino.querySelector('button').focus({ preventScroll: true });
  }
}

async function cargar() {
  lista.setAttribute('aria-busy', 'true');
  try {
    disfraces = await pedir('GET', 'disfraces');
  } catch (error) {
    lista.setAttribute('aria-busy', 'false');
    if (error.estado === 401) {
      // El token venció: api.js ya lo borró, se vuelve a pedir como visitante.
      pintarCuenta();
      return cargar();
    }
    estado.textContent = `No se pudieron cargar los disfraces. ${error.message}`;
    estado.classList.add('muro__estado--error');
    return;
  }
  lista.setAttribute('aria-busy', 'false');
  pintar();
}

async function alternarVoto(boton, disfraz) {
  if (!usuarioActual()) {
    irALogin('Ingresá para votar.');
    return;
  }

  if (estaOcupado(boton)) return;
  ocupar(boton, true);
  try {
    const respuesta = disfraz.votado
      ? await pedir('DELETE', `disfraces/${disfraz.id}/votos`)
      : await pedir('POST', `disfraces/${disfraz.id}/votos`);
    disfraz.votos = respuesta.votos;
    disfraz.votado = respuesta.votado;
    avisar(disfraz.votado ? `Votaste a «${disfraz.nombre}».` : `Quitaste tu voto a «${disfraz.nombre}».`);
  } catch (error) {
    if (error.estado === 401) {
      irALogin('Tu sesión venció. Ingresá de nuevo para votar.');
      return;
    }
    if (error.estado === 409) {
      disfraz.votado = true;
      avisar('Ya habías votado este disfraz.');
    } else if (error.estado === 404) {
      avisar('Ese disfraz ya no está en el concurso.', 'error');
      return cargar();
    } else {
      avisar(error.message, 'error');
    }
  } finally {
    ocupar(boton, false);
  }
  actualizarTarjeta(disfraz);
  pintarLider();
}

lista.addEventListener('click', (evento) => {
  const boton = evento.target.closest('button');
  if (!boton) return;
  const id = Number(boton.closest('.tarjeta').dataset.id);
  const disfraz = disfraces.find((d) => d.id === id);
  if (disfraz) alternarVoto(boton, disfraz);
});

pintarCuenta();
mostrarAvisoPendiente();
cargar();
