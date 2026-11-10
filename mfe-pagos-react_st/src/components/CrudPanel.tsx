import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import type { Recurso } from '../config/recursos';

const mensajeError = (e: any): string => {
  const m = e?.response?.data?.message;
  return (Array.isArray(m) ? m.join(', ') : m) || e?.response?.data?.error || e?.message || 'Ocurrió un error';
};

const normalizar = (d: any): any[] => (Array.isArray(d) ? d : d?.content ?? []);

const formatear = (v: any): string => {
  if (v === null || v === undefined || v === '') return '—';
  if (typeof v === 'object') return v.id !== undefined ? `#${v.id}` : JSON.stringify(v);
  return String(v);
};

const titulo = (k: string) => k.replace(/_/g, ' ');

interface Props {
  recurso: Recurso;
}

export default function CrudPanel({ recurso }: Props) {
  const [items, setItems] = useState<any[]>([]);
  const [cargando, setCargando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busqueda, setBusqueda] = useState('');

  const [modal, setModal] = useState(false);
  const [guardando, setGuardando] = useState(false);
  const [errorForm, setErrorForm] = useState<string | null>(null);
  const [form, setForm] = useState<Record<string, any>>({});
  const [opciones, setOpciones] = useState<Record<string, any[]>>({});

  const cargar = useCallback(async () => {
    setCargando(true);
    setError(null);
    try {
      setItems(normalizar(await recurso.listar()));
    } catch (e) {
      setError(mensajeError(e));
      setItems([]);
    } finally {
      setCargando(false);
    }
  }, [recurso]);

  useEffect(() => {
    cargar();
  }, [cargar]);

  // Columnas: las del orden preferido que existan + cualquier otra que traiga el backend.
  const columnas = useMemo(() => {
    const keys = new Set<string>();
    items.slice(0, 20).forEach((r) => Object.keys(r).forEach((k) => keys.add(k)));
    const orden = recurso.orden ?? [];
    return [...orden.filter((k) => keys.has(k)), ...[...keys].filter((k) => !orden.includes(k))];
  }, [items, recurso]);

  const filtradas = useMemo(() => {
    const q = busqueda.trim().toLowerCase();
    if (!q) return items;
    return items.filter((row) => columnas.some((c) => formatear(row[c]).toLowerCase().includes(q)));
  }, [items, busqueda, columnas]);

  // ---------- Formulario ----------
  const abrirModal = async () => {
    setErrorForm(null);
    const inicial: Record<string, any> = {};
    recurso.fields.forEach((f) => (inicial[f.key] = f.default ?? ''));
    setForm({ ...inicial, ...(recurso.onOpen?.() ?? {}) });
    setModal(true);

    // Carga las listas de los selects que dependen de otros servicios
    const cargadas: Record<string, any[]> = {};
    for (const f of recurso.fields) {
      if (!f.optionsFrom) continue;
      try {
        cargadas[f.key] = normalizar(await f.optionsFrom.listar());
      } catch {
        cargadas[f.key] = [];
      }
    }
    setOpciones(cargadas);
  };

  const cambiar = (key: string, value: any) => setForm((f) => ({ ...f, [key]: value }));

  const guardar = async (e: FormEvent) => {
    e.preventDefault();
    setGuardando(true);
    setErrorForm(null);
    try {
      await recurso.crear(form);
      setModal(false);
      await cargar();
    } catch (err) {
      setErrorForm(mensajeError(err));
    } finally {
      setGuardando(false);
    }
  };

  const eliminar = async (row: any) => {
    if (!window.confirm(`¿Eliminar ${recurso.singular} #${row.id}?`)) return;
    try {
      await recurso.eliminar(row.id);
      await cargar();
    } catch (err) {
      setError(mensajeError(err));
    }
  };

  const celda = (col: string, valor: any) => {
    if (col === 'estado' && valor) {
      const ok = String(valor).toUpperCase() === 'COMPLETADO';
      return <span className={`pg-badge ${ok ? 'ok' : 'warn'}`}>{String(valor)}</span>;
    }
    return formatear(valor);
  };

  return (
    <section className="pg-panel">
      <header className="pg-head">
        <div>
          <h2>{recurso.label}</h2>
          <p className="pg-sub">{filtradas.length} registro(s)</p>
        </div>
        <div className="pg-acciones">
          <input
            className="pg-buscador"
            type="search"
            placeholder="Buscar..."
            value={busqueda}
            onChange={(e) => setBusqueda(e.target.value)}
          />
          <button className="pg-btn-primary" onClick={abrirModal}>
            {recurso.nuevoLabel}
          </button>
        </div>
      </header>

      {error && (
        <div className="pg-alert">
          {error}
          <button className="pg-btn-link" onClick={cargar}>
            Reintentar
          </button>
        </div>
      )}

      <div className="pg-tabla-wrap">
        <table>
          <thead>
            <tr>
              {columnas.map((c) => (
                <th key={c}>{titulo(c)}</th>
              ))}
              <th className="pg-col-acc"></th>
            </tr>
          </thead>
          <tbody>
            {cargando ? (
              <tr>
                <td colSpan={columnas.length + 1} className="pg-vacio">
                  Cargando...
                </td>
              </tr>
            ) : filtradas.length === 0 ? (
              <tr>
                <td colSpan={columnas.length + 1} className="pg-vacio">
                  Sin registros
                </td>
              </tr>
            ) : (
              filtradas.map((row) => (
                <tr key={row.id}>
                  {columnas.map((c) => (
                    <td key={c}>{celda(c, row[c])}</td>
                  ))}
                  <td className="pg-col-acc">
                    <button className="pg-btn-danger" onClick={() => eliminar(row)}>
                      Eliminar
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* ============ MODAL ============ */}
      {modal && (
        <div className="pg-overlay" onMouseDown={(e) => e.target === e.currentTarget && setModal(false)}>
          <form className="pg-modal" onSubmit={guardar}>
            <button type="button" className="pg-cerrar" onClick={() => setModal(false)}>
              &times;
            </button>
            <span className="pg-saludo">NUEVO REGISTRO</span>
            <h3>{recurso.label}</h3>

            {errorForm && <div className="pg-alert">{errorForm}</div>}

            {recurso.fields.map((f) => (
              <div key={f.key} className="pg-campo">
                <label>{f.label}</label>

                {f.type === 'select' ? (
                  <select
                    value={form[f.key] ?? ''}
                    required={f.required}
                    onChange={(e) => cambiar(f.key, e.target.value)}
                  >
                    {f.vacioLabel ? (
                      <option value="">{f.vacioLabel}</option>
                    ) : (
                      <option value="" disabled>
                        Selecciona...
                      </option>
                    )}
                    {f.optionsFrom
                      ? (opciones[f.key] ?? []).map((o) => (
                          <option key={o.id} value={o.id}>
                            {f.optionsFrom!.label(o)}
                          </option>
                        ))
                      : (f.options ?? []).map((o) => (
                          <option key={o} value={o}>
                            {o}
                          </option>
                        ))}
                  </select>
                ) : (
                  <input
                    type={f.type || 'text'}
                    value={form[f.key] ?? ''}
                    required={f.required}
                    readOnly={f.readOnly}
                    step={f.step}
                    min={f.min}
                    max={f.max}
                    placeholder={f.placeholder}
                    onChange={(e) => cambiar(f.key, e.target.value)}
                  />
                )}
              </div>
            ))}

            <button type="submit" className="pg-btn-submit" disabled={guardando}>
              {guardando ? 'GUARDANDO...' : 'GUARDAR'}
            </button>
          </form>
        </div>
      )}
    </section>
  );
}
