package com.smarttex.inventario.infrastructure.out.persistence;

import com.smarttex.inventario.domain.model.MercaderiaTela;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

@Repository
public interface MercaderiaTelaJpaRepository extends JpaRepository<MercaderiaTela, Long> {
}