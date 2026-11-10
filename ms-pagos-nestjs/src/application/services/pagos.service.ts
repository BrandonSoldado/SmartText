import { Injectable, NotFoundException } from '@nestjs/common';
import { DataSource } from 'typeorm';
import { Tarjeta } from '../../infrastructure/entities/tarjeta.entity';
import { Pago } from '../../infrastructure/entities/pago.entity';
import { Factura } from '../../infrastructure/entities/factura.entity';

@Injectable()
export class PagosService {
  constructor(private dataSource: DataSource) {}

  // ==========================================
  // CRUD TARJETA 
  // ==========================================
  async crearTarjeta(datos: any, id_usuario: number): Promise<Tarjeta> {
    const tarjeta = this.dataSource.manager.create(Tarjeta, {
      ...datos,
      id_usuario
    });
    return this.dataSource.manager.save(tarjeta);
  }

  async listarTarjetas(): Promise<Tarjeta[]> {
    return this.dataSource.manager.find(Tarjeta);
  }

  // NUEVO: Obtener Tarjeta por ID
  async obtenerTarjetaPorId(id: number): Promise<Tarjeta> {
    const tarjeta = await this.dataSource.manager.findOne(Tarjeta, { where: { id } });
    if (!tarjeta) {
      throw new NotFoundException(`Tarjeta con ID ${id} no encontrada`);
    }
    return tarjeta;
  }

  async eliminarTarjeta(id: number): Promise<boolean> {
    await this.dataSource.manager.delete(Tarjeta, id);
    return true;
  }

  // ==========================================
  // CRUD PAGO (Con Idempotencia)
  // ==========================================
  async crearPago(datos: any, id_tarjeta: number, id_venta: number): Promise<Pago> {
    // 1. Control de Idempotencia: Verificar si el pago ya existe
    if (datos.idempotency_key) {
      const pagoExistente = await this.dataSource.manager.findOne(Pago, {
        where: { idempotency_key: datos.idempotency_key }
      });
      if (pagoExistente) {
        return pagoExistente; // Retorna el existente sin duplicar el cobro
      }
    }

    // 2. Crear nuevo pago
    const pago = this.dataSource.manager.create(Pago, {
      ...datos,
      tarjeta: { id: id_tarjeta },
      id_venta,
      fecha: new Date().toISOString().split('T')[0],
      hora: new Date().toTimeString().split(' ')[0],
      estado: 'COMPLETADO'
    });
    return this.dataSource.manager.save(pago);
  }

  async listarPagos(): Promise<Pago[]> {
    return this.dataSource.manager.find(Pago, { relations: { tarjeta: true } });
  }

  // NUEVO: Obtener Pago por ID
  async obtenerPagoPorId(id: number): Promise<Pago> {
    const pago = await this.dataSource.manager.findOne(Pago, { 
      where: { id },
      relations: { tarjeta: true }
    });
    if (!pago) {
      throw new NotFoundException(`Pago con ID ${id} no encontrado`);
    }
    return pago;
  }

  async eliminarPago(id: number): Promise<boolean> {
    await this.dataSource.manager.delete(Pago, id);
    return true;
  }

  // ==========================================
  // CRUD FACTURA (Con Idempotencia)
  // ==========================================
  async crearFactura(datos: any, id_venta: number): Promise<Factura> {
    // 1. Control de Idempotencia
    if (datos.idempotency_key) {
      const facturaExistente = await this.dataSource.manager.findOne(Factura, {
        where: { idempotency_key: datos.idempotency_key }
      });
      if (facturaExistente) {
        return facturaExistente;
      }
    }

    // 2. Crear nueva factura
    const factura = this.dataSource.manager.create(Factura, {
      ...datos,
      id_venta,
      fecha_emision: new Date().toISOString().split('T')[0]
    });
    return this.dataSource.manager.save(factura);
  }

  async listarFacturas(): Promise<Factura[]> {
    return this.dataSource.manager.find(Factura);
  }

  // NUEVO: Obtener Factura por ID
  async obtenerFacturaPorId(id: number): Promise<Factura> {
    const factura = await this.dataSource.manager.findOne(Factura, { where: { id } });
    if (!factura) {
      throw new NotFoundException(`Factura con ID ${id} no encontrada`);
    }
    return factura;
  }

  async eliminarFactura(id: number): Promise<boolean> {
    await this.dataSource.manager.delete(Factura, id);
    return true;
  }
}