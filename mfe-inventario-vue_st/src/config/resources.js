import {
  categoriaService,
  proveedorService,
  telaService,
  unidadRolloService,
  inspeccionService,
  defectoService,
  mercaderiaService,
  reservaService,
} from '@/services/inventarioService'

const dinero = (v) => Number(v).toFixed(2)

// Cada recurso define: columnas de la tabla, campos del formulario y cómo crear.
// field.type: text | number | textarea | select | lineas
// select con datos de otro servicio -> optionsFrom: { service, label }
// select con valores fijos -> options: [...]
export const resources = [
  {
    key: 'categoria',
    label: 'Categorías',
    singular: 'categoría',
    service: categoriaService,
    columns: [
      { label: 'ID', key: 'id' },
      { label: 'Nombre', key: 'nombre' },
      { label: 'Descripción', key: 'descripcion' },
    ],
    fields: [
      { key: 'nombre', label: 'Nombre', required: true },
      { key: 'descripcion', label: 'Descripción', type: 'textarea' },
    ],
    crear: (f) => categoriaService.crear({ nombre: f.nombre, descripcion: f.descripcion }),
  },

  {
    key: 'tela',
    label: 'Telas',
    singular: 'tela',
    service: telaService,
    columns: [
      { label: 'ID', key: 'id' },
      { label: 'Nombre', key: 'nombre' },
      { label: 'Tipo', key: 'tipoTela' },
      { label: 'Color', key: 'color' },
      { label: 'Gramaje', key: 'gramaje' },
      { label: 'Unidad', key: 'unidadMedida' },
      { label: 'Precio', key: 'precio', format: dinero },
      { label: 'Stock', key: 'stock' },
      { label: 'Categoría', get: (r) => r.categoria?.nombre },
    ],
    fields: [
      {
        key: 'idCategoria',
        label: 'Categoría',
        type: 'select',
        required: true,
        optionsFrom: { service: categoriaService, label: (c) => c.nombre },
      },
      { key: 'nombre', label: 'Nombre', required: true },
      { key: 'tipoTela', label: 'Tipo de tela', required: true },
      { key: 'color', label: 'Color' },
      { key: 'gramaje', label: 'Gramaje (g/m²)', type: 'number', step: '0.01', min: 0 },
      { key: 'composicion', label: 'Composición' },
      { key: 'unidadMedida', label: 'Unidad de medida', default: 'm', required: true },
      { key: 'precio', label: 'Precio por unidad', type: 'number', step: '0.01', min: 0, required: true },
      { key: 'stock', label: 'Stock inicial', type: 'number', step: '0.01', min: 0, default: 0 },
    ],
    crear: (f) =>
      telaService.crear(
        {
          nombre: f.nombre,
          tipoTela: f.tipoTela,
          color: f.color,
          gramaje: f.gramaje === '' ? null : Number(f.gramaje),
          composicion: f.composicion,
          unidadMedida: f.unidadMedida,
          precio: Number(f.precio),
          stock: Number(f.stock) || 0,
        },
        Number(f.idCategoria)
      ),
  },

  {
    key: 'proveedor',
    label: 'Proveedores',
    singular: 'proveedor',
    service: proveedorService,
    columns: [
      { label: 'ID', key: 'id' },
      { label: 'Razón social', key: 'razonSocial' },
      { label: 'NIT / ID tributaria', key: 'identificacionTributaria' },
      { label: 'Teléfono', key: 'telefono' },
      { label: 'Email', key: 'email' },
      { label: 'Dirección', key: 'direccion' },
    ],
    fields: [
      { key: 'razonSocial', label: 'Razón social', required: true },
      { key: 'identificacionTributaria', label: 'NIT / Identificación tributaria', required: true },
      { key: 'telefono', label: 'Teléfono' },
      { key: 'email', label: 'Email', type: 'email' },
      { key: 'direccion', label: 'Dirección' },
    ],
    crear: (f) =>
      proveedorService.crear({
        razonSocial: f.razonSocial,
        identificacionTributaria: f.identificacionTributaria,
        telefono: f.telefono,
        direccion: f.direccion,
        email: f.email,
      }),
  },

  {
    key: 'unidadRollo',
    label: 'Rollos',
    singular: 'rollo',
    service: unidadRolloService,
    columns: [
      { label: 'ID', key: 'id' },
      { label: 'Tela', get: (r) => r.tela?.nombre },
      { label: 'Cantidad inicial', key: 'cantidadInicial' },
      { label: 'Cantidad actual', key: 'cantidadActual' },
      { label: 'Estado', key: 'estadoDisponibilidad' },
    ],
    fields: [
      {
        key: 'idTela',
        label: 'Tela',
        type: 'select',
        required: true,
        optionsFrom: { service: telaService, label: (t) => `${t.nombre} · ${t.color ?? ''}` },
      },
      { key: 'cantidadInicial', label: 'Metraje del rollo', type: 'number', step: '0.01', min: 0.01, required: true },
      // Ajusta estos valores a los que acepte tu backend
      {
        key: 'estadoDisponibilidad',
        label: 'Estado',
        type: 'select',
        required: true,
        default: 'DISPONIBLE',
        options: ['DISPONIBLE', 'RESERVADO', 'AGOTADO'],
      },
    ],
    crear: (f) =>
      unidadRolloService.crear(
        {
          cantidadInicial: Number(f.cantidadInicial),
          cantidadActual: Number(f.cantidadInicial),
          estadoDisponibilidad: f.estadoDisponibilidad,
        },
        Number(f.idTela)
      ),
  },

  {
    key: 'inspeccion',
    label: 'Inspecciones',
    singular: 'inspección',
    service: inspeccionService,
    columns: [
      { label: 'ID', key: 'id' },
      { label: 'Rollo', get: (r) => r.unidadRollo?.id },
      { label: 'Resultado', key: 'resultado' },
      { label: 'Fecha', key: 'fecha' },
    ],
    fields: [
      {
        key: 'idUnidadRollo',
        label: 'Rollo inspeccionado',
        type: 'select',
        required: true,
        optionsFrom: {
          service: unidadRolloService,
          label: (r) => `Rollo #${r.id} · ${r.tela?.nombre ?? ''} · ${r.cantidadActual} m`,
        },
      },
      { key: 'resultado', label: 'Resultado', required: true, placeholder: 'APROBADO / RECHAZADO' },
      { key: 'imagen', label: 'Imagen (URL)' },
    ],
    crear: (f) =>
      inspeccionService.crear(
        { resultado: f.resultado, imagen: f.imagen || null },
        Number(f.idUnidadRollo)
      ),
  },

  {
    key: 'defecto',
    label: 'Defectos',
    singular: 'defecto',
    service: defectoService,
    columns: [
      { label: 'ID', key: 'id' },
      { label: 'Inspección', get: (r) => r.inspeccion?.id },
      { label: 'Tipo de defecto', key: 'tipoDefecto' },
      { label: 'Confianza', key: 'confianza' },
    ],
    fields: [
      {
        key: 'idInspeccion',
        label: 'Inspección',
        type: 'select',
        required: true,
        optionsFrom: { service: inspeccionService, label: (i) => `Inspección #${i.id} · ${i.resultado ?? ''}` },
      },
      { key: 'tipoDefecto', label: 'Tipo de defecto', required: true, placeholder: 'Mancha, desgarro...' },
      { key: 'confianza', label: 'Confianza (0 a 1)', type: 'number', step: '0.01', min: 0, max: 1, required: true },
    ],
    crear: (f) =>
      defectoService.crear(
        { tipoDefecto: f.tipoDefecto, confianza: Number(f.confianza) },
        Number(f.idInspeccion)
      ),
  },

  {
    key: 'mercaderia',
    label: 'Mercadería',
    singular: 'ingreso de mercadería',
    service: mercaderiaService,
    columns: [
      { label: 'ID', key: 'id' },
      { label: 'Proveedor', get: (r) => r.proveedor?.razonSocial },
      { label: 'Nro. factura', key: 'nroFactura' },
      { label: 'Monto total', key: 'montoTotal', format: dinero },
    ],
    fields: [
      {
        key: 'idProveedor',
        label: 'Proveedor',
        type: 'select',
        required: true,
        optionsFrom: { service: proveedorService, label: (p) => p.razonSocial },
      },
      { key: 'nroFactura', label: 'Nro. de factura', required: true },
      {
        key: 'telas',
        label: 'Telas recibidas',
        type: 'lineas',
        optionsFrom: { service: telaService, label: (t) => `${t.nombre} · ${t.color ?? ''}` },
      },
    ],
    crear: (f) => {
      const telas = f.telas.map((l) => ({
        tela: { id: Number(l.idTela) },
        cantidad: Number(l.cantidad),
        precio: Number(l.precio),
      }))
      // El monto total se calcula solo
      const montoTotal = telas.reduce((s, t) => s + t.cantidad * t.precio, 0)
      return mercaderiaService.crear({
        idProveedor: Number(f.idProveedor),
        nroFactura: f.nroFactura,
        montoTotal: Number(montoTotal.toFixed(2)),
        telas,
      })
    },
  },

  {
    // Las reservas las crea la Saga desde el MS Gestión: aquí solo se consultan.
    key: 'reserva',
    label: 'Reservas',
    singular: 'reserva',
    service: reservaService,
    columns: [
      { label: 'ID', key: 'id' },
      { label: 'ID Venta', key: 'idVenta' },
      { label: 'Estado', key: 'estado' },
    ],
    fields: [],
    crear: null, // sin botón "Nuevo"
  },
]
