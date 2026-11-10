<script setup>
import { ref, reactive, computed, onMounted } from 'vue'

const props = defineProps({
  recurso: { type: Object, required: true },
})

const items = ref([])
const cargando = ref(false)
const error = ref(null)
const busqueda = ref('')

const modal = ref(false)
const guardando = ref(false)
const errorForm = ref(null)
const form = reactive({})
const opciones = reactive({})

const mensajeError = (e) =>
  e.response?.data?.message || e.response?.data?.error || e.message || 'Ocurrió un error'

const normalizar = (data) => (Array.isArray(data) ? data : data?.content ?? [])

async function cargar() {
  cargando.value = true
  error.value = null
  try {
    items.value = normalizar(await props.recurso.service.listar())
  } catch (e) {
    error.value = mensajeError(e)
    items.value = []
  } finally {
    cargando.value = false
  }
}

const valorCelda = (row, col) => {
  const v = col.get ? col.get(row) : row[col.key]
  if (v === null || v === undefined || v === '') return '—'
  return col.format ? col.format(v) : v
}

const filtradas = computed(() => {
  const q = busqueda.value.trim().toLowerCase()
  if (!q) return items.value
  return items.value.filter((row) =>
    props.recurso.columns.some((c) => String(valorCelda(row, c)).toLowerCase().includes(q))
  )
})

// ---------- Formulario ----------
async function abrirModal() {
  errorForm.value = null
  Object.keys(form).forEach((k) => delete form[k])
  for (const f of props.recurso.fields) {
    form[f.key] =
      f.type === 'lineas' ? [{ idTela: '', cantidad: 1, precio: 0 }] : f.default ?? ''
  }
  modal.value = true

  // Carga las listas de los selects que dependen de otros servicios
  for (const f of props.recurso.fields) {
    if (!f.optionsFrom) continue
    try {
      opciones[f.key] = normalizar(await f.optionsFrom.service.listar())
    } catch {
      opciones[f.key] = []
    }
  }
}

async function guardar() {
  guardando.value = true
  errorForm.value = null
  try {
    await props.recurso.crear(form)
    modal.value = false
    await cargar()
  } catch (e) {
    errorForm.value = mensajeError(e)
  } finally {
    guardando.value = false
  }
}

async function eliminar(row) {
  if (!confirm(`¿Eliminar ${props.recurso.singular} #${row.id}?`)) return
  try {
    await props.recurso.service.eliminar(row.id)
    await cargar()
  } catch (e) {
    error.value = mensajeError(e)
  }
}

onMounted(cargar)
</script>

<template>
  <section class="panel">
    <header class="panel-head">
      <div>
        <h2>{{ recurso.label }}</h2>
        <p class="sub">{{ filtradas.length }} registro(s)</p>
      </div>
      <div class="acciones">
        <input v-model="busqueda" class="buscador" type="search" placeholder="Buscar..." />
        <button v-if="recurso.crear" class="btn-primary" @click="abrirModal">+ Nuevo</button>
      </div>
    </header>

    <div v-if="error" class="alert">
      {{ error }}
      <button class="btn-link" @click="cargar">Reintentar</button>
    </div>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th v-for="c in recurso.columns" :key="c.label">{{ c.label }}</th>
            <th class="col-acc"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td :colspan="recurso.columns.length + 1" class="vacio">Cargando...</td>
          </tr>
          <tr v-else-if="!filtradas.length">
            <td :colspan="recurso.columns.length + 1" class="vacio">Sin registros</td>
          </tr>
          <template v-else>
            <tr v-for="row in filtradas" :key="row.id">
              <td v-for="c in recurso.columns" :key="c.label">{{ valorCelda(row, c) }}</td>
              <td class="col-acc">
                <button class="btn-danger" @click="eliminar(row)">Eliminar</button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- ============ MODAL ============ -->
    <div v-if="modal" class="overlay" @click.self="modal = false">
      <form class="modal" @submit.prevent="guardar">
        <button type="button" class="cerrar" @click="modal = false">&times;</button>
        <span class="saludo">NUEVO REGISTRO</span>
        <h3>{{ recurso.label }}</h3>

        <div v-if="errorForm" class="alert">{{ errorForm }}</div>

        <div v-for="f in recurso.fields" :key="f.key" class="campo">
          <label>{{ f.label }}</label>

          <select v-if="f.type === 'select'" v-model="form[f.key]" :required="f.required">
            <option value="" disabled>Selecciona...</option>
            <template v-if="f.optionsFrom">
              <option v-for="o in opciones[f.key]" :key="o.id" :value="o.id">
                {{ f.optionsFrom.label(o) }}
              </option>
            </template>
            <template v-else>
              <option v-for="o in f.options" :key="o" :value="o">{{ o }}</option>
            </template>
          </select>

          <textarea v-else-if="f.type === 'textarea'" v-model="form[f.key]" rows="3" />

          <div v-else-if="f.type === 'lineas'" class="lineas">
            <div v-for="(l, i) in form[f.key]" :key="i" class="linea">
              <select v-model="l.idTela" required>
                <option value="" disabled>Tela...</option>
                <option v-for="o in opciones[f.key]" :key="o.id" :value="o.id">
                  {{ f.optionsFrom.label(o) }}
                </option>
              </select>
              <input v-model="l.cantidad" type="number" min="0.01" step="0.01" placeholder="Metros" required />
              <input v-model="l.precio" type="number" min="0" step="0.01" placeholder="Precio" required />
              <button
                type="button"
                class="btn-icon"
                :disabled="form[f.key].length === 1"
                @click="form[f.key].splice(i, 1)"
              >
                ✕
              </button>
            </div>
            <button
              type="button"
              class="btn-link"
              @click="form[f.key].push({ idTela: '', cantidad: 1, precio: 0 })"
            >
              + Agregar tela
            </button>
          </div>

          <input
            v-else
            v-model="form[f.key]"
            :type="f.type || 'text'"
            :required="f.required"
            :step="f.step"
            :min="f.min"
            :max="f.max"
            :placeholder="f.placeholder"
          />
        </div>

        <button type="submit" class="btn-submit" :disabled="guardando">
          {{ guardando ? 'GUARDANDO...' : 'GUARDAR' }}
        </button>
      </form>
    </div>
  </section>
