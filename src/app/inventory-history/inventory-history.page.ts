import { CommonModule } from '@angular/common';
import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonContent, IonHeader, IonIcon, IonToolbar } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { addOutline, arrowBackOutline, createOutline, leafOutline, timeOutline, trashOutline } from 'ionicons/icons';
import { InventoryService } from '../inventory/inventory.service';
import { InventoryAction } from '../inventory/inventory.models';

@Component({
  selector: 'app-inventory-history',
  standalone: true,
  templateUrl: './inventory-history.page.html',
  styleUrl: './inventory-history.page.scss',
  imports: [CommonModule, RouterLink, IonContent, IonHeader, IonIcon, IonToolbar],
})
export class InventoryHistoryPage {
  readonly inventory = inject(InventoryService);

  constructor() {
    addIcons({ addOutline, arrowBackOutline, createOutline, leafOutline, timeOutline, trashOutline });
  }

  ionViewWillEnter(): void {
    this.inventory.startSync();
  }

  ionViewWillLeave(): void {
    this.inventory.stopSync();
  }

  actionIcon(action: InventoryAction): string {
    if (action === 'Item restocked') return 'add-outline';
    if (action === 'Item updated') return 'create-outline';
    if (action === 'Item deleted') return 'trash-outline';
    return 'leaf-outline';
  }

  formatDate(date: Date | string): string {
    return new Intl.DateTimeFormat('en', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    }).format(new Date(date));
  }
}
