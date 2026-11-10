import { Entity, PrimaryGeneratedColumn, Column } from 'typeorm';

@Entity('factura')
export class Factura {
  @PrimaryGeneratedColumn()
  id: number;

  @Column('int', { unique: true })
  id_venta: number; // FK Blanda hacia el MS Gestión (Laravel)

  @Column({ length: 100, unique: true, nullable: true }) // Único para IDEMPOTENCIA
  idempotency_key: string;

  @Column({ length: 100, nullable: true })
  nro_factura: string;

  @Column({ length: 200, nullable: true })
  razon_social: string;

  @Column({ length: 50, nullable: true })
  nit_ci: string;

  @Column({ length: 255, nullable: true })
  cuf_codigo_autorizacion: string;

  @Column('date', { nullable: true })
  fecha_emision: string;

  @Column('decimal', { precision: 12, scale: 2, nullable: true })
  monto_total: number;

  @Column({ length: 50, nullable: true })
  estado: string;
}