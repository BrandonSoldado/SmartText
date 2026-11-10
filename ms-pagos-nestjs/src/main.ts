import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';

async function bootstrap() {
  const app = await NestFactory.create(AppModule);
  app.enableCors();
  
  // Lee el puerto del .env, si no lo encuentra usa el 8003
  const port = process.env.PORT || 8003;
  
  await app.listen(port);
  console.log(`MS-Pagos (NestJS) ejecutándose en el puerto: ${port}`);
}
bootstrap();