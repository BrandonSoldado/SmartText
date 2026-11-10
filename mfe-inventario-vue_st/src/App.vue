<script setup>
import { ref, computed } from 'vue'
import CrudPanel from '@/components/CrudPanel.vue'
import { resources } from '@/config/resources'

const activa = ref(resources[0].key)
const recurso = computed(() => resources.find((r) => r.key === activa.value))
</script>

<template>
  <div class="inventario">
    <header class="encabezado">
      <span class="saludo">PORTAL DE ALMACÉN</span>
      <h1>Inventario y Producción</h1>
    </header>

    <nav class="tabs">
      <button
        v-for="r in resources"
        :key="r.key"
        :class="['tab', { activa: activa === r.key }]"
        @click="activa = r.key"
      >
        {{ r.label }}
      </button>
    </nav>

    <!-- :key fuerza a recargar los datos al cambiar de pestaña -->
    <CrudPanel :key="recurso.key" :recurso="recurso" />
  </div>
</template>

<style>
body { margin: 0; background: #ffffff; }
</style>

<style scoped>
.inventario {
  font-family: system-ui, -apple-system, sans-serif;
  padding: 32px 24px;
  max-width: 1400px;
  margin: 0 auto;
}
.encabezado { margin-bottom: 20px; }
.saludo { color: #357e65; font-size: 12px; font-weight: 700; letter-spacing: 1px; }
h1 { margin: 6px 0 0; font-size: 32px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; }

.tabs {
  display: flex; gap: 6px; margin-bottom: 28px; overflow-x: auto;
  border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;
}
.tab {
  background: transparent; border: none; color: #475569; font-size: 15px;
  font-weight: 500; padding: 8px 16px; border-radius: 6px; cursor: pointer;
  white-space: nowrap; transition: all 0.2s ease;
}
.tab:hover { color: #357e65; }
.tab.activa { background: #357e65; color: #fff; }
</style>
