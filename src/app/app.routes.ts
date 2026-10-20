import { Routes } from '@angular/router';
import { authGuard } from './core/auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () => import('./login/login.page').then((m) => m.LoginPage),
  },
  {
    path: 'register',
    loadComponent: () => import('./register/register.page').then((m) => m.RegisterPage),
  },
  {
    path: 'inventory',
    canActivate: [authGuard],
    loadComponent: () => import('./inventory/inventory.page').then((m) => m.InventoryPage),
  },
  {
    path: 'inventory/history',
    canActivate: [authGuard],
    loadComponent: () => import('./inventory-history/inventory-history.page').then((m) => m.InventoryHistoryPage),
  },
  {
    path: 'home',
    redirectTo: 'inventory',
    pathMatch: 'full',
  },
  {
    path: '',
    redirectTo: 'login',
    pathMatch: 'full',
  },
  {
    path: '**',
    redirectTo: 'login',
  },
];
