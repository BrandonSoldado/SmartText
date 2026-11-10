require('dotenv').config();
const express = require('express');
const { createProxyMiddleware } = require('http-proxy-middleware');
const cors = require('cors');

const app = express();

app.use(cors());

// ==========================================
// CONFIGURACIÓN DE RUTAS (PROXIES)
// ==========================================

// 1. Redirigir tráfico GraphQL hacia Laravel forzando la ruta /graphql
app.use('/graphql', createProxyMiddleware({ 
    target: process.env.MS_GESTION_URL, 
    changeOrigin: true,
    xfwd: true,
    on: {
        proxyReq: (proxyReq, req, res) => {
            proxyReq.path = '/graphql'; // Fuerza a que siempre llegue a /graphql en Laravel
        }
    }
}));

// 2. Redirigir tráfico REST de Inventario hacia Spring Boot
app.use('/api/inventario', createProxyMiddleware({ 
    target: process.env.MS_INVENTARIO_URL, 
    changeOrigin: true,
    xfwd: true
}));

// 3. Redirigir tráfico REST de Pagos hacia NestJS
app.use('/api/pagos', createProxyMiddleware({ 
    target: process.env.MS_PAGOS_URL, 
    changeOrigin: true,
    xfwd: true
}));

// ==========================================
// INICIAR EL API GATEWAY
// ==========================================
const PORT = process.env.PORT || 4000;
app.listen(PORT, () => {
    console.log(`🚀 API Gateway ejecutándose en http://localhost:${PORT}`);
    console.log(`- GraphQL (Laravel) -> http://localhost:${PORT}/graphql`);
    console.log(`- Inventario (Spring Boot) -> http://localhost:${PORT}/api/inventario`);
    console.log(`- Pagos (NestJS) -> http://localhost:${PORT}/api/pagos`);
});