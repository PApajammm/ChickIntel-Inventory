import { Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { IonButton, IonContent, IonIcon, IonInput } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { arrowForwardOutline, leafOutline, lockClosedOutline, mailOutline } from 'ionicons/icons';
import { AuthService } from '../core/auth.service';
import { apiErrorMessage } from '../core/api.service';

@Component({
  selector: 'app-login',
  standalone: true,
  templateUrl: './login.page.html',
  styleUrl: './login.page.scss',
  imports: [FormsModule, RouterLink, IonButton, IonContent, IonIcon, IonInput],
})
export class LoginPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  email = '';
  password = '';
  message = '';
  loading = false;
  formError = '';

  constructor() {
    addIcons({ arrowForwardOutline, leafOutline, lockClosedOutline, mailOutline });
  }

  submitLogin(): void {
    this.loading = true;
    this.formError = '';
    this.message = '';
    this.auth.login(this.email.trim(), this.password).subscribe({
      next: () => {
        const returnUrl = this.route.snapshot.queryParamMap.get('returnUrl');
        const destination = returnUrl?.startsWith('/') && !returnUrl.startsWith('//')
          ? returnUrl
          : '/inventory';
        void this.router.navigateByUrl(destination);
      },
      error: (error: unknown) => {
        this.formError = apiErrorMessage(error, 'Unable to sign in. Please try again.');
        this.loading = false;
      },
      complete: () => {
        this.loading = false;
      },
    });
  }
}
