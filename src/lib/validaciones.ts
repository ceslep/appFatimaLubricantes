// Validaciones para Colombia: documento de identidad, placas y teléfonos.

export interface ResultadoValidacion {
  valido: boolean;
  mensaje: string;
  valorNormalizado: string;
}

const SOLO_REPETIDOS = /^(\d)\1+$/;

// ---------- Documento de identidad (cédula / NIT) ----------

// Limita lo que se puede escribir: máximo 10 dígitos (y un guion para el NIT).
export function limitarDocumento(valor: string, maxDigitos = 10): string {
  const limpio = (valor || '').replace(/[^\d-]/g, '');
  let digitos = 0;
  let guionUsado = false;
  let salida = '';
  for (const ch of limpio) {
    if (ch === '-') {
      if (guionUsado || salida === '' || digitos >= maxDigitos) continue;
      salida += '-';
      guionUsado = true;
    } else if (digitos < maxDigitos) {
      salida += ch;
      digitos++;
    }
  }
  return salida;
}

export function normalizarDocumento(valor: string): string {
  const limpio = (valor || '').trim().replace(/[.\s]/g, '');
  const partes = limpio.split('-').filter((p) => p !== '');
  if (partes.length > 1 && /^\d+$/.test(partes[0])) {
    const base = partes[0].replace(/\D/g, '');
    const dv = partes[1].replace(/\D/g, '').slice(0, 1);
    return base + (dv ? '-' + dv : '');
  }
  return limpio.replace(/\D/g, '');
}

// Dígito de verificación del NIT (algoritmo DIAN, módulo 11)
export function digitoVerificacionNIT(base: string): number {
  const pesos = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];
  const digitos = base.replace(/\D/g, '').split('').map(Number).reverse();
  let suma = 0;
  for (let i = 0; i < digitos.length; i++) suma += digitos[i] * (pesos[i] ?? 0);
  const residuo = suma % 11;
  return residuo < 2 ? residuo : 11 - residuo;
}

export function validarDocumento(valor: string, minDigitos = 10): ResultadoValidacion {
  const v = normalizarDocumento(valor);
  if (!v) return { valido: false, mensaje: 'Ingresa el número de identificación.', valorNormalizado: '' };

  if (v.includes('-')) {
    const [base, dv] = v.split('-');
    if (!/^\d{9,10}$/.test(base)) {
      return { valido: false, mensaje: 'El NIT debe tener 9 o 10 dígitos antes del guion.', valorNormalizado: v };
    }
    if (SOLO_REPETIDOS.test(base)) {
      return { valido: false, mensaje: 'Número de identificación inválido.', valorNormalizado: v };
    }
    const esperado = digitoVerificacionNIT(base);
    if (Number(dv) !== esperado) {
      return { valido: false, mensaje: 'Dígito de verificación incorrecto: debería ser ' + esperado + '.', valorNormalizado: v };
    }
    return { valido: true, mensaje: '', valorNormalizado: v };
  }

  if (!/^\d+$/.test(v)) {
    return { valido: false, mensaje: 'La identificación solo debe tener números.', valorNormalizado: v };
  }
  if (v.length < minDigitos || v.length > 10) {
    const rango = minDigitos >= 10 ? '10 dígitos' : minDigitos + ' a 10 dígitos';
    return { valido: false, mensaje: 'La cédula debe tener ' + rango + '.', valorNormalizado: v };
  }
  if (SOLO_REPETIDOS.test(v)) {
    return { valido: false, mensaje: 'Número de identificación inválido.', valorNormalizado: v };
  }
  return { valido: true, mensaje: '', valorNormalizado: v };
}

// ---------- Placas de vehículos ----------

// Placa Colombia: carro AAA000 o moto AAA00A, con guion o espacio opcional.
export const REGEX_PLACA_CO = /^[A-Za-z]{3}[-\s]?[0-9]{3}$|^[A-Za-z]{3}[-\s]?[0-9]{2}[A-Za-z]$/;

export function normalizarPlaca(valor: string): string {
  return (valor || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);
}

// Limita el campo: letras, números y un separador (guion o espacio).
export function limitarPlaca(valor: string): string {
  return (valor || '').toUpperCase().replace(/[^A-Z0-9\-\s]/g, '').slice(0, 7);
}

