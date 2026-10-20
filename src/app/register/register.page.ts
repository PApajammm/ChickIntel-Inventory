import { Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { IonButton, IonContent, IonIcon, IonInput } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { arrowForwardOutline, eyeOffOutline, eyeOutline, leafOutline, lockClosedOutline, mailOutline, personOutline } from 'ionicons/icons';
import { AuthService } from '../core/auth.service';
import { apiErrorMessage } from '../core/api.service';

@Component({
  selector: 'app-register',
  standalone: true,
  templateUrl: './register.page.html',
  styleUrl: './register.page.scss',
  imports: [FormsModule, RouterLink, IonButton, IonContent, IonIcon, IonInput],
})
export class RegisterPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  fullName = '';
  email = '';
  password = '';
  confirmPassword = '';
  message = '';
  formError = '';
  loading = false;
  passwordVisible = false;
  confirmPasswordVisible = false;

  constructor() {
    addIcons({ arrowForwardOutline, eyeOffOutline, eyeOutline, leafOutline, lockClosedOutline, mailOutline, personOutline });
  }

  togglePasswordVisibility(field: 'password' | 'confirmPassword'): void {
    if (field === 'password') {
      this.passwordVisible = !this.passwordVisible;
      return;
    }
    this.confirmPasswordVisible = !this.confirmPasswordVisible;
  }

  submitRegistration(): void {
    this.formError = '';
    this.message = '';
    if (this.password !== this.confirmPassword) {
      this.formError = 'The passwords do not match. Check both fields and try again.';
      return;
    }
    this.loading = true;
    this.auth.register(this.fullName.trim(), this.email.trim(), this.password).subscribe({
      next: () => void this.router.navigateByUrl('/inventory'),
      error: (error: unknown) => {
        this.formError = apiErrorMessage(error, 'Unable to create your account. Please try again.');
        this.loading = false;
      },
      complete: () => {
        this.loading = false;
      },
    });
  }
}
