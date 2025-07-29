<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdmin();

$page_title = 'Yönetici Paneli';

// Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

// İstatistikler
$stats = [];

// Gerçek zamanlı istatistikler
$stats['total_orders'] = getTotalOrders();
$stats['monthly_orders'] = getMonthlyOrders();
$stats['monthly_revenue'] = getTotalRevenue();
$stats['pending_payments'] = getPendingPayments();

// Diğer istatistikler
if ($db) {
    // Toplam müşteri sayısı
    $query = "SELECT COUNT(*) as total FROM users WHERE role = 'customer' AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total_customers'] = $result ? $result['total'] : 0;
    
    // Toplam ürün sayısı
    $query = "SELECT COUNT(*) as total FROM products WHERE status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total_products'] = $result ? $result['total'] : 0;
} else {
    $stats['total_customers'] = 0;
    $stats['total_products'] = 0;
}

// Son siparişler
$query = "SELECT o.*, u.company_name, u.contact_person FROM orders o 
          LEFT JOIN users u ON o.customer_id = u.id 
          ORDER BY o.created_at DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_orders = $stmt->fetchAll();

// Düşük stoklu ürünler
$query = "SELECT * FROM products WHERE stock_quantity <= min_stock_level AND status = 'active' ORDER BY stock_quantity ASC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$low_stock_products = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="p-4">
                <!-- Başlık -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2>Yönetici Paneli</h2>
                        <p class="text-muted">İsova Ambalaj B2B Yönetim Sistemi</p>
                    </div>
                    <div>
                        <span class="badge bg-success">Sistem Aktif</span>
                    </div>
                </div>

                <!-- İstatistik Kartları -->
                <div class="row mb-4">
                    <div class="col-md-4 col-lg-2 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-shopping-cart fa-2x mb-3"></i>
                                <h3><?php echo number_format($stats['total_orders']); ?></h3>
                                <p class="mb-0">Toplam Sipariş</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-2 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-alt fa-2x mb-3"></i>
                                <h3><?php echo number_format($stats['monthly_orders']); ?></h3>
                                <p class="mb-0">Bu Ay</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-2 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-3"></i>
                                <h3><?php echo number_format($stats['total_customers']); ?></h3>
                                <p class="mb-0">Müşteri</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-2 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-box fa-2x mb-3"></i>
                                <h3><?php echo number_format($stats['total_products']); ?></h3>
                                <p class="mb-0">Ürün</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-2 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-lira-sign fa-2x mb-3"></i>
                                <h3><?php echo formatPrice($stats['monthly_revenue']); ?></h3>
                                <p class="mb-0">Aylık Gelir</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-2 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x mb-3"></i>
                                <h3><?php echo formatPrice($stats['pending_payments']); ?></h3>
                                <p class="mb-0">Bekleyen</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Son Siparişler -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-list-alt me-2"></i>
                                    Son Siparişler
                                </h5>
                                <a href="orders.php" class="btn btn-sm btn-outline-primary">Tümünü Gör</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($recent_orders)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Henüz sipariş yok</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Sipariş No</th>
                                                    <th>Müşteri</th>
                                                    <th>Tutar</th>
                                                    <th>Durum</th>
                                                    <th>Tarih</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_orders as $order): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                                        </td>
                                                        <td>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($order['company_name']); ?></strong>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($order['contact_person']); ?></small>
                                                            </div>
                                                        </td>
                                                        <td><?php echo formatPrice($order['total_amount']); ?></td>
                                                        <td>
                                                            <span class="badge order-status-<?php echo $order['status']; ?>">
                                                                <?php
                                                                $status_labels = [
                                                                    'pending' => 'Beklemede',
                                                                    'processing' => 'İşlemde',
                                                                    'shipped' => 'Kargoda',
                                                                    'delivered' => 'Teslim Edildi',
                                                                    'cancelled' => 'İptal'
                                                                ];
                                                                echo $status_labels[$order['status']] ?? $order['status'];
                                                                ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo formatDate($order['created_at']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Düşük Stoklu Ürünler -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                                    Düşük Stok
                                </h5>
                                <a href="products.php" class="btn btn-sm btn-outline-primary">Tümünü Gör</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($low_stock_products)): ?>
                                    <div class="text-center py-3">
                                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                        <p class="text-muted mb-0">Tüm ürünler yeterli stokta</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($low_stock_products as $product): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                <small class="text-muted">SKU: <?php echo htmlspecialchars($product['sku']); ?></small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-warning text-dark"><?php echo $product['stock_quantity']; ?></span>
                                                <br><small class="text-muted">Min: <?php echo $product['min_stock_level']; ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hızlı İşlemler -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-bolt me-2"></i>
                                    Hızlı İşlemler
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-2 mb-3">
                                        <a href="products.php?action=add" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-plus d-block mb-2"></i>
                                            Ürün Ekle
                                        </a>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <a href="categories.php?action=add" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-tags d-block mb-2"></i>
                                            Kategori Ekle
                                        </a>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <a href="orders.php?status=pending" class="btn btn-outline-warning w-100">
                                            <i class="fas fa-clock d-block mb-2"></i>
                                            Bekleyen Siparişler
                                        </a>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <a href="payments.php?status=pending" class="btn btn-outline-danger w-100">
                                            <i class="fas fa-credit-card d-block mb-2"></i>
                                            Bekleyen Ödemeler
                                        </a>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <a href="reports.php" class="btn btn-outline-info w-100">
                                            <i class="fas fa-chart-bar d-block mb-2"></i>
                                            Raporlar
                                        </a>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <a href="settings.php" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-cog d-block mb-2"></i>
                                            Ayarlar
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>