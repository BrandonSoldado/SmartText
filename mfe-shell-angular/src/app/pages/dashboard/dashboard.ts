import { Component, ElementRef, HostListener, inject, signal, viewChild } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';

interface SubItem {
  view: string;
  title: string;
  desc: string;
}

interface MenuItem {
  label: string;
  view?: string;         // link directo
  children?: SubItem[];  // dropdown (opcional, para más adelante)
}

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.css',
})
export class Dashboard {
  private auth = inject(AuthService);
  private router = inject(Router);

  private nav = viewChild<ElementRef<HTMLElement>>('nav');

  // Menú vacío de contenido por ahora: solo las opciones.
  // Para convertir una opción en dropdown, usa "children" en lugar de "view".
  readonly menu: MenuItem[] = [
    { label: 'Usuarios', view: 'usuarios' },
    { label: 'Roles y Permisos', view: 'roles-permisos' },
    { label: 'Ventas', view: 'ventas' },
    { label: 'Pagos', view: 'pagos' },
    { label: 'Inventario', view: 'inventario' },
    { label: 'Reportes', view: 'reportes' },
  ];

  activeView = signal<string>('');
  openDropdown = signal<string | null>(null);

  currentUser = this.auth.getCurrentUser();
  nombre = this.currentUser?.nombres || 'Usuario';
  email = this.currentUser?.email || '';
  inicial = this.nombre.charAt(0).toUpperCase();
  rolNombre = this.currentUser?.rol?.nombre || 'Sin rol';

  // Cerrar dropdowns al hacer click fuera
  @HostListener('document:mousedown', ['$event'])
  onDocumentClick(event: MouseEvent) {
    const el = this.nav()?.nativeElement;
    if (el && !el.contains(event.target as Node)) this.openDropdown.set(null);
  }

  isGroupActive(item: MenuItem): boolean {
    return item.children?.some((c) => c.view === this.activeView()) ?? false;
  }

  toggle(name: string) {
    this.openDropdown.update((prev) => (prev === name ? null : name));
  }

  select(view: string, e: Event) {
    e.preventDefault();
    e.stopPropagation();
    this.activeView.set(view);
    this.openDropdown.set(null);
  }

  logout() {
    this.auth.logout();
    this.router.navigate(['/']);
  }
}