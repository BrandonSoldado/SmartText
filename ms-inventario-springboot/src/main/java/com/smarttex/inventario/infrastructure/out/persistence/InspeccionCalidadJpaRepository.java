package com.smarttex.inventario.infrastructure.out.persistence;

import com.smarttex.inventario.domain.model.InspeccionCalidad;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

@Repository
public interface InspeccionCalidadJpaRepository extends JpaRepository<InspeccionCalidad, Long> {
}