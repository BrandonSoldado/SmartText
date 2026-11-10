package com.smarttex.inventario.domain.model;
import jakarta.persistence.*;
import lombok.Data;
import java.math.BigDecimal;

@Data @Entity @Table(name = "unidades_rollos")
public class UnidadesRollos {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY) private Long id;
    @ManyToOne @JoinColumn(name = "id_tela", nullable = false) private Tela tela;
    @Column(name = "cantidad_inicial", precision = 10, scale = 2) private BigDecimal cantidadInicial;
    @Column(name = "cantidad_actual", precision = 10, scale = 2) private BigDecimal cantidadActual;
    @Column(name = "estado_disponibilidad", length = 50) private String estadoDisponibilidad = "DISPONIBLE";
}