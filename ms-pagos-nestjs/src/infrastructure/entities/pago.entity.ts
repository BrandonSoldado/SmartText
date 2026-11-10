import { Entity, PrimaryGeneratedColumn, Column, ManyToOne, JoinColumn } from 'typeorm';
import { Tarjeta } from './tarjeta.entity';

@Entity('pago')
export class Pago {
  @PrimaryGeneratedColumn()
  id: number;

  @Column('int')
  id_venta: number; // FK Blanda hacia el MS Gestión (Laravel)

  @ManyToOne(() => Tarjeta)
  @JoinColumn({ name: 'id_tarjeta' })
  tarjeta: Tarjeta;

  @Column('decimal', { precision: 10, scale: 2 })
  monto: number;

  @Column({ length: 10, nullable: true })
  moneda: string;

  @Column({ length: 50, nullable: true })
  estado: string;

  @Column({ length: 100, nullable: true })
  stripe_payment_intent_id: string;

  @Column({ length: 100, nullable: true, unique: true }) // Único para IDEMPOTENCIA
  idempotency_key: string;

  @Column('date', { nullable: true })
  fecha: string;

  @Column('time', { nullable: true })
  hora: string;

  @Column({ length: 100, nullable: true })
  stripe_error_code: string;
}