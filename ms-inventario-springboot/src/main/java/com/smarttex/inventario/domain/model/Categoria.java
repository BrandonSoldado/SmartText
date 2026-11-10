package com.smarttex.inventario.domain.model;
import jakarta.persistence.*;
import lombok.Data;

@Data @Entity @Table(name = "categoria")
public class Categoria {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY) private Long id;
    @Column(length = 100, nullable = false) private String nombre;
    @Column(columnDefinition = "TEXT") private String descripcion;
}