// Achica la foto en el navegador antes de enviarla.
// La columna foto_blob es un BLOB (máximo 65.535 bytes), así que la imagen se
// redimensiona y se convierte a JPEG hasta que pese menos de ~60 KB.
// El servidor vuelve a validarla igual: nunca se confía solo en el cliente.

const LIMITE_BYTES = 60000;
const LADO_INICIAL = 640;
const TIPOS = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

function bytesDeDataUrl(url) {
  const base64 = url.slice(url.indexOf(',') + 1);
  const relleno = base64.endsWith('==') ? 2 : base64.endsWith('=') ? 1 : 0;
  return Math.floor((base64.length * 3) / 4) - relleno;
}

async function abrir(archivo) {
  if ('createImageBitmap' in window) {
    try {
      return await createImageBitmap(archivo, { imageOrientation: 'from-image' });
    } catch {
      /* se intenta con <img> */
    }
  }
  return new Promise((resolver, rechazar) => {
    const url = URL.createObjectURL(archivo);
    const img = new Image();
    img.onload = () => { URL.revokeObjectURL(url); resolver(img); };
    img.onerror = () => { URL.revokeObjectURL(url); rechazar(new Error('No se pudo abrir la imagen. Probá con otro archivo.')); };
    img.src = url;
  });
}

/** @returns {Promise<string>} data URL JPEG lista para mandar a la API */
export async function comprimirImagen(archivo) {
  if (!TIPOS.includes(archivo.type)) {
    throw new Error('Elegí una imagen JPG, PNG, GIF o WebP.');
  }
  if (archivo.size > 15 * 1024 * 1024) {
    throw new Error('La imagen pesa más de 15 MB. Elegí una más liviana.');
  }

  const fuente = await abrir(archivo);
  const ancho = fuente.naturalWidth || fuente.width;
  const alto = fuente.naturalHeight || fuente.height;
  let lado = LADO_INICIAL;

  for (let intento = 0; intento < 6; intento += 1) {
    const escala = Math.min(1, lado / Math.max(ancho, alto));
    const lienzo = document.createElement('canvas');
    lienzo.width = Math.max(1, Math.round(ancho * escala));
    lienzo.height = Math.max(1, Math.round(alto * escala));
    const ctx = lienzo.getContext('2d');
    ctx.fillStyle = '#ffffff'; // fondo para PNG con transparencia
    ctx.fillRect(0, 0, lienzo.width, lienzo.height);
    ctx.drawImage(fuente, 0, 0, lienzo.width, lienzo.height);

    for (const calidad of [0.85, 0.75, 0.65, 0.55]) {
      const url = lienzo.toDataURL('image/jpeg', calidad);
      if (bytesDeDataUrl(url) <= LIMITE_BYTES) return url;
    }
    lado = Math.round(lado * 0.8);
  }
  throw new Error('No se pudo achicar la imagen lo suficiente. Probá con otra.');
}
