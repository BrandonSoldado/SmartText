package com.smarttex.inventario.domain.model;

import jakarta.persistence.*;
import lombok.Data;
import java.time.LocalDate;

@Data
@Entity
@Table(name = "inspeccion_calidad")
public class InspeccionCalidad {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @ManyToOne
    @JoinColumn(name = "id_unidad_rollo", nullable = false)
    private UnidadesRollos unidadesRollos;

    @Column(name = "fecha_inspeccion")
    private LocalDate fechaInspeccion;

    @Column(length = 50)
    private String resultado;

    @Column(columnDefinition = "TEXT")
    private String imagen; // Para guardar la ruta o base64 que analizará el modelo de Machine Learning
}