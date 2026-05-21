# API Resources

P1-8 (Phase 3) audit menemukan: folder ini sebelumnya **tidak ada**, dan controller API mengembalikan response sebagai array manual / Eloquent toArray() langsung.

## Status Audit

Saat audit Phase 3:
- **Controllers dengan `response()->json($model)` langsung:** banyak (~80% dari 27 endpoint group)
- **Controllers yang sudah pakai Resource class:** 0
- **Folder `app/Http/Resources/`:** baru dibuat di Phase 3

## Pattern yang Direkomendasikan

```php
// app/Http/Resources/ProductResource.php
class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'slug'  => $this->slug,
            'price' => (float) $this->price,
            'stock' => $this->stock,
            'images' => ProductImageResource::collection($this->whenLoaded('productImages')),
        ];
    }
}

// Di controller
return ProductResource::make($product);
return ProductResource::collection($products);
```

## Manfaat

1. **Versioning:** ubah shape response di 1 tempat tanpa perlu modify controller
2. **Hindari leak:** kontrol field mana yang di-expose ke client
3. **Consistency:** field naming + casting konsisten antar endpoint
4. **Lazy load awareness:** `whenLoaded()` mencegah N+1

## Action Plan (Phase berikutnya)

Belum di-execute di Phase 3 karena scope refactor lain (Action classes, Form Requests, frontend components) sudah cukup besar untuk 1 sprint. Direkomendasikan di-extract bertahap di Phase 4-5:

1. **Sprint 1 Phase 4/5:** ProductResource, CategoryResource, OrderResource, CartResource, AppointmentResource (5 yang paling sering dipakai)
2. **Sprint 2:** ArticleResource, ReviewResource, AffiliateResource, PrescriptionResource, ShippingResource
3. **Sprint 3+:** sisanya bertahap

Rule of thumb: setiap kali edit controller, kalau response dibentuk manual lebih dari 5 baris → ekstrak ke Resource.
