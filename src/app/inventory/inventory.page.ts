import { CommonModule } from '@angular/common';
import { Component, computed, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import {
  IonButton,
  IonContent,
  IonHeader,
  IonIcon,
  IonInput,
  IonSelect,
  IonSelectOption,
  IonToolbar,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import {
  addOutline,
  alertCircleOutline,
  arrowBackOutline,
  checkmarkCircleOutline,
  chevronDownOutline,
  closeOutline,
  createOutline,
  cubeOutline,
  ellipsisHorizontal,
  filterOutline,
  leafOutline,
  medicalOutline,
  removeOutline,
  searchOutline,
  timeOutline,
  trashOutline,
} from 'ionicons/icons';
import { AuthService } from '../core/auth.service';
import { apiErrorMessage } from '../core/api.service';
import type { ExpirationState, InventoryItem, InventoryItemInput, StockLevel } from './inventory.models';
import { InventoryService } from './inventory.service';
import {
  calculateStockPercentage,
  expirationStateForDate,
  parseInventoryDate,
  stockLevelForPercentage,
} from './inventory.utils';

type DialogMode = 'add' | 'edit' | 'restock' | null;

interface InventoryForm {
  name: string;
  category: string;
  quantity: number | null;
  unit: string;
  unitPrice: number | null;
  expirationDate: string;
  notes: string;
}

const EMPTY_FORM: InventoryForm = {
  name: '',
  category: 'Equipment',
  quantity: 0,
  unit: 'pcs',
  unitPrice: null,
  expirationDate: '',
  notes: '',
};

@Component({
  selector: 'app-inventory',
  standalone: true,
  templateUrl: './inventory.page.html',
  styleUrl: './inventory.page.scss',
  imports: [
    CommonModule,
    FormsModule,
    RouterLink,
    IonButton,
    IonContent,
    IonHeader,
    IonIcon,
    IonInput,
    IonSelect,
    IonSelectOption,
    IonToolbar,
  ],
})
export class InventoryPage {
  readonly inventory = inject(InventoryService);
  readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  readonly categories = computed(() => [
    'All items',
    ...new Set([
      'Equipment',
      'Feeds',
      'Medicine',
      'Vitamins',
      ...this.inventory.items().map((item) => item.category),
    ]),
  ]);
  readonly expiredItems = computed(() =>
    this.inventory.items().filter((item) => this.expirationState(item) === 'Expired'),
  );
  readonly soonItems = computed(() =>
    this.inventory.items().filter((item) => this.expirationState(item) === 'Expiring soon'),
  );
  readonly healthyCount = computed(() =>
    this.inventory.items().filter((item) => this.stockLevel(item) === 'Normal').length,
  );
  readonly attentionCount = computed(() =>
    this.inventory.items().filter((item) => {
      const level = this.stockLevel(item);
      return level === 'Critical' || level === 'Depleted';
    }).length,
  );

  searchTerm = '';
  selectedCategory = 'All items';
  dialogMode: DialogMode = null;
  selectedItem: InventoryItem | null = null;
  form: InventoryForm = { ...EMPTY_FORM };
  restockQuantity: number | null = null;
  restockNotes = '';
  formError = '';

  constructor() {
    addIcons({
      addOutline,
      alertCircleOutline,
      arrowBackOutline,
      checkmarkCircleOutline,
      chevronDownOutline,
      closeOutline,
      createOutline,
      cubeOutline,
      ellipsisHorizontal,
      filterOutline,
      leafOutline,
      medicalOutline,
      removeOutline,
      searchOutline,
      timeOutline,
      trashOutline,
    });
  }

  ionViewWillEnter(): void {
    this.inventory.startSync();
  }

  ionViewWillLeave(): void {
    this.inventory.stopSync();
  }

  get filteredItems(): InventoryItem[] {
    const query = this.searchTerm.trim().toLocaleLowerCase();
    return this.inventory.items().filter((item) => {
      const matchesCategory = this.selectedCategory === 'All items' || item.category === this.selectedCategory;
      const searchableText = [
        item.name,
        item.category,
        item.unit,
        item.notes ?? '',
        item.expirationDate ?? '',
      ]
        .join(' ')
        .toLocaleLowerCase();
      return matchesCategory && (!query || searchableText.includes(query));
    });
  }

  setCategory(category: string): void {
    this.selectedCategory = category;
  }

  openAddDialog(): void {
    this.selectedItem = null;
    this.form = { ...EMPTY_FORM };
    this.formError = '';
    this.dialogMode = 'add';
  }

  openEditDialog(item: InventoryItem): void {
    this.selectedItem = item;
    this.form = {
      name: item.name,
      category: item.category,
      quantity: item.quantity,
      unit: item.unit,
      unitPrice: item.unitPrice ?? null,
      expirationDate: item.expirationDate ?? '',
      notes: item.notes ?? '',
    };
    this.formError = '';
    this.dialogMode = 'edit';
  }

  openRestockDialog(item: InventoryItem): void {
    this.selectedItem = item;
    this.restockQuantity = null;
    this.restockNotes = '';
    this.formError = '';
    this.dialogMode = 'restock';
  }

  closeDialog(): void {
    this.dialogMode = null;
    this.selectedItem = null;
    this.formError = '';
  }

  saveItem(): void {
    const quantity = Number(this.form.quantity);
    const unitPrice = this.form.unitPrice === null || this.form.unitPrice === undefined
      ? undefined
      : Number(this.form.unitPrice);
    if (!this.form.name.trim() || !this.form.category || !this.form.unit.trim()) {
      this.formError = 'Add an item name, category, and unit to continue.';
      return;
    }
    if (this.form.quantity === null || this.form.quantity === undefined || !Number.isFinite(quantity) || quantity < 0 || (unitPrice !== undefined && (!Number.isFinite(unitPrice) || unitPrice < 0))) {
      this.formError = 'Quantity and unit price must be valid non-negative numbers.';
      return;
    }

    const input: InventoryItemInput = {
      name: this.form.name.trim(),
      category: this.form.category,
      quantity,
      unit: this.form.unit.trim(),
      unitPrice,
      expirationDate: this.form.expirationDate || undefined,
      notes: this.form.notes.trim() || undefined,
    };

    const request = this.dialogMode === 'add'
      ? this.inventory.addItem(input)
      : this.dialogMode === 'edit' && this.selectedItem
        ? this.inventory.updateItem(this.selectedItem.id, input)
        : null;
    request?.subscribe({
      next: () => this.closeDialog(),
      error: (error: unknown) => {
        this.formError = apiErrorMessage(error, 'Could not save this item.');
      },
    });
  }

  saveRestock(): void {
    const quantity = Number(this.restockQuantity);
    if (!Number.isFinite(quantity) || quantity <= 0) {
      this.formError = 'Enter a restock quantity greater than zero.';
      return;
    }
    if (this.selectedItem) {
      this.inventory.restockItem(this.selectedItem.id, quantity, this.restockNotes.trim() || undefined).subscribe({
        next: () => this.closeDialog(),
        error: (error: unknown) => {
          this.formError = apiErrorMessage(error, 'Could not restock this item.');
        },
      });
    }
  }

  deleteItem(item: InventoryItem): void {
    const confirmed = window.confirm(`Delete "${item.name}" from inventory? This action cannot be undone.`);
    if (confirmed) {
      this.inventory.deleteItem(item.id).subscribe({
        error: (error: unknown) => this.inventory.loadError.set(apiErrorMessage(error, 'Could not delete this item.')),
      });
    }
  }

  signOut(): void {
    this.auth.logout().subscribe({
      next: () => void this.router.navigateByUrl('/login'),
      error: (error: unknown) => this.inventory.loadError.set(apiErrorMessage(error, 'Could not sign out. Try again.')),
    });
  }

  stockPercentage(item: InventoryItem): number {
    return calculateStockPercentage(item.quantity, item.referenceQuantity);
  }

  stockLevel(item: InventoryItem): StockLevel {
    return stockLevelForPercentage(this.stockPercentage(item));
  }

  expirationState(item: InventoryItem): ExpirationState {
    return expirationStateForDate(item.expirationDate);
  }

  formatDate(date: string): string {
    return new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', year: 'numeric' }).format(
      parseInventoryDate(date),
    );
  }

  categoryIcon(category: string): string {
    if (category === 'Medicine') return 'medical-outline';
    if (category === 'Feeds' || category === 'Vitamins' || category === 'Bedding') return 'leaf-outline';
    return 'cube-outline';
  }
}
