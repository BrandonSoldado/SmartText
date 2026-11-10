import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { ConfigModule } from '@nestjs/config';
import { PagosModule } from './pagos.module';

@Module({
  imports: [
    ConfigModule.forRoot({
      isGlobal: true, // Mantenemos isGlobal para que tu JWT_SECRET funcione en otros módulos
    }),
    TypeOrmModule.forRoot({
      type: 'postgres',
      host: process.env.DB_HOST,
      port: Number(process.env.DB_PORT),
      username: process.env.DB_USER,
      password: process.env.DB_PASSWORD,
      database: process.env.DB_NAME,
      autoLoadEntities: true,
      synchronize: true,
      ssl: {
        rejectUnauthorized: false,
      },
    }),
    PagosModule,
  ],
})
export class AppModule {}