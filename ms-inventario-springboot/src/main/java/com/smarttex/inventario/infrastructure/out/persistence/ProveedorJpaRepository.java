package com.smarttex.inventario.infrastructure.out.persistence;

import com.smarttex.inventario.domain.model.Proveedor;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

@Repository
public interface ProveedorJpaRepository extends JpaRepository<Proveedor, Long> {
}