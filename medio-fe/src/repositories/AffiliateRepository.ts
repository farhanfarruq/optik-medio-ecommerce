import { apiClient } from '../core/api/axiosclient';

export interface AffiliateProfile {
  id: number;
  affiliate_code: string;
  status: 'pending' | 'approved' | 'rejected' | 'suspended';
  commission_rate_percentage: string | number;
  notes?: string | null;
  referrals_count?: number;
}

export interface AffiliateSummary {
  referrals_count: number;
  total_requests: number;
  total_success: number;
  total_pending: number;
  total_processing: number;
  total_cancelled: number;
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

  async requestPayout(requestedAmount: number, adminNotes?: string): Promise<AffiliateCommission> {
    const { data } = await apiClient.post('/affiliate/commissions/request', {
      requested_amount: requestedAmount,
      admin_notes: adminNotes,
    });
    return data.data;
  }
}

export const affiliateRepository = new AffiliateRepository();
