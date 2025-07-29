<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdmin();

$page_title = 'Raporlar';

// Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

// Tarih filtreleri
$date_from = sanitizeInput($_GET['date_from'] ?? date('Y-m-01'));
$date_to = sanitizeInput($_GET['date_to'] ?? date('Y-m-d'));

// Genel istatistikler
$stats = [];

// Sipariş istatistikleri
$query = "SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_order_value,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as completed_orders,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders
          FROM orders 
          WHERE DATE(created_at) BETWEEN ? AND ?";
$stmt = $db->prepare($query);
$stmt->execute([$date_from, $date_to]);
$stats['orders'] = $stmt->fetch();

// Ödeme istatistikleri
$query = "SELECT 
            COUNT(*) as total_payments,
            SUM(amount) as total_amount,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_payments,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payments
          FROM payments 
          WHERE DATE(created_at) BETWEEN ? AND ?";
$stmt = $db->prepare($query);
$stmt->execute([$date_from, $date_to]);
$stats['payments'] = $stmt->fetch();

// Müşteri istatistikleri
$query = "SELECT 
            COUNT(*) as total_customers,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_customers,
            COUNT(CASE WHEN DATE(created_at) BETWEEN ? AND ? THEN 1 END) as new_customers
          FROM users 
          WHERE role = 'customer'";
$stmt = $db->prepare($query);
$stmt->execute([$date_from, $date_to]);
$stats['customers'] = $stmt->fetch();

// Ürün istatistikleri
$query = "SELECT 
            COUNT(*) as total_products,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_products,
            COUNT(CASE WHEN stock_quantity <= min_stock_level THEN 1 END) as low_stock_products
          FROM products";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['products'] = $stmt->fetch();

// En çok satan ürünler
$query = "SELECT p.name, p.sku, SUM(oi.quantity) as total_sold, SUM(oi.total_price) as total_revenue
          FROM order_items oi
          JOIN products p ON oi.product_id = p.id
          JOIN orders o ON oi.order_id = o.id
          WHERE DATE(o.created_at) BETWEEN ? AND ?
          GROUP BY p.id
          ORDER BY total_sold DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$date_from, $date_to]);
$top_products = $stmt->fetchAll();

// En iyi müşteriler
$query = "SELECT u.company_name, u.contact_person, COUNT(o.id) as order_count, SUM(o.total_amount) as total_spent
          FROM users u
          JOIN orders o ON u.id = o.customer_id
          WHERE DATE(o.created_at) BETWEEN ? AND ?
          GROUP BY u.id
          ORDER BY total_spent DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$date_from, $date_to]);
$top_customers = $stmt->fetchAll();

// Aylık satış trendi
$query = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as order_count,
            SUM(total_amount) as revenue
          FROM orders
          WHERE DATE(created_at) BETWEEN DATE_SUB(?, INTERVAL 11 MONTH) AND ?
          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
          ORDER BY month";
$stmt = $db->prepare($query);
$stmt->execute([$date_to, $date_to]);
$monthly_sales = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="p-4">
                <!-- Başlık -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2>Raporlar</h2>
                        <p class="text-muted"><?php echo formatDate($date_from); ?> - <?php echo formatDate($date_to); ?> dönemi</p>
                    </div>
                </div>

                <!-- Tarih Filtreleri -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Başlangıç Tarihi</label>
                                    <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Bitiş Tarihi</label>
                                    <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-chart-bar me-2"></i>Rapor Oluştur
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Genel İstatistikler -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-shopping-cart fa-2x mb-3"></i>
                                <h3><?php echo number_format($stats['orders']['total_orders']); ?></h3>
                                <p class="mb-0">Toplam Sipariş</p>
                                <small><?php echo number_format($stats['orders']['completed_orders']); ?> tamamlandı</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-lira-sign fa-2x mb-3"></i>
                                <h3><?php echo formatPrice($stats['orders']['total_revenue'] ?: 0); ?></h3>
                                <p class="mb-0">Toplam Gelir</p>
                                <small>Ort: <?php echo formatPrice($stats['orders']['avg_order_value'] ?: 0); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-3"></i>
                                <h3><?php echo number_format($stats['customers']['total_customers']); ?></h3>
                                <p class="mb-0">Toplam Müşteri</p>
                                <small><?php echo number_format($stats['customers']['new_customers']); ?> yeni</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-box fa-2x mb-3"></i>
                                <h3><?php echo number_format($stats['products']['active_products']); ?></h3>
                                <p class="mb-0">Aktif Ürün</p>
                                <small><?php echo number_format($stats['products']['low_stock_products']); ?> düşük stok</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- En Çok Satan Ürünler -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-trophy me-2"></i>
                                    En Çok Satan Ürünler
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($top_products)): ?>
                                    <p class="text-muted text-center">Bu dönemde satış yok</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Ürün</th>
                                                    <th>Satılan</th>
                                                    <th>Gelir</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($top_products as $product): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($product['sku']); ?></small>
                                                        </td>
                                                        <td><?php echo number_format($product['total_sold']); ?></td>
                                                        <td><?php echo formatPrice($product['total_revenue']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- En İyi Müşteriler -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-star me-2"></i>
                                    En İyi Müşteriler
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($top_customers)): ?>
                                    <p class="text-muted text-center">Bu dönemde sipariş yok</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Müşteri</th>
                                                    <th>Sipariş</th>
                                                    <th>Harcama</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($top_customers as $customer): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($customer['company_name']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($customer['contact_person']); ?></small>
                                                        </td>
                                                        <td><?php echo number_format($customer['order_count']); ?></td>
                                                        <td><?php echo formatPrice($customer['total_spent']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aylık Satış Trendi -->
                <?php if (!empty($monthly_sales)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>
                                Aylık Satış Trendi
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Ay</th>
                                            <th>Sipariş Sayısı</th>
                                            <th>Gelir</th>
                                            <th>Ortalama Sipariş</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($monthly_sales as $month): ?>
                                            <tr>
                                                <td><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></td>
                                                <td><?php echo number_format($month['order_count']); ?></td>
                                                <td><?php echo formatPrice($month['revenue']); ?></td>
                                                <td><?php echo formatPrice($month['order_count'] > 0 ? $month['revenue'] / $month['order_count'] : 0); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>