export function validarPlaca(valor: string, obligatoria = false): ResultadoValidacion {
  const bruto = (valor || '').trim();
  const normalizada = normalizarPlaca(bruto);
  if (!normalizada) {
    return obligatoria
      ? { valido: false, mensaje: 'Ingresa la placa del vehículo.', valorNormalizado: '' }
      : { valido: true, mensaje: '', valorNormalizado: '' };
  }
  if (!REGEX_PLACA_CO.test(bruto.toUpperCase())) {
    return { valido: false, mensaje: 'Placa inválida. Carro: ABC123 · Moto: ABC12D.', valorNormalizado: normalizada };
  }
  return { valido: true, mensaje: '', valorNormalizado: normalizada };
}

// ---------- Teléfonos ----------

// Devuelve solo los dígitos, quitando el prefijo país (+57 / 57 / 00).
// Celular Colombia: 10 dígitos que empiezan por 3, con prefijo +57 opcional.
export const REGEX_CELULAR_CO = /^(?:\+?57)?\s?3\d{9}$/;

// Extrae los 10 dígitos nacionales (sin prefijo país) para guardarlos.
export function normalizarTelefonoCO(valor: string): string {
  const v = (valor || '').trim();
  const m = v.match(/^(?:\+?57)?\s?(3\d{9})$/);
  if (m) return m[1];
  let d = v.replace(/\D/g, '');
  if (d.length > 10 && d.startsWith('57')) d = d.slice(2);
  return d.slice(0, 10);
}

export function validarTelefonoCO(valor: string): ResultadoValidacion {
  const v = (valor || '').trim();
  if (!v) return { valido: false, mensaje: 'Ingresa el número de teléfono.', valorNormalizado: '' };
  if (!REGEX_CELULAR_CO.test(v)) {
    return { valido: false, mensaje: 'El celular debe tener 10 dígitos y empezar por 3 (ej. 3001234567).', valorNormalizado: normalizarTelefonoCO(v) };
  }
  return { valido: true, mensaje: '', valorNormalizado: normalizarTelefonoCO(v) };
}

export function formatearTelefonoCO(valor: string): string {
  const v = normalizarTelefonoCO(valor);
  if (v.length !== 10) return valor;
  return v.slice(0, 3) + ' ' + v.slice(3, 6) + ' ' + v.slice(6);
}

// ---------- Correo ----------

export function validarCorreo(valor: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test((valor || '').trim());
}

// ---------- Nombres de persona ----------

// 2 a 4 palabras, admite partículas (de la, de los, de las, del, de, la).
export const REGEX_NOMBRES = /^(?:[A-Za-zÁÉÍÓÚáéíóúÑñÜü]+(?:\s+(?:de\s+la|de\s+los|de\s+las|del|de|la)?\s*[A-Za-zÁÉÍÓÚáéíóúÑñÜü]+){1,3})$/;

export function normalizarNombres(valor: string): string {
  return (valor || '').trim().replace(/\s+/g, ' ');
}

export function validarNombres(valor: string): ResultadoValidacion {
  const v = normalizarNombres(valor);
  if (!v) return { valido: false, mensaje: 'Ingresa el nombre completo.', valorNormalizado: '' };
  if (!REGEX_NOMBRES.test(v)) {
    return { valido: false, mensaje: 'Escribe nombre y apellido, solo letras (ej. Juan Pérez).', valorNormalizado: v };
  }
  if (v.length < 10) {
    return { valido: false, mensaje: 'El nombre debe tener al menos 10 caracteres.', valorNormalizado: v };
  }
  return { valido: true, mensaje: '', valorNormalizado: v };
}

// Limita lo que se escribe: solo letras (con tildes/ñ) y espacios.
export function limitarNombres(valor: string): string {
  return (valor || '')
    .replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñÜü\s]/g, '')
    .replace(/\s{2,}/g, ' ')
    .replace(/^\s+/, '')
    .slice(0, 60);
}

// ---------- Estado en vivo (mientras el usuario escribe) ----------

export type EstadoCampo = 'vacio' | 'ok' | 'parcial' | 'error';

export interface EstadoValidacion {
  estado: EstadoCampo;
  mensaje: string;
}

function faltan(n: number): string {
  return 'Faltan ' + n + ' dígito' + (n === 1 ? '' : 's') + '.';
}

