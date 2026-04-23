import json
import requests
import time
from slugify import slugify

def scrape_melawai_sunglasses():
    base_url = "https://api.optikmelawai.com/api/v2/products"
    
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Accept': 'application/json',
        'Origin': 'https://optikmelawai.com',
        'Referer': 'https://optikmelawai.com/'
    }

    all_products = []
    current_page = 1
    last_page = 1 

    print("🚀 Memulai proses pengumpulan data Kacamata Hitam (Sunglasses)...")

    while current_page <= last_page:
        # ====================================================================
        # PARAMETER SAKTI UNTUK SUNGLASSES BEST SELLER
        # ====================================================================
        params = {
            'page': current_page,
            'limit': 20,
            'product_section_slug': 'sunglasses',
            'product_type_slug': 'single-product',
            'is_bestseller': 'true',
            'slug': 'best-seller-sunglasses'
        }

        print(f"📦 Mengambil Halaman {current_page} dari {last_page if last_page > 1 else '...'} ", end="\r")
        
        try:
            response = requests.get(base_url, headers=headers, params=params, timeout=20)
            response.raise_for_status()
            res_json = response.json()

            # 1. Update total halaman dari data API yang asli
            if current_page == 1:
                last_page = res_json['data']['last_page']
                total_data = res_json['data']['total']
                print(f"\n📊 Total terdeteksi: {total_data} kacamata hitam dalam {last_page} halaman.")

            # 2. Ambil list produk di halaman ini
            items = res_json['data']['data']
            
            if not items:
                break

            for item in items:
                product_tags = [tag['name'] for tag in item.get('tags', [])]
                
                product_data = {
                    "id": item.get('id'),
                    "name": item.get('name'),
                    "slug": item.get('slug'),
                    "sku": item.get('sku'),
                    "brand": item.get('brand', {}).get('name') if item.get('brand') else "Unknown",
                    "price_original": item.get('price', 0),
                    "price_final": item.get('final_price', 0),
                    "image_url": item.get('image', {}).get('file', ''),
                    "is_new": item.get('is_new', False),
                    "is_bestseller": item.get('is_best_seller', False),
                    "tags": product_tags,
                    "stock_status": {
                        "raya": item.get('stock_melawai_raya', 0),
                        "gudang": item.get('stock_jabodetabek_gudang', 0)
                    }
                }
                all_products.append(product_data)

            # 3. Lanjut ke halaman berikutnya
            current_page += 1
            
            # JEDA: Agar IP tidak diblokir oleh server
            time.sleep(0.5) 

            # Backup otomatis setiap 5 halaman
            if current_page % 5 == 0:
                with open("backup_sunglasses.json", "w", encoding="utf-8") as f:
                    json.dump(all_products, f, ensure_ascii=False, indent=4)

        except requests.exceptions.RequestException as e:
            print(f"\n❌ Error koneksi di halaman {current_page}: {e}")
            print("Menyimpan data yang sudah didapat...")
            break
        except Exception as e:
            print(f"\n❌ Error sistem di halaman {current_page}: {e}")
            break

    return all_products

if __name__ == "__main__":
    start_time = time.time()
    
    hasil = scrape_melawai_sunglasses()
    
    if hasil:
        file_name = "data_sunglasses.json"
        with open(file_name, "w", encoding="utf-8") as f:
            json.dump(hasil, f, ensure_ascii=False, indent=4)
        
        end_time = time.time()
        durasi = (end_time - start_time) / 60
        print(f"\n\n🎉 SELESAI!")
        print(f"✅ Berhasil mengambil {len(hasil)} produk Kacamata Hitam.")
        print(f"📂 Data disimpan di: {file_name}")
        print(f"⏱️ Waktu total proses: {durasi:.2f} menit")
    else:
        print("\n⚠️ Gagal mengambil data.")