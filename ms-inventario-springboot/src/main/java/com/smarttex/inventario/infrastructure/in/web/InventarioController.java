package com.smarttex.inventario.infrastructure.in.web;
import com.smarttex.inventario.domain.model.*;
import com.smarttex.inventario.domain.ports.in.InventarioUseCase;
import lombok.RequiredArgsConstructor;
import org.springframework.web.bind.annotation.*;
import java.util.List;

@RestController @RequestMapping("/api/inventario") @RequiredArgsConstructor
public class InventarioController {
    private final InventarioUseCase useCase;

    @PostMapping("/categoria") public Categoria crearCategoria(@RequestBody Categoria c) { return useCase.crearCategoria(c); }
    @GetMapping("/categoria") public List<Categoria> listarCategorias() { return useCase.listarCategorias(); }
    @GetMapping("/categoria/{id}") public Categoria obtenerCategoria(@PathVariable Long id) { return useCase.obtenerCategoriaPorId(id); }
    @DeleteMapping("/categoria/{id}") public void eliminarCategoria(@PathVariable Long id) { useCase.eliminarCategoria(id); }

    @PostMapping("/proveedor") public Proveedor crearProveedor(@RequestBody Proveedor p) { return useCase.crearProveedor(p); }
    @GetMapping("/proveedor") public List<Proveedor> listarProveedores() { return useCase.listarProveedores(); }
    @GetMapping("/proveedor/{id}") public Proveedor obtenerProveedor(@PathVariable Long id) { return useCase.obtenerProveedorPorId(id); }
    @DeleteMapping("/proveedor/{id}") public void eliminarProveedor(@PathVariable Long id) { useCase.eliminarProveedor(id); }

    @PostMapping("/tela") public Tela crearTela(@RequestBody DTOs.TelaRequest req) { return useCase.crearTela(req.getTela(), req.getIdCategoria()); }
    @GetMapping("/tela") public List<Tela> listarTelas() { return useCase.listarTelas(); }
    @GetMapping("/tela/{id}") public Tela obtenerTela(@PathVariable Long id) { return useCase.obtenerTelaPorId(id); }
    @DeleteMapping("/tela/{id}") public void eliminarTela(@PathVariable Long id) { useCase.eliminarTela(id); }

    @PostMapping("/unidad-rollo") public UnidadesRollos crearRollo(@RequestBody DTOs.UnidadRolloRequest req) { return useCase.crearUnidadRollo(req.getUnidadRollo(), req.getIdTela()); }
    @GetMapping("/unidad-rollo") public List<UnidadesRollos> listarRollos() { return useCase.listarUnidadesRollos(); }
    @GetMapping("/unidad-rollo/{id}") public UnidadesRollos obtenerRollo(@PathVariable Long id) { return useCase.obtenerUnidadRolloPorId(id); }
    @DeleteMapping("/unidad-rollo/{id}") public void eliminarRollo(@PathVariable Long id) { useCase.eliminarUnidadRollo(id); }

    @PostMapping("/inspeccion") public InspeccionCalidad crearInspeccion(@RequestBody DTOs.InspeccionRequest req) { return useCase.crearInspeccion(req.getInspeccion(), req.getIdUnidadRollo()); }
    @GetMapping("/inspeccion") public List<InspeccionCalidad> listarInspecciones() { return useCase.listarInspecciones(); }
    @GetMapping("/inspeccion/{id}") public InspeccionCalidad obtenerInspeccion(@PathVariable Long id) { return useCase.obtenerInspeccionPorId(id); }
    @DeleteMapping("/inspeccion/{id}") public void eliminarInspeccion(@PathVariable Long id) { useCase.eliminarInspeccion(id); }

    @PostMapping("/defecto") public DefectoDetectado crearDefecto(@RequestBody DTOs.DefectoRequest req) { return useCase.crearDefecto(req.getDefecto(), req.getIdInspeccion()); }
    @GetMapping("/defecto") public List<DefectoDetectado> listarDefectos() { return useCase.listarDefectos(); }
    @GetMapping("/defecto/{id}") public DefectoDetectado obtenerDefecto(@PathVariable Long id) { return useCase.obtenerDefectoPorId(id); }
    @DeleteMapping("/defecto/{id}") public void eliminarDefecto(@PathVariable Long id) { useCase.eliminarDefecto(id); }

    @PostMapping("/mercaderia") public Mercaderia crearMercaderia(@RequestBody DTOs.MercaderiaRequest req) { return useCase.crearMercaderia(req.getIdProveedor(), req.getMontoTotal(), req.getNroFactura(), req.getTelas()); }
    @GetMapping("/mercaderia") public List<Mercaderia> listarMercaderias() { return useCase.listarMercaderias(); }
    @GetMapping("/mercaderia/{id}") public Mercaderia obtenerMercaderia(@PathVariable Long id) { return useCase.obtenerMercaderiaPorId(id); }
    @DeleteMapping("/mercaderia/{id}") public void eliminarMercaderia(@PathVariable Long id) { useCase.eliminarMercaderia(id); }

    @PostMapping("/reserva") public Reserva crearReserva(@RequestBody DTOs.ReservaRequest req) { return useCase.crearReserva(req.getIdVenta(), req.getRollos()); }
    @GetMapping("/reserva") public List<Reserva> listarReservas() { return useCase.listarReservas(); }
    @GetMapping("/reserva/{id}") public Reserva obtenerReserva(@PathVariable Long id) { return useCase.obtenerReservaPorId(id); }
    @DeleteMapping("/reserva/{id}") public void eliminarReserva(@PathVariable Long id) { useCase.eliminarReserva(id); }
    @DeleteMapping("/reserva/venta/{idVenta}") public void rollbackReserva(@PathVariable Long idVenta) { useCase.eliminarReservaPorVenta(idVenta); }
}