import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpHeaders, HttpResponse } from '@angular/common/http';
import { Observable, from, map, switchMap, throwError } from 'rxjs';
import { environment } from '../environments/environment.development';

// ======================================================
// CONFIGURACIÓN
// ======================================================
const BASE_URL = environment.apiUrl; 
const GRAPHQL_URL = `${BASE_URL}/graphql`;
const REPORTE_REST_URL = `${BASE_URL}/api/reporte-natural`;
const TOKEN_KEY = 'smarttex_token';
const USER_KEY = 'smarttex_usuario';

// ======================================================
// TIPOS
// ======================================================
export type FormatoReporte = 'json' | 'excel' | 'pdf' | 'word';

export interface Permiso { id: string; nombre: string; descripcion?: string | null; }
export interface Rol { id: string; nombre: string; permisos?: Permiso[]; }
export interface Usuario {
  id: string; id_rol: number; nombres: string; apellido_p?: string | null;
  apellido_m?: string | null; email: string; foto?: string | null;
  telefono?: string | null; fecha_nacimiento?: string | null;
  stripe_customer_id?: string | null; rol?: Rol;
}
export interface DetalleVenta { id_unidad_rollo: number; cantidad: number; precio: number; }
export interface Venta {
  id: string; total: number; estado: 'PENDIENTE' | 'COMPLETADO' | 'CANCELADO';
  fecha: string; hora: string; motivo_cancelacion?: string | null;
  usuario?: Usuario; detalles?: DetalleVenta[];
}
export interface AuthResponse { token: string; usuario: Usuario; }

export interface NuevoUsuario {
  id_rol: number; nombres: string; email: string; passwords: string;
  apellido_p?: string; apellido_m?: string; telefono?: string;
  fecha_nacimiento?: string; stripe_customer_id?: string; foto?: string;
}

export interface ArchivoReporte { nombre: string; mime: string; base64: string; }
export interface ReporteJson {
  version?: string;
  metadatos?: {
    titulo: string; recurso: string; periodo: string; fecha_generacion: string;
    total_registros: number; registros_incluidos: number; notas: any;
  };
  columnas?: { titulo: string; [k: string]: any }[];
  encabezados?: string[];
  filas?: any[];
  resumen?: any;
  archivo?: ArchivoReporte;
  error_exportacion?: string;
  mensaje?: string; // pedido ambiguo
  error?: string;   // error del agente
}

export type ReporteRestResultado =
  | { tipo: 'json'; data: ReporteJson }
  | { tipo: 'archivo'; blob: Blob; nombre: string };

// ======================================================
// SERVICIO GLOBAL
// ======================================================
@Injectable({ providedIn: 'root' })
export class AuthService {
  private http = inject(HttpClient);

  // ======================================================
  // SESIÓN
  // ======================================================
  getToken(): string | null { return localStorage.getItem(TOKEN_KEY); }

  isLoggedIn(): boolean { return !!this.getToken(); }

  getCurrentUser(): Usuario | null {
    try {
      const raw = localStorage.getItem(USER_KEY);
      return raw ? (JSON.parse(raw) as Usuario) : null;
    } catch {
      return null;
    }
  }

  logout(): void {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
  }

  private headers(): HttpHeaders {
    let h = new HttpHeaders({ 'Content-Type': 'application/json' });
    const t = this.getToken();
    if (t) h = h.set('Authorization', `Bearer ${t}`);
    return h;
  }

  // ---------- Helper GraphQL ----------
  // Envía { query, variables } a /graphql y devuelve data[campo].
  // Si llega "errors", lanza el primer mensaje.
  private gql<T>(campo: string, query: string, variables: Record<string, any> = {}): Observable<T> {
    return this.http
      .post<any>(GRAPHQL_URL, { query, variables }, { headers: this.headers() })
      .pipe(
        switchMap((res) =>
          res.errors?.length
            ? throwError(() => new Error(res.errors[0].message))
            : from([res.data[campo] as T])
        )
      );
  }

  // ======================================================
  // AUTENTICACIÓN (públicas)
  // ======================================================

