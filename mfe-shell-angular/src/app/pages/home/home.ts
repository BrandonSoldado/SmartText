import { Component, signal } from '@angular/core';
import { LoginModal } from '../auth/login-modal';
import { RegisterModal } from '../auth/register-modal';

@Component({
  selector: 'app-home',
  imports: [LoginModal, RegisterModal],
  templateUrl: './home.html',
  styleUrl: './home.css',
})
export class Home {
  activeModal = signal<'login' | 'register' | null>(null);
}