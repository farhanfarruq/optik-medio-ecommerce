/**
 * useSeoMeta — composable untuk set dynamic meta tags per halaman.
 * Menggunakan DOM manipulation langsung karena project ini SPA (Vue Router).
 * Untuk SSR/SSG, ganti dengan @unhead/vue atau @vueuse/head.
 */

interface SeoMetaOptions {
  title?: string;
  description?: string;
  ogTitle?: string;
  ogDescription?: string;
  ogImage?: string;
  ogType?: string;
  ogUrl?: string;
  twitterTitle?: string;
  twitterDescription?: string;
  twitterImage?: string;
  canonicalUrl?: string;
  noindex?: boolean;
}

const APP_NAME = 'Optik Medio';
const DEFAULT_DESCRIPTION = 'Optik Medio menghadirkan eyewear premium, lensa kurasi, dan pengalaman belanja optik yang rapi untuk pelanggan Indonesia.';
const DEFAULT_IMAGE = '/gambar/medio.jpeg';

function setMetaTag(name: string, content: string, property = false): void {
  const attr = property ? 'property' : 'name';
  let el = document.querySelector(`meta[${attr}="${name}"]`) as HTMLMetaElement | null;
  if (!el) {
    el = document.createElement('meta');
    el.setAttribute(attr, name);
    document.head.appendChild(el);
  }
  el.setAttribute('content', content);
}

function setLinkTag(rel: string, href: string): void {
  let el = document.querySelector(`link[rel="${rel}"]`) as HTMLLinkElement | null;
  if (!el) {
    el = document.createElement('link');
    el.setAttribute('rel', rel);
    document.head.appendChild(el);
  }
  el.setAttribute('href', href);
}

export function useSeoMeta() {
  function setSeo(options: SeoMetaOptions): void {
    const title = options.title
      ? `${options.title} | ${APP_NAME}`
      : `${APP_NAME} | Curated Lens Experience`;

    const description = options.description || DEFAULT_DESCRIPTION;
    const ogImage = options.ogImage || DEFAULT_IMAGE;
    const ogTitle = options.ogTitle || options.title || APP_NAME;
    const ogDescription = options.ogDescription || description;
    const currentUrl = options.ogUrl || window.location.href;

    // Page title
    document.title = title;

    // Standard meta
    setMetaTag('description', description);

    // Open Graph
    setMetaTag('og:title', ogTitle, true);
    setMetaTag('og:description', ogDescription, true);
    setMetaTag('og:image', ogImage, true);
    setMetaTag('og:type', options.ogType || 'website', true);
    setMetaTag('og:url', currentUrl, true);
    setMetaTag('og:site_name', APP_NAME, true);

    // Twitter Card
    setMetaTag('twitter:card', 'summary_large_image');
    setMetaTag('twitter:title', options.twitterTitle || ogTitle);
    setMetaTag('twitter:description', options.twitterDescription || ogDescription);
    setMetaTag('twitter:image', options.twitterImage || ogImage);

    // Canonical
    if (options.canonicalUrl) {
      setLinkTag('canonical', options.canonicalUrl);
    }

    // Robots
    if (options.noindex) {
      setMetaTag('robots', 'noindex, nofollow');
    } else {
      setMetaTag('robots', 'index, follow');
    }
  }

  /**
   * Inject JSON-LD structured data ke <head>.
   * Hapus script lama sebelum inject yang baru.
   */
  function setJsonLd(data: Record<string, unknown>): void {
    // Hapus JSON-LD lama
    const existing = document.querySelector('script[type="application/ld+json"][data-dynamic]');
    if (existing) existing.remove();

    const script = document.createElement('script');
    script.type = 'application/ld+json';
    script.setAttribute('data-dynamic', 'true');
    script.textContent = JSON.stringify(data);
    document.head.appendChild(script);
  }

  /**
   * Buat JSON-LD Product schema dari data produk.
   */
  function buildProductJsonLd(product: {
    name: string;
    description?: string;
    slug: string;
    price: number;
    stock: number;
    brand?: string;
    sku?: string;
    gtin?: string;
    images?: string[];
    rating?: number;
    reviewCount?: number;
  }): Record<string, unknown> {
    const frontendUrl = window.location.origin;
    const imageUrl = product.images?.[0]
      ? (product.images[0].startsWith('http') ? product.images[0] : `${frontendUrl}/storage/${product.images[0]}`)
      : `${frontendUrl}/gambar/medio.jpeg`;

    const schema: Record<string, unknown> = {
      '@context': 'https://schema.org',
      '@type': 'Product',
      name: product.name,
      description: product.description || product.name,
      url: `${frontendUrl}/products/${product.slug}`,
      image: imageUrl,
      brand: {
        '@type': 'Brand',
        name: product.brand || APP_NAME,
      },
      offers: {
        '@type': 'Offer',
        priceCurrency: 'IDR',
        price: product.price,
        availability: product.stock > 0
          ? 'https://schema.org/InStock'
          : 'https://schema.org/OutOfStock',
        url: `${frontendUrl}/products/${product.slug}`,
        seller: {
          '@type': 'Organization',
          name: APP_NAME,
        },
      },
    };

    if (product.sku) {
      schema['sku'] = product.sku;
    }
    if (product.gtin) {
      schema['gtin'] = product.gtin;
    }
    if (product.rating && product.reviewCount) {
      schema['aggregateRating'] = {
        '@type': 'AggregateRating',
        ratingValue: product.rating,
        reviewCount: product.reviewCount,
      };
    }

    return schema;
  }

  /**
   * Buat JSON-LD BreadcrumbList schema.
   */
  function buildBreadcrumbJsonLd(items: Array<{ name: string; url: string }>): Record<string, unknown> {
    return {
      '@context': 'https://schema.org',
      '@type': 'BreadcrumbList',
      itemListElement: items.map((item, index) => ({
        '@type': 'ListItem',
        position: index + 1,
        name: item.name,
        item: item.url,
      })),
    };
  }

  /**
   * Reset ke default (untuk halaman yang tidak set SEO).
   */
  function resetSeo(): void {
    setSeo({});
    const existing = document.querySelector('script[type="application/ld+json"][data-dynamic]');
    if (existing) existing.remove();
  }

  return {
    setSeo,
    setJsonLd,
    buildProductJsonLd,
    buildBreadcrumbJsonLd,
    resetSeo,
  };
}
