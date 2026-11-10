# mfe-pagos-react

Microfrontend de Pagos y Facturación (React + TypeScript + Vite).
Consume el MS Pagos (NestJS): tarjetas, pagos y facturas.

```bash
npm install
npm run dev      # http://localhost:5175
npm run build
```

Configura la URL en `.env` (`VITE_PAGOS_URL`). Apunta al API Gateway (puerto 4000).
Si llamas directo a NestJS, habilita CORS allí (`app.enableCors()`).

El token se lee de `localStorage` (`smarttex_token`), el mismo que guarda el shell Angular.
Para probar en solitario, pégalo en la consola:
`localStorage.setItem('smarttex_token', '<token>')`

IMPORTANTE: revisa `src/config/recursos.ts`. Los campos de los formularios de
tarjeta y factura son una suposición: ajústalos a las columnas reales de tus entidades.
