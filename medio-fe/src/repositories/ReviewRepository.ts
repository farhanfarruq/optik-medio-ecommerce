import { apiClient } from '../core/api/axiosclient';

export interface Review {
  id: number;
  rating: number;
  comment: string | null;
  user_name: string;
  created_at: string;
}

export interface ReviewSummary {
  avg_rating: number;
  total_reviews: number;
  reviews: Review[];
}

class ReviewRepository {
  async getProductReviews(slug: string): Promise<ReviewSummary> {
    const { data } = await apiClient.get(`/products/${slug}/reviews`);
    return data;
  }

  async submitReview(orderItemId: number, rating: number, comment: string): Promise<void> {
    await apiClient.post('/reviews', {
      order_item_id: orderItemId,
      rating,
      comment,
    });
  }

  async deleteReview(reviewId: number): Promise<void> {
    await apiClient.delete(`/reviews/${reviewId}`);
  }
}

export const reviewRepository = new ReviewRepository();
