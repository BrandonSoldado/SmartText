package com.smarttex.inventario.domain.ports.out;
import com.smarttex.inventario.domain.model.*;
import java.util.List;
import java.util.Optional;

public interface InventarioRepositoryPort {
    Categoria guardarCategoria(Categoria c); List<Categoria> listarCategorias(); void eliminarCategoria(Long id); Optional<Categoria> buscarCategoriaPorId(Long id);
    Proveedor guardarProveedor(Proveedor p); List<Proveedor> listarProveedores(); void eliminarProveedor(Long id); Optional<Proveedor> buscarProveedorPorId(Long id);
    Tela guardarTela(Tela t); List<Tela> listarTelas(); void eliminarTela(Long id); Optional<Tela> buscarTelaPorId(Long id);
    UnidadesRollos guardarUnidadRollo(UnidadesRollos u); List<UnidadesRollos> listarUnidadesRollos(); void eliminarUnidadRollo(Long id); Optional<UnidadesRollos> buscarUnidadRolloPorId(Long id);
    InspeccionCalidad guardarInspeccion(InspeccionCalidad i); List<InspeccionCalidad> listarInspecciones(); void eliminarInspeccion(Long id); Optional<InspeccionCalidad> buscarInspeccionPorId(Long id);
    DefectoDetectado guardarDefecto(DefectoDetectado d); List<DefectoDetectado> listarDefectos(); void eliminarDefecto(Long id); Optional<DefectoDetectado> buscarDefectoPorId(Long id);
    Mercaderia guardarMercaderia(Mercaderia m); void guardarDetallesMercaderia(List<MercaderiaTela> detalles); List<Mercaderia> listarMercaderias(); void eliminarMercaderia(Long id); Optional<Mercaderia> buscarMercaderiaPorId(Long id);
    Reserva guardarReserva(Reserva r); void guardarDetallesReserva(List<ReservaUnidadesRollos> detalles); List<Reserva> listarReservas(); void eliminarReserva(Long id); Optional<Reserva> buscarReservaPorId(Long id);
    void eliminarReservaPorVenta(Long idVenta);
}