// src/services/pagosService.ts
// Servicio para consumir el MS Pagos y Facturación (NestJS) desde React.
// El controlador es @Controller('api/pagos') y está protegido con JwtAuthGuard.

import axios from 'axios';

// .env -> VITE_PAGOS_URL=http://localhost:4000/api/pagos  (API Gateway)
const BASE_URL = import.meta.env.VITE_PAGOS_URL || 'http://localhost:4000/api/pagos';

const http = axios.create({
  baseURL: BASE_URL,
  headers: { 'Content-Type': 'application/json' },
  timeout: 15000,
});

// SSO: adjunta el JWT emitido por el MS Gestión (Laravel).
// El shell Angular lo guarda con la clave "smarttex_token".
http.interceptors.request.use((config) => {
  const token = localStorage.getItem('smarttex_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// Token vencido o inválido -> vuelve al login único (Home del shell).
http.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('smarttex_token');
      localStorage.removeItem('smarttex_usuario');
      window.location.href = '/';
    }
    return Promise.reject(error);
  }
);

// UUID para idempotencia (reintentos sin duplicar cobros ni facturas).
export const uuid = (): string =>
  typeof crypto !== 'undefined' && crypto.randomUUID
    ? crypto.randomUUID()
    : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
      });

// Usuario que guardó el shell al iniciar sesión.
export const getUsuarioActual = (): { id?: string | number; email?: string } | null => {
  try {
    const raw = localStorage.getItem('smarttex_usuario');
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
};

// CRUD común: listar, obtener por id, eliminar.
const crud = (ruta: string) => ({
  listar: (): Promise<any[]> => http.get(ruta).then((r) => r.data),
  obtener: (id: number | string): Promise<any> => http.get(`${ruta}/${id}`).then((r) => r.data),
  eliminar: (id: number | string): Promise<boolean> => http.delete(`${ruta}/${id}`).then((r) => r.data),
});

// ───────────────────────── TARJETA ─────────────────────────
// POST /tarjeta  body: { id_usuario, ...datos }
export const tarjetaService = {
  ...crud('/tarjeta'),
  crear: (datos: Record<string, any>, id_usuario: number | string) =>
    http.post('/tarjeta', { id_usuario, ...datos }).then((r) => r.data),
};

// ───────────────────────── PAGO ─────────────────────────
// POST /procesar  body: { id_venta, id_tarjeta?, monto, moneda, idempotency_key }
// Con la misma idempotency_key devuelve el pago existente (no duplica el cobro).
export const pagoService = {
  ...crud('/pago'),
  procesar: (p: {
    id_venta: number;
    monto: number;
    moneda?: string;
    id_tarjeta?: number | null;
    idempotency_key?: string;
  }) =>
    http
      .post('/procesar', {
        id_venta: p.id_venta,
        id_tarjeta: p.id_tarjeta ?? null,
        monto: p.monto,
        moneda: p.moneda ?? 'BOB',
        idempotency_key: p.idempotency_key ?? uuid(),
      })
      .then((r) => r.data),
};

// ───────────────────────── FACTURA ─────────────────────────
// POST /factura  body: { id_venta, idempotency_key, ...datos }
export const facturaService = {
  ...crud('/factura'),
  crear: (id_venta: number, datos: Record<string, any>, idempotency_key: string = uuid()) =>
    http.post('/factura', { id_venta, idempotency_key, ...datos }).then((r) => r.data),
};

export default { tarjeta: tarjetaService, pago: pagoService, factura: facturaService };
