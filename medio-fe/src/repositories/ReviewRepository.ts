import { apiClient } from '../core/api/axiosclient';

export interface Review {
  id: number;
  rating: number;
  comment: string | null;
  user_name: string;
  created_at: string;
  user?: {
    id?: number;
    name?: string | null;
  } | null;
}

export interface ReviewSummary {
  avg_rating: number;
  total_reviews: number;
  reviews: Review[];
}

class ReviewRepository {
  async getProductReviews(slug: string): Promise<ReviewSummary> {
    const { data } = await apiClient.get(`/products/${slug}/reviews`);
    const reviews = Array.isArray(data?.reviews)
      ? data.reviews
      : Array.isArray(data?.data)
        ? data.data
        : Array.isArray(data)
          ? data
          : [];

    const normalizedReviews = reviews.map((review: any) => ({
      ...review,
      user_name: review?.user_name || review?.user?.name || 'Pengguna',
    }));

    return {
      avg_rating: Number(data?.avg_rating ?? data?.average_rating ?? 0),
      total_reviews: Number(data?.total_reviews ?? data?.total ?? data?.meta?.total ?? normalizedReviews.length),
      reviews: normalizedReviews,
    };
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