</template>

<style scoped>
.panel { font-family: system-ui, -apple-system, sans-serif; color: #0f172a; }

.panel-head {
  display: flex; align-items: center; justify-content: space-between;
  gap: 16px; margin-bottom: 20px; flex-wrap: wrap;
}
.panel-head h2 { font-size: 24px; font-weight: 800; margin: 0; letter-spacing: -0.5px; }
.sub { margin: 4px 0 0; color: #64748b; font-size: 13px; }
.acciones { display: flex; gap: 12px; align-items: center; }

.buscador {
  padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px;
  font-size: 14px; outline: none; min-width: 220px;
}
.buscador:focus, .campo input:focus, .campo select:focus, .campo textarea:focus {
  border-color: #357e65; box-shadow: 0 0 0 3px rgba(53, 126, 101, 0.1);
}

.btn-primary {
  background: #357e65; color: #fff; border: none; padding: 10px 20px;
  border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer;
  transition: background-color 0.2s;
}
.btn-primary:hover { background: #2a634f; }

.btn-danger {
  background: transparent; color: #ef4444; border: 1px solid #fecaca;
  padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;
}
.btn-danger:hover { background: #fef2f2; }

.btn-link {
  background: none; border: none; color: #357e65; font-weight: 600;
  font-size: 13px; cursor: pointer; padding: 0;
}
.btn-link:hover { text-decoration: underline; }

.btn-icon {
  background: transparent; border: 1px solid #e2e8f0; border-radius: 6px;
  width: 36px; cursor: pointer; color: #64748b;
}
.btn-icon:disabled { opacity: 0.4; cursor: not-allowed; }

.alert {
  background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;
  padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 14px;
  display: flex; gap: 12px; align-items: center;
}

/* ----- Tabla ----- */
.tabla-wrap { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 10px; }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
thead { background: #f8fafc; }
th {
  text-align: left; padding: 12px 14px; color: #475569; font-size: 12px;
  font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
  border-bottom: 1px solid #e2e8f0; white-space: nowrap;
}
td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
tbody tr:hover { background: #f8fafc; }
tbody tr:last-child td { border-bottom: none; }
.col-acc { text-align: right; width: 1%; white-space: nowrap; }
.vacio { text-align: center; color: #64748b; padding: 40px; }

/* ----- Modal ----- */
.overlay {
  position: fixed; inset: 0; background: rgba(0, 0, 0, 0.4); z-index: 9999;
  display: flex; align-items: center; justify-content: center; padding: 20px;
}
.modal {
  background: #fff; border-radius: 12px; width: 520px; max-width: 95%;
  max-height: 88vh; overflow-y: auto; padding: 36px 36px 30px; position: relative;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: fade 0.25s ease-out;
}
@keyframes fade {
  from { opacity: 0; transform: translateY(16px); }
  to { opacity: 1; transform: translateY(0); }
}
.cerrar {
  position: absolute; top: 12px; right: 18px; background: transparent;
  border: none; font-size: 28px; color: #64748b; cursor: pointer;
}
.cerrar:hover { color: #0f172a; }
.saludo { color: #357e65; font-size: 12px; font-weight: 700; letter-spacing: 1px; }
.modal h3 { font-size: 24px; font-weight: 800; margin: 6px 0 22px; }

.campo { display: flex; flex-direction: column; margin-bottom: 16px; }
.campo label { font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
.campo input, .campo select, .campo textarea {
  padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;
  color: #0f172a; outline: none; width: 100%; font-family: inherit; box-sizing: border-box;
}

.lineas { display: flex; flex-direction: column; gap: 10px; }
.linea { display: grid; grid-template-columns: 1fr 90px 90px 36px; gap: 8px; }

.btn-submit {
  background: #357e65; color: #fff; border: none; padding: 12px; border-radius: 6px;
  font-size: 14px; font-weight: 700; cursor: pointer; width: 100%; margin-top: 6px;
}
.btn-submit:hover { background: #2a634f; }
.btn-submit:disabled { opacity: 0.7; cursor: not-allowed; }
</style>
