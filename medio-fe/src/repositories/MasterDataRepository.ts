import { apiClient } from '../core/api/axiosclient';

export interface BankAccount {
  id: number;
  name: string;
  account_name: string;
  account_number: string;
  branch?: string | null;
}

export interface PaymentMethodItem {
  id: number;
  name: string;
  code: string;
  type?: string | null;
  provider?: string | null;
  instructions?: string | null;
  requires_bank_selection: boolean;
}

export interface StoreStatus {
  is_closed: boolean;
  current_close: {
    id: number;
    start_at?: string | null;
    end_at?: string | null;
    reason?: string | null;
  } | null;
}

class MasterDataRepository {
  async getBanks(): Promise<BankAccount[]> {
    const { data } = await apiClient.get('/banks');
    return data.data || [];
  }

  async getPaymentMethods(): Promise<PaymentMethodItem[]> {
    const { data } = await apiClient.get('/payment-methods');
    return data.data || [];
  }

  async getStoreStatus(): Promise<StoreStatus> {
    const { data } = await apiClient.get('/store-status');
    return data.data;
  }
}

export const masterDataRepository = new MasterDataRepository();
