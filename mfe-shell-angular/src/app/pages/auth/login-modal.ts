import { Component, inject, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-login-modal',
  imports: [FormsModule],
  templateUrl: './login-modal.html',
  styleUrl: './auth-modal.css',
})
export class LoginModal {
  private auth = inject(AuthService);
  private router = inject(Router);

  cerrar = output<void>();
  irARegistro = output<void>();

  email = '';
  password = '';
  error = signal<string | null>(null);
  loading = signal(false);

  onSubmit() {
    this.error.set(null);
    this.loading.set(true);

    this.auth.login(this.email, this.password).subscribe({
      next: () => {
        this.loading.set(false);
        this.cerrar.emit();
        this.router.navigate(['/dashboard']);
      },
      error: (e) => {
        this.error.set(e?.message || 'Error al iniciar sesión');
        this.loading.set(false);
      },
    });
  }
}