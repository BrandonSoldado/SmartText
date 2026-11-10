package com.smarttex.inventario.domain.model;
import jakarta.persistence.*;
import lombok.Data;
import java.math.BigDecimal;

@Data @Entity @Table(name = "reserva_unidades_rollos")
public class ReservaUnidadesRollos {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY) private Long id;
    @ManyToOne @JoinColumn(name = "id_reserva", nullable = false) private Reserva reserva;
    @ManyToOne @JoinColumn(name = "id_unidad_rollo", nullable = false) private UnidadesRollos unidadRollo;
    @Column(precision = 10, scale = 2) private BigDecimal cantidad;
}