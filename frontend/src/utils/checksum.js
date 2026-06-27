// Check-digit validation for registration identifiers — instant client-side
// feedback mirroring the authoritative backend (App\Services\ChecksumValidator).
// Unknown algorithms return true (never block).

function gstin(v) {
  v = (v || '').toUpperCase();
  if (v.length !== 15) return false;
  const cp = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  const mod = 36;
  let factor = 2;
  let sum = 0;
  for (let i = 13; i >= 0; i--) {
    const code = cp.indexOf(v[i]);
    if (code < 0) return false;
    let digit = factor * code;
    factor = factor === 2 ? 1 : 2;
    digit = Math.floor(digit / mod) + (digit % mod);
    sum += digit;
  }
  const check = (mod - (sum % mod)) % mod;
  return cp[check] === v[14];
}

function abn(v) {
  v = (v || '').replace(/\D/g, '');
  if (v.length !== 11) return false;
  const weights = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19];
  const digits = v.split('').map(Number);
  digits[0] -= 1;
  let sum = 0;
  for (let i = 0; i < 11; i++) sum += digits[i] * weights[i];
  return sum % 89 === 0;
}

function cnpj(v) {
  v = (v || '').replace(/\D/g, '');
  if (v.length !== 14 || /^(\d)\1{13}$/.test(v)) return false;
  const calc = (len) => {
    const weights = len === 12
      ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
      : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    let sum = 0;
    for (let i = 0; i < len; i++) sum += Number(v[i]) * weights[i];
    const r = sum % 11;
    return r < 2 ? 0 : 11 - r;
  };
  return Number(v[12]) === calc(12) && Number(v[13]) === calc(13);
}

const ALGORITHMS = { gstin, abn, cnpj };

export function isChecksumValid(algorithm, value) {
  if (!algorithm) return true;
  const fn = ALGORITHMS[algorithm];
  return fn ? fn(value) : true;
}
