# mfe-inventario-vue

Microfrontend de Inventario y Producción (Vue 3 + Vite).

```bash
npm install
npm run dev      # http://localhost:5174
npm run build
```

Configura la URL del backend en `.env` (`VITE_INVENTARIO_URL`).
Apunta al API Gateway (puerto 4000). Si llamas directo a Spring Boot (:8080), habilita CORS allí.

El token se lee de `localStorage` (`smarttex_token`), el mismo que guarda el shell Angular.
Para probar en solitario, pégalo en la consola:
`localStorage.setItem('smarttex_token', '<token>')`
