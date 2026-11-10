import { useState } from 'react';
import CrudPanel from './components/CrudPanel';
import { recursos } from './config/recursos';

export default function App() {
  const [activa, setActiva] = useState(recursos[0].key);
  const recurso = recursos.find((r) => r.key === activa)!;

  return (
    <div className="pg-app">
      <header className="pg-encabezado">
        <span className="pg-saludo">PAGOS Y FACTURACIÓN</span>
        <h1>Pagos, tarjetas y facturas</h1>
      </header>

      <nav className="pg-tabs">
        {recursos.map((r) => (
          <button
            key={r.key}
            className={`pg-tab ${activa === r.key ? 'activa' : ''}`}
            onClick={() => setActiva(r.key)}
          >
            {r.label}
          </button>
        ))}
      </nav>

      {/* key: recarga los datos al cambiar de pestaña */}
      <CrudPanel key={recurso.key} recurso={recurso} />
    </div>
  );
}
