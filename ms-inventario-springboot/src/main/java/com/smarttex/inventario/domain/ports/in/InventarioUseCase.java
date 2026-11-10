package com.smarttex.inventario.domain.ports.in;
import com.smarttex.inventario.domain.model.*;
import com.smarttex.inventario.infrastructure.in.web.DTOs;
import java.util.List;

public interface InventarioUseCase {
    Categoria crearCategoria(Categoria c); List<Categoria> listarCategorias(); void eliminarCategoria(Long id); Categoria obtenerCategoriaPorId(Long id);
    Proveedor crearProveedor(Proveedor p); List<Proveedor> listarProveedores(); void eliminarProveedor(Long id); Proveedor obtenerProveedorPorId(Long id);
    Tela crearTela(Tela t, Long idCategoria); List<Tela> listarTelas(); void eliminarTela(Long id); Tela obtenerTelaPorId(Long id);
    UnidadesRollos crearUnidadRollo(UnidadesRollos u, Long idTela); List<UnidadesRollos> listarUnidadesRollos(); void eliminarUnidadRollo(Long id); UnidadesRollos obtenerUnidadRolloPorId(Long id);
    InspeccionCalidad crearInspeccion(InspeccionCalidad i, Long idUnidadRollo); List<InspeccionCalidad> listarInspecciones(); void eliminarInspeccion(Long id); InspeccionCalidad obtenerInspeccionPorId(Long id);
    DefectoDetectado crearDefecto(DefectoDetectado d, Long idInspeccion); List<DefectoDetectado> listarDefectos(); void eliminarDefecto(Long id); DefectoDetectado obtenerDefectoPorId(Long id);
    Mercaderia crearMercaderia(Long idProveedor, Double montoTotal, String nroFactura, List<MercaderiaTela> detalles); List<Mercaderia> listarMercaderias(); void eliminarMercaderia(Long id); Mercaderia obtenerMercaderiaPorId(Long id);
    Reserva crearReserva(Long idVenta, List<DTOs.RolloItem> rollos); List<Reserva> listarReservas(); void eliminarReserva(Long id); Reserva obtenerReservaPorId(Long id);
    void eliminarReservaPorVenta(Long idVenta);
}