package com.smarttex.inventario.infrastructure.in.web;
import com.smarttex.inventario.domain.model.*;
import lombok.Data;
import java.math.BigDecimal;
import java.util.List;

public class DTOs {
    @Data public static class TelaRequest { private Tela tela; private Long idCategoria; }
    @Data public static class UnidadRolloRequest { private UnidadesRollos unidadRollo; private Long idTela; }
    @Data public static class InspeccionRequest { private InspeccionCalidad inspeccion; private Long idUnidadRollo; }
    @Data public static class DefectoRequest { private DefectoDetectado defecto; private Long idInspeccion; }
    @Data public static class MercaderiaRequest { private Long idProveedor; private Double montoTotal; private String nroFactura; private List<MercaderiaTela> telas; }
    @Data public static class ReservaRequest { private Long idVenta; private List<RolloItem> rollos; }
    @Data public static class RolloItem { private Long idUnidadRollo; private BigDecimal cantidad; }
}