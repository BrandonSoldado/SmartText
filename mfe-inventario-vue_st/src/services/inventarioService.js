// src/services/inventarioService.js
// Servicio para consumir el MS Inventario (Spring Boot) desde Vue.

import axios from 'axios'

// Configura la URL en .env  ->  VITE_INVENTARIO_URL=http://localhost:4000/api/inventario
// Idealmente apunta al API Gateway en vez de al microservicio directo.
const BASE_URL = import.meta.env.VITE_INVENTARIO_URL || 'http://localhost:4000/api/inventario'

const http = axios.create({
  baseURL: BASE_URL,
  headers: { 'Content-Type': 'application/json' },
  timeout: 15000,
})

// SSO: adjunta el JWT emitido por el MS Gestión (Laravel).
// El shell Angular lo guarda con la clave "smarttex_token".
http.interceptors.request.use((config) => {
  const token = localStorage.getItem('smarttex_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// Si el token expiró, vuelve al login único (Home del shell).
http.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('smarttex_token')
      localStorage.removeItem('smarttex_usuario')
      window.location.href = '/'
    }
    return Promise.reject(error)
  }
)

// Genera el UUID para idempotencia (reintentos sin duplicar).
const uuid = () =>
  crypto.randomUUID
    ? crypto.randomUUID()
    : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16)
      })

// Fábrica de CRUD: lo que el backend expone por recurso (listar, obtener, eliminar).
const crud = (ruta) => ({
  listar: () => http.get(ruta).then((r) => r.data),
  obtener: (id) => http.get(`${ruta}/${id}`).then((r) => r.data),
  eliminar: (id) => http.delete(`${ruta}/${id}`).then((r) => r.data),
})

// ───────────────────────── CATEGORÍA ─────────────────────────
// body: { nombre, descripcion }
export const categoriaService = {
  ...crud('/categoria'),
  crear: (categoria) => http.post('/categoria', categoria).then((r) => r.data),
}

// ───────────────────────── PROVEEDOR ─────────────────────────
// body: { razonSocial, identificacionTributaria, telefono, direccion, email }
export const proveedorService = {
  ...crud('/proveedor'),
  crear: (proveedor) => http.post('/proveedor', proveedor).then((r) => r.data),
}

// ───────────────────────── TELA ─────────────────────────
// tela: { nombre, tipoTela, color, gramaje, composicion, unidadMedida, precio, stock }
export const telaService = {
  ...crud('/tela'),
  crear: (tela, idCategoria) =>
    http.post('/tela', { tela, idCategoria }).then((r) => r.data),
}

// ───────────────────────── UNIDAD ROLLO ─────────────────────────
// unidadRollo: { cantidadInicial, cantidadActual, estadoDisponibilidad }
export const unidadRolloService = {
  ...crud('/unidad-rollo'),
  crear: (unidadRollo, idTela) =>
    http.post('/unidad-rollo', { unidadRollo, idTela }).then((r) => r.data),
}

// ───────────────────────── INSPECCIÓN ─────────────────────────
// inspeccion: { resultado, imagen }   (la fecha la pone el backend)
export const inspeccionService = {
  ...crud('/inspeccion'),
  crear: (inspeccion, idUnidadRollo) =>
    http.post('/inspeccion', { inspeccion, idUnidadRollo }).then((r) => r.data),
}

// ───────────────────────── DEFECTO ─────────────────────────
// defecto: { tipoDefecto, confianza }
export const defectoService = {
  ...crud('/defecto'),
  crear: (defecto, idInspeccion) =>
    http.post('/defecto', { defecto, idInspeccion }).then((r) => r.data),
}

// ───────────────────────── MERCADERÍA ─────────────────────────
// telas: [{ tela: { id }, cantidad, precio }]
export const mercaderiaService = {
  ...crud('/mercaderia'),
  crear: ({ idProveedor, montoTotal, nroFactura, telas }) =>
    http
      .post('/mercaderia', { idProveedor, montoTotal, nroFactura, telas })
      .then((r) => r.data),
}

// ───────────────────────── RESERVA (SAGA) ─────────────────────────
// rollos: [{ idUnidadRollo, cantidad }]
export const reservaService = {
  ...crud('/reserva'),

  // Paso 1 de la Saga. Envía Idempotency-Key para que un reintento no duplique.
  crear: (idVenta, rollos, idempotencyKey = uuid()) =>
    http
      .post(
        '/reserva',
        { idVenta, rollos },
        { headers: { 'Idempotency-Key': idempotencyKey } }
      )
      .then((r) => r.data),

  // Compensación de la Saga: libera la reserva de una venta si el pago falla.
  rollback: (idVenta) => http.delete(`/reserva/venta/${idVenta}`).then((r) => r.data),
}

export default {
  categoria: categoriaService,
  proveedor: proveedorService,
  tela: telaService,
  unidadRollo: unidadRolloService,
  inspeccion: inspeccionService,
  defecto: defectoService,
  mercaderia: mercaderiaService,
  reserva: reservaService,
}
