# Laravel ZMQ Artisan Commands

Package Laravel ZMQ menyediakan beberapa Artisan command yang berguna untuk testing, monitoring, dan menjalankan server ZMQ.

## Daftar Commands

### 1. `zmq:test` - Testing ZMQ Connections

Command ini digunakan untuk testing koneksi ZMQ dan broadcasting.

#### Usage:
```bash
# Test PUB/SUB publishing
php artisan zmq:test --connection=publish --message="Hello World"

# Test PUB/SUB subscribing (timeout 10 detik)
php artisan zmq:test --connection=subscribe --timeout=10

# Test DEALER client
php artisan zmq:test --connection=dealer --message="Test request"

# Test DEALER server (timeout 30 detik)
php artisan zmq:test --connection=dealer --dealer-server --timeout=30

# Test ROUTER client
php artisan zmq:test --connection=router --message="Test request"

# Test ROUTER server (timeout 30 detik)
php artisan zmq:test --connection=router --router-server --timeout=30
```

#### Options:
- `--connection`: Jenis koneksi (publish, subscribe, dealer, router)
- `--message`: Pesan yang akan dikirim
- `--dealer-server`: Jalankan sebagai DEALER server
- `--router-server`: Jalankan sebagai ROUTER server
- `--timeout`: Timeout dalam detik (default: 5)

### 2. `zmq:server` - Run ZMQ Server

Command ini digunakan untuk menjalankan server ZMQ yang berjalan terus menerus.

#### Usage:
```bash
# Jalankan DEALER server
php artisan zmq:server dealer

# Jalankan ROUTER server dengan timeout 60 detik
php artisan zmq:server router --timeout=60

# Jalankan SUBSCRIBE server dengan verbose output
php artisan zmq:server subscribe --verbose

# Jalankan DEALER server dengan koneksi custom
php artisan zmq:server dealer --connection=worker1
```

#### Options:
- `type`: Jenis server (dealer, router, subscribe)
- `--connection`: Nama koneksi dari config (default: sama dengan type)
- `--timeout`: Timeout dalam detik (0 = infinite, default: 0)
- `--verbose`: Output verbose

### 3. `zmq:monitor` - Monitor ZMQ Connections

Command ini digunakan untuk monitoring status koneksi ZMQ.

#### Usage:
```bash
# Monitor semua koneksi
php artisan zmq:monitor

# Monitor koneksi tertentu
php artisan zmq:monitor --connection=dealer

# Output dalam format JSON
php artisan zmq:monitor --format=json

# Output dalam format CSV
php artisan zmq:monitor --format=csv

# Watch mode (monitoring berkelanjutan)
php artisan zmq:monitor --watch

# Watch mode dengan format JSON
php artisan zmq:monitor --watch --format=json
```

#### Options:
- `--connection`: Koneksi tertentu yang akan dimonitor
- `--format`: Format output (table, json, csv, default: table)
- `--watch`: Mode watch (monitoring berkelanjutan)

## Contoh Penggunaan Lengkap

### Testing PUB/SUB Pattern

```bash
# Terminal 1: Jalankan SUBSCRIBE server
php artisan zmq:server subscribe --timeout=30

# Terminal 2: Test publishing
php artisan zmq:test --connection=publish --message="Hello from Laravel!"
```

### Testing DEALER/ROUTER Pattern

```bash
# Terminal 1: Jalankan ROUTER server
php artisan zmq:server router --timeout=30

# Terminal 2: Test DEALER client
php artisan zmq:test --connection=dealer --message="Process this data"
```

### Monitoring Production Environment

```bash
# Monitor semua koneksi dalam format JSON
php artisan zmq:monitor --format=json

# Watch mode untuk monitoring berkelanjutan
php artisan zmq:monitor --watch --format=table
```

## Konfigurasi untuk Testing

Pastikan konfigurasi ZMQ sudah benar di `config/zmq.php`:

```php
'connections' => [
    'publish' => [
        'dsn'       => 'tcp://127.0.0.1:5555',
        'method'    => \ZMQ::SOCKET_PUB,
    ],

    'subscribe' => [
        'dsn'    => 'tcp://0.0.0.0:5555',
        'method'    => \ZMQ::SOCKET_SUB,
    ],

    'dealer' => [
        'dsn'       => 'tcp://127.0.0.1:5556',
        'method'    => \ZMQ::SOCKET_DEALER,
        'action'    => 'connect',
        'identity'  => null,
        'linger'    => 0,
    ],

    'router' => [
        'dsn'       => 'tcp://0.0.0.0:5556',
        'method'    => \ZMQ::SOCKET_ROUTER,
        'action'    => 'bind',
        'linger'    => 0,
    ],
],
```

## Troubleshooting

### Command Tidak Ditemukan
Jika command tidak ditemukan, pastikan:
1. Package sudah terinstall dengan benar
2. Service provider sudah terdaftar
3. Cache sudah di-clear: `php artisan config:clear`

### Koneksi Gagal
Jika koneksi gagal:
1. Periksa konfigurasi DSN di `config/zmq.php`
2. Pastikan port tidak digunakan aplikasi lain
3. Periksa firewall settings
4. Gunakan `zmq:monitor` untuk melihat status koneksi

### Server Tidak Merespon
Jika server tidak merespon:
1. Periksa apakah server sudah berjalan
2. Gunakan `--verbose` untuk melihat detail
3. Periksa timeout settings
4. Pastikan client dan server menggunakan port yang sama

## Advanced Usage

### Custom Server Processing

Anda bisa memodifikasi command `ZmqServerCommand` untuk menambahkan custom processing logic:

```php
// Di src/Console/Commands/ZmqServerCommand.php
protected function processRequest($request, $type)
{
    $data = json_decode($request, true);
    
    // Custom processing logic
    switch ($data['action'] ?? 'unknown') {
        case 'custom_action':
            return json_encode([
                'status' => 'success',
                'result' => 'Custom processing completed'
            ]);
        // ... other cases
    }
}
```

### Multiple Server Instances

Anda bisa menjalankan multiple server instances dengan konfigurasi berbeda:

```bash
# Server 1
php artisan zmq:server dealer --connection=worker1

# Server 2  
php artisan zmq:server dealer --connection=worker2

# Router untuk load balancing
php artisan zmq:server router --connection=router
```

### Integration dengan Queue

Command ini bisa diintegrasikan dengan Laravel Queue untuk background processing:

```php
// Di Job class
public function handle()
{
    // Process job
    $result = $this->processData();
    
    // Send result via ZMQ
    $dealer = app('zmq.connection.dealer');
    $socket = $dealer->connect();
    $socket->send('job_result', \ZMQ::MODE_SNDMORE);
    $socket->send(json_encode($result));
}
``` 