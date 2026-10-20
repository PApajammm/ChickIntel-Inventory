export interface InventoryItem {
  id: number;
  name: string;
  category: string;
  quantity: number;
  unit: string;
  unitPrice?: number;
  expirationDate?: string;
  notes?: string;
  referenceQuantity: number;
}

export type InventoryAction = 'Item added' | 'Item restocked' | 'Item updated' | 'Item deleted';

export interface InventoryHistoryEntry {
  id: number;
  itemName: string;
  action: InventoryAction;
  quantityChange?: number;
  createdAt: Date | string;
  notes?: string;
}

export type StockLevel = 'Depleted' | 'Critical' | 'Medium' | 'Normal';
export type ExpirationState = 'Expired' | 'Expiring soon' | null;

export interface InventoryItemInput {
  name: string;
  category: string;
  quantity: number;
  unit: string;
  unitPrice?: number;
  expirationDate?: string;
  notes?: string;
}
