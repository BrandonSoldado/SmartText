package com.smarttex.inventario.infrastructure.out.persistence;

import com.smarttex.inventario.domain.model.Tela;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

@Repository
public interface TelaJpaRepository extends JpaRepository<Tela, Long> {
}