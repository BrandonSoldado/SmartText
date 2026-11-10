package com.smarttex.inventario.domain.model;
import jakarta.persistence.*;
import lombok.Data;
import java.math.BigDecimal;
import java.time.LocalDate;

@Data @Entity @Table(name = "mercaderia")
public class Mercaderia {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY) private Long id;
    @ManyToOne @JoinColumn(name = "id_proveedor", nullable = false) private Proveedor proveedor;
    @Column(name = "nro_factura", length = 100) private String nroFactura;
    private LocalDate fecha;
    @Column(name = "monto_total", precision = 12, scale = 2) private BigDecimal montoTotal;
}