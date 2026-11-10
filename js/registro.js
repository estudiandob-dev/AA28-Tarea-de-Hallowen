// Página de registro. Al crear la cuenta la API ya devuelve un token,
// así que la persona queda con la sesión iniciada.
import { pedir } from './api.js';
import { guardarSesion, usuarioActual } from './sesion.js';
import {
  pintarCuenta, limpiarErrores, mostrarErrores, destinoSeguro, guardarAviso, ocupar, estaOcupado,
} from './ui.js';

const formulario = document.getElementById('formulario');
const destino = destinoSeguro();
const PATRON_USUARIO = /^[\p{L}\p{N}_.-]{3,30}$/u;

document.getElementById('ir-login').href = `login.html?volver=${destino}`;

if (usuarioActual()) {
  location.replace(destino);
}

function validar(nombre, clave, repetir) {
  const errores = {};
  if (!PATRON_USUARIO.test(nombre)) {
    errores.nombre = 'Usá entre 3 y 30 letras, números, puntos o guiones, sin espacios.';
  }
  if (clave.length < 8) {
    errores.clave = 'La contraseña tiene que tener al menos 8 caracteres.';
  }
  if (!repetir) {
    errores.repetir = 'Repetí la contraseña.';
  } else if (clave !== repetir) {
    errores.repetir = 'Las contraseñas no coinciden.';
  }
  return errores;
}

formulario.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  limpiarErrores(formulario);

  const nombre = formulario.nombre.value.trim();
  const clave = formulario.clave.value;
  const errores = validar(nombre, clave, formulario.repetir.value);
  if (Object.keys(errores).length) {
    mostrarErrores(formulario, errores);
    return;
  }

  const boton = formulario.querySelector('button[type="submit"]');
  if (estaOcupado(boton)) return;
  ocupar(boton, true, 'Creando cuenta…');
  try {
    const sesion = await pedir('POST', 'usuarios', { nombre, clave });
    guardarSesion(sesion);
    guardarAviso(`Cuenta creada. Hola, ${sesion.usuario.nombre}.`);
    location.href = destino;
  } catch (error) {
    mostrarErrores(formulario, error.campos, error.message);
    ocupar(boton, false, 'Crear cuenta');
  }
});

pintarCuenta();
