package com.smarttex.inventario.domain.model;
import jakarta.persistence.*;
import lombok.Data;
import java.math.BigDecimal;

@Data @Entity @Table(name = "defecto_detectado")
public class DefectoDetectado {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY) private Long id;
    @ManyToOne @JoinColumn(name = "id_inspeccion", nullable = false) private InspeccionCalidad inspeccionCalidad;
    @Column(name = "tipo_defecto", length = 100) private String tipoDefecto;
    @Column(precision = 5, scale = 2) private BigDecimal confianza; 
}