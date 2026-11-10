import { Entity, PrimaryGeneratedColumn, Column } from 'typeorm';

@Entity('tarjeta')
export class Tarjeta {
  @PrimaryGeneratedColumn()
  id: number;

  @Column('int')
  id_usuario: number; // FK Blanda hacia el MS Gestión (Laravel)

  @Column({ length: 50, nullable: true })
  brand: string;

  @Column('int', { nullable: true })
  exp_month: number;

  @Column('int', { nullable: true })
  exp_year: number;

  @Column({ length: 4, nullable: true })
  last4: string;

  @Column({ length: 100, nullable: true })
  stripe_payment_method_id: string;
}