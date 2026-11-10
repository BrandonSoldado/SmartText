import { facturaService, getUsuarioActual, pagoService, tarjetaService, uuid } from '../services/pagosService';

export interface Field {
  key: string;
  label: string;
  type?: 'text' | 'number' | 'email' | 'select';
  required?: boolean;
  readOnly?: boolean;
  step?: string;
  min?: number;
  max?: number;
  default?: any;
  placeholder?: string;
  options?: string[]; // select con valores fijos
  optionsFrom?: { listar: () => Promise<any[]>; label: (o: any) => string }; // select con datos de otro servicio
  vacioLabel?: string; // si existe, el select acepta "sin valor" (opcional)
}

export interface Recurso {
  key: string;
  label: string;
  singular: string;
  nuevoLabel: string;
  listar: () => Promise<any[]>;
  eliminar: (id: number | string) => Promise<any>;
  orden?: string[]; // orden preferido de columnas (el resto se agrega solo)
  fields: Field[];
  onOpen?: () => Record<string, any>; // valores iniciales extra al abrir el formulario
  crear: (f: Record<string, any>) => Promise<any>;
}

const num = (v: any) => Number(v);

export const recursos: Recurso[] = [
  // ───────────── PAGOS ─────────────
  {
    key: 'pago',
    label: 'Pagos',
    singular: 'pago',
    nuevoLabel: '+ Nuevo pago',
    listar: pagoService.listar,
    eliminar: pagoService.eliminar,
    orden: ['id', 'id_venta', 'tarjeta', 'monto', 'moneda', 'estado', 'fecha', 'hora', 'idempotency_key'],
    fields: [
      { key: 'id_venta', label: 'ID de la venta', type: 'number', min: 1, required: true },
      {
        key: 'id_tarjeta',
        label: 'Tarjeta (opcional)',
        type: 'select',
        vacioLabel: 'Sin tarjeta',
        optionsFrom: { listar: tarjetaService.listar, label: (t) => `Tarjeta #${t.id}` },
      },
      { key: 'monto', label: 'Monto', type: 'number', step: '0.01', min: 0.01, required: true },
      { key: 'moneda', label: 'Moneda', type: 'select', options: ['BOB', 'USD'], default: 'BOB', required: true },
      // Se genera al abrir el formulario y se reutiliza si reintentas: evita cobros dobles.
      { key: 'idempotency_key', label: 'Clave de idempotencia', readOnly: true },
    ],
    onOpen: () => ({ idempotency_key: uuid() }),
    crear: (f) =>
      pagoService.procesar({
        id_venta: num(f.id_venta),
        id_tarjeta: f.id_tarjeta === '' ? null : num(f.id_tarjeta),
        monto: num(f.monto),
        moneda: f.moneda,
        idempotency_key: f.idempotency_key,
      }),
  },

  // ───────────── TARJETAS ─────────────
  // ⚠️ AJUSTA estos campos a las columnas reales de tu entidad Tarjeta.
  // Nunca envíes ni guardes el número completo de la tarjeta: con Stripe usa
  // el id del método de pago (pm_...) y los últimos 4 dígitos.
  {
    key: 'tarjeta',
    label: 'Tarjetas',
    singular: 'tarjeta',
    nuevoLabel: '+ Nueva tarjeta',
    listar: tarjetaService.listar,
    eliminar: tarjetaService.eliminar,
    orden: ['id', 'id_usuario', 'titular', 'marca', 'ultimos4', 'expiracion'],
    fields: [
      { key: 'titular', label: 'Titular', required: true },
      { key: 'marca', label: 'Marca', type: 'select', options: ['VISA', 'MASTERCARD', 'AMEX'], default: 'VISA', required: true },
      { key: 'ultimos4', label: 'Últimos 4 dígitos', placeholder: '4242', required: true },
      { key: 'expiracion', label: 'Expiración (MM/AA)', placeholder: '12/28', required: true },
    ],
    crear: (f) => {
      const usuario = getUsuarioActual();
      if (!usuario?.id) throw new Error('No hay un usuario con sesión iniciada.');
      return tarjetaService.crear(
        { titular: f.titular, marca: f.marca, ultimos4: f.ultimos4, expiracion: f.expiracion },
        num(usuario.id)
      );
    },
  },

  // ───────────── FACTURAS ─────────────
  // ⚠️ AJUSTA estos campos a las columnas reales de tu entidad Factura.
  {
    key: 'factura',
    label: 'Facturas',
    singular: 'factura',
    nuevoLabel: '+ Nueva factura',
    listar: facturaService.listar,
    eliminar: facturaService.eliminar,
    orden: ['id', 'id_venta', 'nit', 'razon_social', 'monto_total', 'fecha_emision', 'idempotency_key'],
    fields: [
      { key: 'id_venta', label: 'ID de la venta', type: 'number', min: 1, required: true },
      { key: 'nit', label: 'NIT / CI', required: true },
      { key: 'razon_social', label: 'Razón social', required: true },
      { key: 'monto_total', label: 'Monto total', type: 'number', step: '0.01', min: 0.01, required: true },
      { key: 'idempotency_key', label: 'Clave de idempotencia', readOnly: true },
    ],
    onOpen: () => ({ idempotency_key: uuid() }),
    crear: (f) =>
      facturaService.crear(
        num(f.id_venta),
        { nit: f.nit, razon_social: f.razon_social, monto_total: num(f.monto_total) },
        f.idempotency_key
      ),
  },
];
