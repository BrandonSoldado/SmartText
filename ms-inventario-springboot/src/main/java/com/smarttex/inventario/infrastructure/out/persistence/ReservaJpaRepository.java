package com.smarttex.inventario.infrastructure.out.persistence;

import com.smarttex.inventario.domain.model.Reserva;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

@Repository
public interface ReservaJpaRepository extends JpaRepository<Reserva, Long> {
    // Método personalizado clave para el Rollback de la Saga
    void deleteByIdVenta(Long idVenta);
}