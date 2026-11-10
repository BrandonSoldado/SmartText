import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { JwtModule } from '@nestjs/jwt'; // <-- Importar módulo
import { Tarjeta } from './infrastructure/entities/tarjeta.entity';
import { Pago } from './infrastructure/entities/pago.entity';
import { Factura } from './infrastructure/entities/factura.entity';
import { PagosController } from './infrastructure/controllers/pagos.controller';
import { PagosService } from './application/services/pagos.service';

@Module({
  imports: [
    TypeOrmModule.forFeature([Tarjeta, Pago, Factura]),
    JwtModule.register({}), // <-- Registrar el módulo JWT
  ],
  controllers: [PagosController],
  providers: [PagosService],
})
export class PagosModule {}