package com.smarttex.inventario.domain.model;
import jakarta.persistence.*;
import lombok.Data;
import java.math.BigDecimal;

@Data @Entity @Table(name = "mercaderia_tela")
public class MercaderiaTela {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY) private Long id;
    @ManyToOne @JoinColumn(name = "id_mercaderia", nullable = false) private Mercaderia mercaderia;
    @ManyToOne @JoinColumn(name = "id_tela", nullable = false) private Tela tela;
    @Column(precision = 10, scale = 2) private BigDecimal cantidad;
    @Column(precision = 10, scale = 2) private BigDecimal precio;
}