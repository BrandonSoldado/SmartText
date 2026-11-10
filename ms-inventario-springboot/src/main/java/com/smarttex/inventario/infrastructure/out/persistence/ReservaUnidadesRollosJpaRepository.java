package com.smarttex.inventario.infrastructure.out.persistence;

import com.smarttex.inventario.domain.model.ReservaUnidadesRollos;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

@Repository
public interface ReservaUnidadesRollosJpaRepository extends JpaRepository<ReservaUnidadesRollos, Long> {
}