export function estadoDocumento(valor: string, minDigitos = 10): EstadoValidacion {
  const v = normalizarDocumento(valor);
  if (!v) return { estado: 'vacio', mensaje: '' };
  if (v.includes('-')) {
    const r = validarDocumento(v, minDigitos);
    return r.valido ? { estado: 'ok', mensaje: 'NIT válido.' } : { estado: 'error', mensaje: r.mensaje };
  }
  if (!/^\d+$/.test(v)) return { estado: 'error', mensaje: 'La identificación solo debe tener números.' };
  if (v.length < minDigitos) return { estado: 'parcial', mensaje: faltan(minDigitos - v.length) };
  if (v.length > 10) return { estado: 'error', mensaje: 'La cédula no debe tener más de 10 dígitos.' };
  if (SOLO_REPETIDOS.test(v)) return { estado: 'error', mensaje: 'Número de identificación inválido.' };
  return { estado: 'ok', mensaje: 'Cédula válida.' };
}

// Separa el prefijo país (+57 / 57) del número nacional mientras se escribe.
function parseTelefono(valor: string): { prefijoParcial: boolean; nacional: string } {
  const v = (valor || '').trim();
  let i = 0;
  let prefijoParcial = false;
  if (v[i] === '+') {
    i++;
    if (v.startsWith('57', i)) {
      i += 2;
    } else {
      // Aún no completa el +57
      prefijoParcial = true;
      if (v[i] === '5') i++;
    }
  } else if (v.startsWith('57')) {
    i = 2;
  } else if (v[0] === '5') {
    prefijoParcial = true;
    i = 1;
  }
  if (v[i] === ' ') i++;
  return { prefijoParcial, nacional: v.slice(i).replace(/\D/g, '') };
}

export function estadoTelefono(valor: string): EstadoValidacion {
  const v = (valor || '').trim();
  if (!v) return { estado: 'vacio', mensaje: '' };

  const { prefijoParcial, nacional } = parseTelefono(v);

  if (prefijoParcial) {
    return { estado: 'parcial', mensaje: 'Para el prefijo escribe +57 y luego el número.' };
  }
  if (nacional === '') {
    return { estado: 'parcial', mensaje: 'Escribe el número después del +57.' };
  }
  if (nacional[0] !== '3') {
    return { estado: 'error', mensaje: 'El celular debe empezar por 3, o usar el prefijo +57 (ej. 3001234567).' };
  }
  if (nacional.length < 10) return { estado: 'parcial', mensaje: faltan(10 - nacional.length) };
  if (nacional.length > 10) return { estado: 'error', mensaje: 'El celular no debe tener más de 10 dígitos.' };
  return { estado: 'ok', mensaje: 'Celular válido.' };
}

export function estadoPlaca(valor: string): EstadoValidacion {
  const bruto = (valor || '').trim();
  if (!bruto) return { estado: 'vacio', mensaje: '' };
  if (REGEX_PLACA_CO.test(bruto.toUpperCase())) return { estado: 'ok', mensaje: 'Placa válida.' };
  const normalizada = normalizarPlaca(bruto);
  if (normalizada.length < 6) return { estado: 'parcial', mensaje: 'Completa la placa (ej. ABC123 o ABC12D).' };
  return { estado: 'error', mensaje: 'Placa inválida. Carro: ABC123 · Moto: ABC12D.' };
}

export function estadoNombres(valor: string): EstadoValidacion {
  const v = normalizarNombres(valor);
  if (!v) return { estado: 'vacio', mensaje: '' };
  if (!REGEX_NOMBRES.test(v)) {
    if (!v.includes(' ')) return { estado: 'parcial', mensaje: 'Escribe nombre y apellido.' };
    return { estado: 'error', mensaje: 'Nombre inválido: de 2 a 4 palabras, solo letras (ej. Juan Pérez).' };
  }
  if (v.length < 10) {
    const faltan = 10 - v.length;
    return { estado: 'parcial', mensaje: 'Faltan ' + faltan + ' caracter' + (faltan === 1 ? '' : 'es') + ' (mínimo 10).' };
  }
  return { estado: 'ok', mensaje: 'Nombre válido.' };
}

export function estadoCorreo(valor: string): EstadoValidacion {
  const v = (valor || '').trim();
  if (!v) return { estado: 'vacio', mensaje: '' };
  return validarCorreo(v)
    ? { estado: 'ok', mensaje: 'Correo válido.' }
    : { estado: 'error', mensaje: 'El correo electrónico no es válido.' };
}
