<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'Teclado mecanico RGB', 'sku' => 'KB-RGB-001', 'price' => 79.90, 'stock' => 40, 'description' => 'Teclado mecanico retroiluminado con switches rojos y reposamuñecas magnetico.'],
            ['name' => 'Mouse inalambrico ergonomico', 'sku' => 'MS-ERG-002', 'price' => 34.50, 'stock' => 75, 'description' => 'Mouse Bluetooth de 6 botones, 4000 DPI ajustables y bateria recargable.'],
            ['name' => 'Monitor 27" QHD 165Hz', 'sku' => 'MON-27Q-003', 'price' => 289.99, 'stock' => 18, 'description' => 'Panel IPS 2560x1440, 165Hz, 1ms, FreeSync y HDR10.'],
            ['name' => 'Auriculares over-ear con cancelacion de ruido', 'sku' => 'AUD-ANC-004', 'price' => 129.00, 'stock' => 30, 'description' => 'Cancelacion activa de ruido, 30h de autonomia y modo transparencia.'],
            ['name' => 'Webcam Full HD 1080p', 'sku' => 'CAM-1080-005', 'price' => 45.00, 'stock' => 55, 'description' => 'Camara 1080p a 60fps con microfono estereo y correccion de luz automatica.'],
            ['name' => 'SSD NVMe 1TB Gen4', 'sku' => 'SSD-1TB-006', 'price' => 99.99, 'stock' => 60, 'description' => 'Unidad NVMe PCIe 4.0, lectura hasta 7000 MB/s.'],
            ['name' => 'Silla ergonomica de oficina', 'sku' => 'CHR-ERG-007', 'price' => 199.00, 'stock' => 12, 'description' => 'Soporte lumbar ajustable, reposabrazos 4D y malla transpirable.'],
            ['name' => 'Hub USB-C 8 en 1', 'sku' => 'HUB-8IN1-008', 'price' => 39.90, 'stock' => 80, 'description' => 'HDMI 4K, 2x USB-A, USB-C PD 100W, lector SD/microSD y Ethernet.'],
            ['name' => 'Microfono USB de condensador', 'sku' => 'MIC-USB-009', 'price' => 69.00, 'stock' => 25, 'description' => 'Patron cardioide, monitoreo sin latencia y soporte antivibracion.'],
            ['name' => 'Router WiFi 6 AX3000', 'sku' => 'RTR-AX3K-010', 'price' => 89.99, 'stock' => 22, 'description' => 'Doble banda, MU-MIMO, OFDMA y 4 antenas de alta ganancia.'],
            ['name' => 'Base refrigerante para laptop', 'sku' => 'CLR-LAP-011', 'price' => 24.99, 'stock' => 65, 'description' => 'Cinco ventiladores silenciosos, altura ajustable y doble puerto USB.'],
            ['name' => 'Cargador GaN 65W', 'sku' => 'CHG-GAN65-012', 'price' => 29.99, 'stock' => 90, 'description' => 'Tecnologia GaN, 2x USB-C + 1x USB-A, carga rapida PD y PPS.'],
            ['name' => 'Tarjeta grafica edicion compacta', 'sku' => 'GPU-CMP-013', 'price' => 449.00, 'stock' => 0, 'description' => 'Diseño ITX de doble ventilador, ideal para builds pequeños. (Agotado)'],
        ];

        foreach ($products as $data) {
            Product::updateOrCreate(
                ['sku' => $data['sku']],
                array_merge($data, [
                    'slug' => Str::slug($data['name']),
                    'image_url' => 'https://picsum.photos/seed/'.Str::slug($data['sku']).'/600/600',
                    'is_active' => ($data['stock'] ?? 0) > 0,
                ])
            );
        }

        // Algunos productos extra generados aleatoriamente.
        Product::factory()->count(7)->create();
    }
}
