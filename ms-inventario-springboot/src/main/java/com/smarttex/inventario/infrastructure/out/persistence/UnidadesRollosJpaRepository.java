package com.smarttex.inventario.infrastructure.out.persistence;

import com.smarttex.inventario.domain.model.UnidadesRollos;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

@Repository
public interface UnidadesRollosJpaRepository extends JpaRepository<UnidadesRollos, Long> {
}