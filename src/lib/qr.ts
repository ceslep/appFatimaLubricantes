import qrcode from 'qrcode-generator';

// Genera un código QR como imagen SVG (data URL): nítida a cualquier tamaño
// y lista para pantalla o impresión.
export function generarQrDataUrl(texto: string, cellSize = 8, margin = 2): string {
  if (!texto) return '';
  const qr = qrcode(0, 'M');
  qr.addData(texto);
  qr.make();
  const svg = qr.createSvgTag({ cellSize, margin, scalable: true });
  return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
}
