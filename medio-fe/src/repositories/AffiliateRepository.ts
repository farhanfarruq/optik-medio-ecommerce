import { apiClient } from '../core/api/axiosclient';

export interface AffiliateProfile {
  id: number;
  affiliate_code: string;
  status: 'pending' | 'approved' | 'rejected' | 'suspended';
  commission_rate_percentage: string | number;
  notes?: string | null;
  referrals_count?: number;
  payout_method?: 'bank_transfer' | string | null;
  payout_bank_name?: string | null;
  payout_account_number?: string | null;
  payout_account_name?: string | null;
  payout_notes?: string | null;
}

export interface AffiliateSummary {
  referrals_count: number;
  total_requests: number;
  total_earned: number;
  available_balance: number;
  locked_balance: number;
  paid_out: number;
  total_success: number;
  total_pending: number;
  total_processing: number;
  total_cancelled: number;
  eligible_orders_count: number;
  available_orders_count: number;
  minimum_payout_amount: number;
}

export interface AffiliateCommission {
  id: number;
  request_no: string;
  requested_amount: number;
  approved_amount?: number | null;
  status: 'pending' | 'processing' | 'success' | 'cancelled';
  requested_at?: string | null;
  processed_at?: string | null;
  admin_notes?: string | null;
  payout_method?: string | null;
  payout_bank_name?: string | null;
  payout_account_number?: string | null;
  payout_account_name?: string | null;
}

export interface AffiliateEarning {
  order_id: number;
  order_number: string;
  customer_name: string;
  status: string;
  created_at?: string | null;
  delivered_at?: string | null;
  base_amount: number;
  commission_rate_percentage: number;
  total_commission: number;
  claimed_commission: number;
  remaining_commission: number;
  is_available_for_payout: boolean;
}

class AffiliateRepository {
  async getDashboard(): Promise<{ profile: AffiliateProfile | null; summary: AffiliateSummary }> {
    const { data } = await apiClient.get('/affiliate/dashboard');
    return data.data;
  }

  async apply(): Promise<AffiliateProfile> {
    const { data } = await apiClient.post('/affiliate/apply');
    return data.data;
  }

  async getCommissions(page = 1): Promise<{
    data: AffiliateCommission[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
  }> {
    const { data } = await apiClient.get('/affiliate/commissions', { params: { page } });
    return data.data;
  }

  async getEarnings(): Promise<AffiliateEarning[]> {
    const { data } = await apiClient.get('/affiliate/earnings');
    return data.data;
  }

  async updatePayoutProfile(payload: {
    payout_method: 'bank_transfer';
    payout_bank_name: string;
    payout_account_number: string;
    payout_account_name: string;
    payout_notes?: string;
  }): Promise<AffiliateProfile> {
    const { data } = await apiClient.patch('/affiliate/payout-profile', payload);
    return data.data;
  }

  async requestPayout(requestedAmount: number, adminNotes?: string): Promise<AffiliateCommission> {
    const { data } = await apiClient.post('/affiliate/commissions/request', {
      requested_amount: requestedAmount,
      admin_notes: adminNotes,
    });
    return data.data;
  }
}

export const affiliateRepository = new AffiliateRepository();
