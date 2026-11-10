package com.smarttex.inventario.infrastructure.out.persistence;

import com.smarttex.inventario.domain.model.Mercaderia;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

@Repository
public interface MercaderiaJpaRepository extends JpaRepository<Mercaderia, Long> {
}