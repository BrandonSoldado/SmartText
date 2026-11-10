package com.smarttex.inventario.application.service;

import com.smarttex.inventario.domain.model.*;
import com.smarttex.inventario.domain.ports.in.InventarioUseCase;
import com.smarttex.inventario.domain.ports.out.InventarioRepositoryPort;
import com.smarttex.inventario.infrastructure.in.web.DTOs;
import lombok.RequiredArgsConstructor;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.math.BigDecimal;
import java.time.LocalDate;
import java.util.List;

@Service 
@RequiredArgsConstructor
public class InventarioService implements InventarioUseCase {
    private final InventarioRepositoryPort port;

    @Override public Categoria crearCategoria(Categoria c) { return port.guardarCategoria(c); }
    @Override public List<Categoria> listarCategorias() { return port.listarCategorias(); }
    @Override public void eliminarCategoria(Long id) { port.eliminarCategoria(id); }
    @Override public Categoria obtenerCategoriaPorId(Long id) { return port.buscarCategoriaPorId(id).orElseThrow(); }

    @Override public Proveedor crearProveedor(Proveedor p) { return port.guardarProveedor(p); }
    @Override public List<Proveedor> listarProveedores() { return port.listarProveedores(); }
    @Override public void eliminarProveedor(Long id) { port.eliminarProveedor(id); }
    @Override public Proveedor obtenerProveedorPorId(Long id) { return port.buscarProveedorPorId(id).orElseThrow(); }

    @Override public Tela crearTela(Tela t, Long idCategoria) {
        t.setCategoria(port.buscarCategoriaPorId(idCategoria).orElseThrow());
        return port.guardarTela(t);
    }
    @Override public List<Tela> listarTelas() { return port.listarTelas(); }
    @Override public void eliminarTela(Long id) { port.eliminarTela(id); }
    @Override public Tela obtenerTelaPorId(Long id) { return port.buscarTelaPorId(id).orElseThrow(); }

    @Override public UnidadesRollos crearUnidadRollo(UnidadesRollos u, Long idTela) {
        u.setTela(port.buscarTelaPorId(idTela).orElseThrow());
        return port.guardarUnidadRollo(u);
    }
    @Override public List<UnidadesRollos> listarUnidadesRollos() { return port.listarUnidadesRollos(); }
    @Override public void eliminarUnidadRollo(Long id) { port.eliminarUnidadRollo(id); }
    @Override public UnidadesRollos obtenerUnidadRolloPorId(Long id) { return port.buscarUnidadRolloPorId(id).orElseThrow(); }

    @Override public InspeccionCalidad crearInspeccion(InspeccionCalidad i, Long idUnidadRollo) {
        i.setUnidadesRollos(port.buscarUnidadRolloPorId(idUnidadRollo).orElseThrow());
        i.setFechaInspeccion(LocalDate.now());
        return port.guardarInspeccion(i);
    }
    @Override public List<InspeccionCalidad> listarInspecciones() { return port.listarInspecciones(); }
    @Override public void eliminarInspeccion(Long id) { port.eliminarInspeccion(id); }
    @Override public InspeccionCalidad obtenerInspeccionPorId(Long id) { return port.buscarInspeccionPorId(id).orElseThrow(); }

    @Override public DefectoDetectado crearDefecto(DefectoDetectado d, Long idInspeccion) {
        d.setInspeccionCalidad(port.buscarInspeccionPorId(idInspeccion).orElseThrow());
        return port.guardarDefecto(d);
    }
    @Override public List<DefectoDetectado> listarDefectos() { return port.listarDefectos(); }
    @Override public void eliminarDefecto(Long id) { port.eliminarDefecto(id); }
    @Override public DefectoDetectado obtenerDefectoPorId(Long id) { return port.buscarDefectoPorId(id).orElseThrow(); }

    @Override 
    @Transactional
    public Mercaderia crearMercaderia(Long idProveedor, Double montoTotal, String nroFactura, List<MercaderiaTela> detalles) {
        Mercaderia m = new Mercaderia();
        m.setProveedor(port.buscarProveedorPorId(idProveedor).orElseThrow());
        
        // Conversión de Double a BigDecimal
        m.setMontoTotal(montoTotal != null ? BigDecimal.valueOf(montoTotal) : BigDecimal.ZERO);
        
        m.setNroFactura(nroFactura);
        m.setFecha(LocalDate.now());
        
        Mercaderia guardada = port.guardarMercaderia(m);
        detalles.forEach(d -> d.setMercaderia(guardada));
        port.guardarDetallesMercaderia(detalles);
        return guardada;
    }
    
    @Override public List<Mercaderia> listarMercaderias() { return port.listarMercaderias(); }
    @Override public void eliminarMercaderia(Long id) { port.eliminarMercaderia(id); }
    @Override public Mercaderia obtenerMercaderiaPorId(Long id) { return port.buscarMercaderiaPorId(id).orElseThrow(); }

    @Override 
    @Transactional
    public Reserva crearReserva(Long idVenta, List<DTOs.RolloItem> rollos) {
        Reserva r = new Reserva();
        r.setIdVenta(idVenta);
        r.setEstado("RESERVADO");
        r.setFecha(LocalDate.now());
        Reserva guardada = port.guardarReserva(r);

        List<ReservaUnidadesRollos> detalles = rollos.stream().map(item -> {
            ReservaUnidadesRollos d = new ReservaUnidadesRollos();
            d.setReserva(guardada);
            d.setUnidadRollo(port.buscarUnidadRolloPorId(item.getIdUnidadRollo()).orElseThrow());
            d.setCantidad(item.getCantidad());
            return d;
        }).toList();

        port.guardarDetallesReserva(detalles);
        return guardada;
    }
    
    @Override public List<Reserva> listarReservas() { return port.listarReservas(); }
    @Override public void eliminarReserva(Long id) { port.eliminarReserva(id); }
    @Override public Reserva obtenerReservaPorId(Long id) { return port.buscarReservaPorId(id).orElseThrow(); }
    @Override @Transactional public void eliminarReservaPorVenta(Long idVenta) { port.eliminarReservaPorVenta(idVenta); }
}