  /** Inicia sesión, guarda token y usuario, y devuelve el usuario. */
  login(email: string, pass: string): Observable<Usuario> {
    const query = `
      mutation LoginUnico($email: String!, $pass: String!) {
        loginUnico(email: $email, pass: $pass) {
          token
          usuario { id id_rol nombres apellido_p apellido_m email foto telefono
                    rol { id nombre permisos { id nombre } } }
        }
      }`;
    return this.gql<AuthResponse>('loginUnico', query, { email, pass }).pipe(
      map((r) => {
        localStorage.setItem(TOKEN_KEY, r.token);
        localStorage.setItem(USER_KEY, JSON.stringify(r.usuario));
        return r.usuario;
      })
    );
  }

  /** Registro público (mutation crearUsuario). */
  registrar(datos: NuevoUsuario): Observable<Usuario> {
    const query = `
      mutation CrearUsuario($id_rol: Int!, $nombres: String!, $email: String!, $passwords: String!,
        $apellido_p: String, $apellido_m: String, $telefono: String,
        $fecha_nacimiento: String, $stripe_customer_id: String, $foto: String) {
        crearUsuario(id_rol: $id_rol, nombres: $nombres, email: $email, passwords: $passwords,
          apellido_p: $apellido_p, apellido_m: $apellido_m, telefono: $telefono,
          fecha_nacimiento: $fecha_nacimiento, stripe_customer_id: $stripe_customer_id, foto: $foto) {
          id id_rol nombres apellido_p apellido_m email telefono
        }
      }`;
    return this.gql<Usuario>('crearUsuario', query, datos as any);
  }

  // ======================================================
  // PERMISOS
  // ======================================================
  listarPermisos(): Observable<Permiso[]> {
    return this.gql('listarPermisos', `query { listarPermisos { id nombre descripcion } }`);
  }

  obtenerPermisoPorId(id: string | number): Observable<Permiso> {
    return this.gql('obtenerPermisoPorId',
      `query($id: ID!) { obtenerPermisoPorId(id: $id) { id nombre descripcion } }`, { id });
  }

  crearPermiso(nombre: string, descripcion?: string): Observable<Permiso> {
    return this.gql('crearPermiso',
      `mutation($nombre: String!, $descripcion: String) {
         crearPermiso(nombre: $nombre, descripcion: $descripcion) { id nombre descripcion } }`,
      { nombre, descripcion });
  }

  eliminarPermiso(id: string | number): Observable<boolean> {
    return this.gql('eliminarPermiso', `mutation($id: ID!) { eliminarPermiso(id: $id) }`, { id });
  }

  // ======================================================
  // ROLES
  // ======================================================
  listarRoles(): Observable<Rol[]> {
    return this.gql('listarRoles',
      `query { listarRoles { id nombre permisos { id nombre descripcion } } }`);
  }

  obtenerRolPorId(id: string | number): Observable<Rol> {
    return this.gql('obtenerRolPorId',
      `query($id: ID!) { obtenerRolPorId(id: $id) { id nombre permisos { id nombre descripcion } } }`,
      { id });
  }

  crearRol(nombre: string, permisosIds?: number[]): Observable<Rol> {
    return this.gql('crearRol',
      `mutation($nombre: String!, $permisosIds: [Int!]) {
         crearRol(nombre: $nombre, permisosIds: $permisosIds) { id nombre permisos { id nombre } } }`,
      { nombre, permisosIds });
  }

  eliminarRol(id: string | number): Observable<boolean> {
    return this.gql('eliminarRol', `mutation($id: ID!) { eliminarRol(id: $id) }`, { id });
  }

  // ======================================================
  // USUARIOS
  // ======================================================
  listarUsuarios(): Observable<Usuario[]> {
    return this.gql('listarUsuarios',
      `query { listarUsuarios { id id_rol nombres apellido_p apellido_m email telefono
                                rol { id nombre } } }`);
  }

  obtenerUsuarioPorId(id: string | number): Observable<Usuario> {
    return this.gql('obtenerUsuarioPorId',
      `query($id: ID!) { obtenerUsuarioPorId(id: $id) { id id_rol nombres apellido_p apellido_m
                          email foto telefono fecha_nacimiento stripe_customer_id
                          rol { id nombre } } }`, { id });
  }

  eliminarUsuario(id: string | number): Observable<boolean> {
    return this.gql('eliminarUsuario', `mutation($id: ID!) { eliminarUsuario(id: $id) }`, { id });
  }

  // ======================================================
  // VENTAS
  // ======================================================
  private readonly VENTA_FIELDS = `
    id total estado fecha hora motivo_cancelacion
    usuario { id nombres email }
    detalles { id_unidad_rollo cantidad precio }`;

