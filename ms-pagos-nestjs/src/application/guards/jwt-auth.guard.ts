import { Injectable, CanActivate, ExecutionContext, UnauthorizedException } from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';

@Injectable()
export class JwtAuthGuard implements CanActivate {
  constructor(private jwtService: JwtService) {}

  async canActivate(context: ExecutionContext): Promise<boolean> {
    const request = context.switchToHttp().getRequest();
    const authHeader = request.headers.authorization;

    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      throw new UnauthorizedException('Debe enviar el Token JWT de Laravel en el header Authorization');
    }

    const token = authHeader.split(' ')[1];

    try {
      // Verifica el token usando la misma clave secreta de Laravel
      const payload = this.jwtService.verify(token, { secret: process.env.JWT_SECRET });
      
      // Adjunta los datos del usuario (id, rol) al request para usarlos si es necesario
      request['user'] = payload;
    } catch {
      throw new UnauthorizedException('Token inválido o expirado');
    }
    
    return true;
  }
}