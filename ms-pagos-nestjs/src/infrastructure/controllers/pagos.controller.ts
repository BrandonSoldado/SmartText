import { Controller, Get, Post, Delete, Body, Param, ParseIntPipe, UseGuards } from '@nestjs/common';
import { PagosService } from '../../application/services/pagos.service';
import { JwtAuthGuard } from '../../application/guards/jwt-auth.guard';

// Al aplicar el guard aquí, TODOS los endpoints de /api/pagos exigirán el Token
@UseGuards(JwtAuthGuard)
@Controller('api/pagos')
export class PagosController {
  constructor(private readonly pagosService: PagosService) {}

  // --- RUTAS TARJETA ---
  @Post('tarjeta')
  crearTarjeta(@Body() body: any) {
    const { id_usuario, ...datos } = body;
    return this.pagosService.crearTarjeta(datos, id_usuario);
  }
  
  @Get('tarjeta')
  listarTarjetas() {
    return this.pagosService.listarTarjetas();
  }

  // NUEVO: Ruta para Obtener Tarjeta por ID
  @Get('tarjeta/:id')
  obtenerTarjetaPorId(@Param('id', ParseIntPipe) id: number) {
    return this.pagosService.obtenerTarjetaPorId(id);
  }
  
  @Delete('tarjeta/:id')
  eliminarTarjeta(@Param('id', ParseIntPipe) id: number) {
    return this.pagosService.eliminarTarjeta(id);
  }

  // --- RUTAS PAGO ---
  @Post('procesar')
  crearPago(@Body() body: any) {
    const { id_tarjeta, id_venta, ...datos } = body;
    return this.pagosService.crearPago(datos, id_tarjeta || null, id_venta);
  }
  
  @Get('pago')
  listarPagos() {
    return this.pagosService.listarPagos();
  }

  // NUEVO: Ruta para Obtener Pago por ID
  @Get('pago/:id')
  obtenerPagoPorId(@Param('id', ParseIntPipe) id: number) {
    return this.pagosService.obtenerPagoPorId(id);
  }
  
  @Delete('pago/:id')
  eliminarPago(@Param('id', ParseIntPipe) id: number) {
    return this.pagosService.eliminarPago(id);
  }

  // --- RUTAS FACTURA ---
  @Post('factura')
  crearFactura(@Body() body: any) {
    const { id_venta, ...datos } = body;
    return this.pagosService.crearFactura(datos, id_venta);
  }
  
  @Get('factura')
  listarFacturas() {
    return this.pagosService.listarFacturas();
  }

  // NUEVO: Ruta para Obtener Factura por ID
  @Get('factura/:id')
  obtenerFacturaPorId(@Param('id', ParseIntPipe) id: number) {
    return this.pagosService.obtenerFacturaPorId(id);
  }
  
  @Delete('factura/:id')
  eliminarFactura(@Param('id', ParseIntPipe) id: number) {
    return this.pagosService.eliminarFactura(id);
  }
}