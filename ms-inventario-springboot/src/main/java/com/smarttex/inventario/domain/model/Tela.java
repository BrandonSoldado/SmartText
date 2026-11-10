package com.smarttex.inventario.domain.model;
import jakarta.persistence.*;
import lombok.Data;
import java.math.BigDecimal;

@Data @Entity @Table(name = "tela")
public class Tela {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY) private Long id;
    @ManyToOne @JoinColumn(name = "id_categoria") private Categoria categoria;
    @Column(length = 150, nullable = false) private String nombre;
    @Column(name = "tipo_tela", length = 100) private String tipoTela;
    @Column(length = 50) private String color;
    @Column(precision = 8, scale = 2) private BigDecimal gramaje;
    @Column(length = 150) private String composicion;
    @Column(name = "unidad_medida", length = 20) private String unidadMedida;
    @Column(precision = 10, scale = 2) private BigDecimal precio;
    private Integer stock;
}