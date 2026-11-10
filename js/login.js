// Página de inicio de sesión.
import { pedir } from './api.js';
import { guardarSesion, usuarioActual } from './sesion.js';
import {
  pintarCuenta, mostrarAvisoPendiente, limpiarErrores, mostrarErrores, destinoSeguro, guardarAviso,
  ocupar, estaOcupado,
} from './ui.js';

const formulario = document.getElementById('formulario');
const destino = destinoSeguro();

// Se conserva el destino si la persona prefiere crear una cuenta.
document.getElementById('ir-registro').href = `registro.html?volver=${destino}`;

if (usuarioActual()) {
  location.replace(destino);
}

formulario.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  limpiarErrores(formulario);

  const nombre = formulario.nombre.value.trim();
  const clave = formulario.clave.value;
  const errores = {};
  if (!nombre) errores.nombre = 'Escribí tu nombre de usuario.';
  if (!clave) errores.clave = 'Escribí tu contraseña.';
  if (Object.keys(errores).length) {
    mostrarErrores(formulario, errores);
    return;
  }

  const boton = formulario.querySelector('button[type="submit"]');
  if (estaOcupado(boton)) return;
  ocupar(boton, true, 'Ingresando…');
  try {
    const sesion = await pedir('POST', 'sesion', { nombre, clave });
    guardarSesion(sesion);
    guardarAviso(`Hola, ${sesion.usuario.nombre}.`);
    location.href = destino;
  } catch (error) {
    mostrarErrores(formulario, error.campos, error.message);
    ocupar(boton, false, 'Ingresar');
    if (!Object.keys(error.campos).length) {
      // Usuario o contraseña incorrectos: se borra la clave y el foco vuelve a ese campo.
      formulario.clave.value = '';
      formulario.clave.focus();
    }
  }
});

pintarCuenta();
mostrarAvisoPendiente();
