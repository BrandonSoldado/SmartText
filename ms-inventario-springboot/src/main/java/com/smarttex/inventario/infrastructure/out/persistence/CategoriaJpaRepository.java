package com.smarttex.inventario.infrastructure.out.persistence;

import com.smarttex.inventario.domain.model.Categoria;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

@Repository
public interface CategoriaJpaRepository extends JpaRepository<Categoria, Long> {
}