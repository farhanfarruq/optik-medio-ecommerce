export const resolveImageUrl = (imageOrProduct: any, productName: string = '') => {
  const getBaseStorageUrl = () => {
    const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
    return apiUrl.replace('/api', '') + '/storage/';
  };

  const normalizeImage = (image: any): any => {
    if (Array.isArray(image)) {
      return image[0];
    }

    if (image && typeof image === 'object') {
      return image.url || image.image_url || image.image_path || image.path || image[0];
    }

    return image;
  };

  let image = imageOrProduct;
  let name = productName;

  // If a product object was passed instead of a string
  if (imageOrProduct && typeof imageOrProduct === 'object' && !Array.isArray(imageOrProduct)) {
    if (imageOrProduct.image_url) {
      image = imageOrProduct.image_url;
    } else if (imageOrProduct.resolved_images) {
      image = imageOrProduct.resolved_images;
    } else if (imageOrProduct.images) {
      image = imageOrProduct.images;
    }
    if (!name && imageOrProduct.name) {
      name = imageOrProduct.name;
    }
  }

  if (!name) name = 'Product';

  // If it's already a full URL
  if (typeof image === 'string' && image.startsWith('http')) {
    // Check if it's via.placeholder.com and replace it (it's often unstable)
    if (image.includes('via.placeholder.com')) {
        return `https://placehold.co/600x600?text=${encodeURIComponent(name)}`;
    }
    return image;
  }

  // Handle array/JSON from Filament
  let imagePath = normalizeImage(image);
  if (typeof image === 'string' && image.startsWith('[')) {
    try {
      const parsed = JSON.parse(image);
      if (Array.isArray(parsed)) imagePath = normalizeImage(parsed);
    } catch (e) {}
  }

  if (imagePath && typeof imagePath === 'string') {
    if (imagePath.startsWith('http')) {
      if (imagePath.includes('via.placeholder.com')) {
          return `https://placehold.co/600x600?text=${encodeURIComponent(name)}`;
      }
      return imagePath;
    }
    return getBaseStorageUrl() + imagePath;
  }

  return `https://placehold.co/600x600?text=${encodeURIComponent(name)}`;
};
