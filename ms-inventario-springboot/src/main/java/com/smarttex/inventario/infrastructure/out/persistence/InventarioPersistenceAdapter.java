package com.smarttex.inventario.infrastructure.out.persistence;

import com.smarttex.inventario.domain.model.*;
import com.smarttex.inventario.domain.ports.out.InventarioRepositoryPort;
import lombok.RequiredArgsConstructor;
import org.springframework.stereotype.Component;

import java.util.List;
import java.util.Optional;

@Component
@RequiredArgsConstructor
public class InventarioPersistenceAdapter implements InventarioRepositoryPort {

    // Inyección de los 10 repositorios JPA
    private final CategoriaJpaRepository categoriaRepo;
    private final ProveedorJpaRepository proveedorRepo;
    private final TelaJpaRepository telaRepo;
    private final UnidadesRollosJpaRepository unidadesRollosRepo;
    private final InspeccionCalidadJpaRepository inspeccionRepo;
    private final DefectoDetectadoJpaRepository defectoRepo;
    private final MercaderiaJpaRepository mercaderiaRepo;
    private final MercaderiaTelaJpaRepository mercaderiaTelaRepo;
    private final ReservaJpaRepository reservaRepo;
    private final ReservaUnidadesRollosJpaRepository reservaUnidadesRollosRepo;

    // --- MÉTODOS DE CATEGORÍA ---
    @Override
    public Categoria guardarCategoria(Categoria c) { return categoriaRepo.save(c); }
    @Override
    public List<Categoria> listarCategorias() { return categoriaRepo.findAll(); }
    @Override
    public void eliminarCategoria(Long id) { categoriaRepo.deleteById(id); }
    @Override
    public Optional<Categoria> buscarCategoriaPorId(Long id) { return categoriaRepo.findById(id); }

    // --- MÉTODOS DE PROVEEDOR ---
    @Override
    public Proveedor guardarProveedor(Proveedor p) { return proveedorRepo.save(p); }
    @Override
    public List<Proveedor> listarProveedores() { return proveedorRepo.findAll(); }
    @Override
    public void eliminarProveedor(Long id) { proveedorRepo.deleteById(id); }
    @Override
    public Optional<Proveedor> buscarProveedorPorId(Long id) { return proveedorRepo.findById(id); }

    // --- MÉTODOS DE TELA ---
    @Override
    public Tela guardarTela(Tela t) { return telaRepo.save(t); }
    @Override
    public List<Tela> listarTelas() { return telaRepo.findAll(); }
    @Override
    public void eliminarTela(Long id) { telaRepo.deleteById(id); }
    @Override
    public Optional<Tela> buscarTelaPorId(Long id) { return telaRepo.findById(id); }

    // --- MÉTODOS DE UNIDADES ROLLOS ---
    @Override
    public UnidadesRollos guardarUnidadRollo(UnidadesRollos u) { return unidadesRollosRepo.save(u); }
    @Override
    public List<UnidadesRollos> listarUnidadesRollos() { return unidadesRollosRepo.findAll(); }
    @Override
    public void eliminarUnidadRollo(Long id) { unidadesRollosRepo.deleteById(id); }
    @Override
    public Optional<UnidadesRollos> buscarUnidadRolloPorId(Long id) { return unidadesRollosRepo.findById(id); }

    // --- MÉTODOS DE INSPECCIÓN CALIDAD ---
    @Override
    public InspeccionCalidad guardarInspeccion(InspeccionCalidad i) { return inspeccionRepo.save(i); }
    @Override
    public List<InspeccionCalidad> listarInspecciones() { return inspeccionRepo.findAll(); }
    @Override
    public void eliminarInspeccion(Long id) { inspeccionRepo.deleteById(id); }
    @Override
    public Optional<InspeccionCalidad> buscarInspeccionPorId(Long id) { return inspeccionRepo.findById(id); }

    // --- MÉTODOS DE DEFECTO DETECTADO ---
    @Override
    public DefectoDetectado guardarDefecto(DefectoDetectado d) { return defectoRepo.save(d); }
    @Override
    public List<DefectoDetectado> listarDefectos() { return defectoRepo.findAll(); }
    @Override
    public void eliminarDefecto(Long id) { defectoRepo.deleteById(id); }
    @Override 
    public Optional<DefectoDetectado> buscarDefectoPorId(Long id) { return defectoRepo.findById(id); }

    // --- MÉTODOS COMPUESTOS (MERCADERÍA) ---
    @Override
    public Mercaderia guardarMercaderia(Mercaderia m) { return mercaderiaRepo.save(m); }
    @Override
    public void guardarDetallesMercaderia(List<MercaderiaTela> detalles) { mercaderiaTelaRepo.saveAll(detalles); }
    @Override
    public List<Mercaderia> listarMercaderias() { return mercaderiaRepo.findAll(); }
    @Override
    public void eliminarMercaderia(Long id) { mercaderiaRepo.deleteById(id); }
    @Override 
    public Optional<Mercaderia> buscarMercaderiaPorId(Long id) { return mercaderiaRepo.findById(id); }

    // --- MÉTODOS COMPUESTOS (RESERVA Y SAGA) ---
    @Override
    public Reserva guardarReserva(Reserva r) { return reservaRepo.save(r); }
    @Override
    public void guardarDetallesReserva(List<ReservaUnidadesRollos> detalles) { reservaUnidadesRollosRepo.saveAll(detalles); }
    @Override
    public List<Reserva> listarReservas() { return reservaRepo.findAll(); }
    @Override
    public void eliminarReserva(Long id) { reservaRepo.deleteById(id); }
    @Override 
    public Optional<Reserva> buscarReservaPorId(Long id) { return reservaRepo.findById(id); }
    
    // Método compensatorio de la SAGA (Llamado desde Laravel si NestJS falla)
    @Override
    public void eliminarReservaPorVenta(Long idVenta) {
        reservaRepo.deleteByIdVenta(idVenta);
    }
}