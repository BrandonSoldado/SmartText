package com.smarttex.inventario.domain.model;

import jakarta.persistence.*;
import lombok.Data;
import java.time.LocalDate;

@Data
@Entity
@Table(name = "reserva")
public class Reserva {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    // Relación blanda con Venta (MS Gestión - Laravel) como indica la línea en el diagrama
    @Column(name = "id_venta", unique = true, nullable = false)
    private Long idVenta; 

    @Column(length = 50)
    private String estado = "RESERVADO";

    private LocalDate fecha = LocalDate.now();
}