  listarVentas(): Observable<Venta[]> {
    return this.gql('listarVentas', `query { listarVentas { ${this.VENTA_FIELDS} } }`);
  }

  obtenerVentaPorId(id: string | number): Observable<Venta> {
    return this.gql('obtenerVentaPorId',
      `query($id: ID!) { obtenerVentaPorId(id: $id) { ${this.VENTA_FIELDS} } }`, { id });
  }

  /** Crea la venta con Saga. El idempotencyKey se genera una vez; reutilízalo en reintentos. */
  crearVenta(
    id_usuario: number,
    total: number,
    detalles: DetalleVenta[],
    idempotencyKey: string = crypto.randomUUID()
  ): Observable<Venta> {
    const query = `
      mutation CrearVenta($id_usuario: Int!, $total: Float!,
                          $detalles: [DetalleVentaInput!]!, $idempotencyKey: String!) {
        crearVenta(id_usuario: $id_usuario, total: $total,
                   detalles: $detalles, idempotencyKey: $idempotencyKey) {
          ${this.VENTA_FIELDS}
        }
      }`;
    return this.gql('crearVenta', query, { id_usuario, total, detalles, idempotencyKey });
  }

  eliminarVenta(id: string | number): Observable<boolean> {
    return this.gql('eliminarVenta', `mutation($id: ID!) { eliminarVenta(id: $id) }`, { id });
  }

  /**
   * POST {BASE_URL}/graphql
   * { "query": "mutation ProcesarCompraNatural(...)", "variables": { texto, id_usuario } }
   * Retorna un string con la respuesta del agente.
   */
  procesarCompraNatural(texto: string, id_usuario?: number): Observable<string> {
    const query = `
      mutation ProcesarCompraNatural($texto: String!, $id_usuario: Int) {
        procesarCompraNatural(texto: $texto, id_usuario: $id_usuario)
      }`;
    return this.gql<string>('procesarCompraNatural', query, { texto, id_usuario });
  }

  // ======================================================
  // REPORTES
  // ======================================================

  /** Vía GraphQL. Con excel/pdf/word, el resultado trae "archivo" en base64. */
  generarReporteNatural(texto: string, formato: FormatoReporte = 'json'): Observable<ReporteJson> {
    const query = `
      mutation GenerarReporteNatural($texto: String!, $formato: String) {
        generarReporteNatural(texto: $texto, formato: $formato)
      }`;
    return this.gql<ReporteJson>('generarReporteNatural', query, { texto, formato });
  }

  /**
   * POST {BASE_URL}/api/reporte-natural
   * Body: { "texto": "...", "formato": "pdf" }
   * - formato json (o sin filas): devuelve { tipo: 'json', data }
   * - excel/pdf/word: devuelve { tipo: 'archivo', blob, nombre }
   */
  generarReporteRest(texto: string, formato: FormatoReporte = 'json'): Observable<ReporteRestResultado> {
    return this.http
      .post(REPORTE_REST_URL, { texto, formato }, {
        headers: this.headers(),
        observe: 'response',
        responseType: 'blob',
      })
      .pipe(
        switchMap((res: HttpResponse<Blob>) => {
          const blob = res.body as Blob;
          const contentType = res.headers.get('Content-Type') || '';

          // JSON (reporte, mensaje o error): el blob se lee como texto
          if (contentType.includes('application/json')) {
            return from(
              blob.text().then((t) => ({ tipo: 'json', data: JSON.parse(t) } as ReporteRestResultado))
            );
          }

          // Archivo binario
          const cd = res.headers.get('Content-Disposition') || '';
          const nombre = /filename="?([^";]+)"?/.exec(cd)?.[1] ?? `reporte.${formato}`;
          return from([{ tipo: 'archivo', blob, nombre } as ReporteRestResultado]);
        })
      );
  }

  // ======================================================
  // UTILIDADES DE DESCARGA
  // ======================================================

  /** Descarga el "archivo" base64 que devuelve generarReporteNatural (GraphQL). */
  descargarBase64(a: ArchivoReporte) {
    const link = document.createElement('a');
    link.href = `data:${a.mime};base64,${a.base64}`;
    link.download = a.nombre;
    link.click();
  }

  /** Descarga el blob que devuelve generarReporteRest. */
  descargarBlob(blob: Blob, nombre: string) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = nombre;
    link.click();
    URL.revokeObjectURL(url);
  }
}