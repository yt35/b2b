<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

requireLogin();

$page_title = 'Panel';

// Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

// Kullanıcı rolüne göre yönlendirme
if (isAdmin()) {
    header('Location: admin/index.php');
    exit();
} elseif (isSupplier()) {
    header('Location: supplier/index.php');
    exit();
}

// Müşteri dashboard'u
$user_id = $_SESSION['user_id'];

// Müşteri istatistikleri
$stats = [];

// Gerçek zamanlı müşteri istatistikleri
$stats['total_orders'] = getTotalOrders($user_id);
$stats['monthly_orders'] = getMonthlyOrders($user_id);
$stats['total_spent'] = getTotalRevenue($user_id);
$stats['pending_payments'] = getPendingPayments($user_id);

// Son siparişler
$query = "SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$recent_orders = $stmt->fetchAll();

// Bildirimler
$query = "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 px-0">
            <div class="sidebar">
                <div class="p-3">
                    <h6 class="text-muted">MÜŞTERİ PANELİ</h6>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Ana Sayfa
                    </a>
                    <a class="nav-link" href="products.php">
                        <i class="fas fa-box"></i>
                        Ürünler
                    </a>
                    <a class="nav-link" href="cart.php">
                        <i class="fas fa-shopping-cart"></i>
                        Sepetim
                    </a>
                    <a class="nav-link" href="orders.php">
                        <i class="fas fa-list-alt"></i>
                        Siparişlerim
                    </a>
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-credit-card"></i>
                        Ödemelerim
                    </a>
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i>
                        Profilim
                    </a>
                </nav>
            </div>
        </div>

        <!-- Ana İçerik -->
        <div class="col-md-9 col-lg-10">
            <div class="p-4">
                <!-- Başlık -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2>Hoş Geldiniz, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
                        <p class="text-muted"><?php echo htmlspecialchars($_SESSION['company_name']); ?></p>
                    </div>
                    <div>
                        <a href="products.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>
                            Yeni Sipariş Ver
                        </a>
                    </div>
                </div>

                <!-- İstatistik Kartları -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-shopping-cart fa-2x mb-3"></i>
                                <h3><?php echo number_format($stats['total_orders']); ?></h3>
                                <p class="mb-0">Toplam Sipariş</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-alt fa-2x mb-3"></i>
                                <h3><?php echo number_format($stats['monthly_orders']); ?></h3>
                                <p class="mb-0">Bu Ay</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-lira-sign fa-2x mb-3"></i>
                                <h3><?php echo formatPrice($stats['total_spent']); ?></h3>
                                <p class="mb-0">Toplam Harcama</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x mb-3"></i>
                                <h3><?php echo formatPrice($stats['pending_payments']); ?></h3>
                                <p class="mb-0">Bekleyen Ödeme</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Son Siparişler -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-list-alt me-2"></i>
                                    Son Siparişler
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_orders)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Henüz sipariş vermediniz.</p>
                                        <a href="products.php" class="btn btn-primary">İlk Siparişinizi Verin</a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Sipariş No</th>
                                                    <th>Tarih</th>
                                                    <th>Tutar</th>
                                                    <th>Durum</th>
                                                    <th>İşlem</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_orders as $order): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                                        </td>
                                                        <td><?php echo formatDate($order['created_at']); ?></td>
                                                        <td><?php echo formatPrice($order['total_amount']); ?></td>
                                                        <td>
                                                            <span class="badge order-status-<?php echo $order['status']; ?>">
                                                                <?php
                                                                $status_labels = [
                                                                    'pending' => 'Beklemede',
                                                                    'processing' => 'İşlemde',
                                                                    'shipped' => 'Kargoda',
                                                                    'delivered' => 'Teslim Edildi',
                                                                    'cancelled' => 'İptal Edildi'
                                                                ];
                                                                echo $status_labels[$order['status']] ?? $order['status'];
                                                                ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="order-detail.php?id=<?php echo $order['id']; ?>" 
                                                               class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-center">
                                        <a href="orders.php" class="btn btn-outline-primary">
                                            Tüm Siparişleri Görüntüle
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Bildirimler -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-bell me-2"></i>
                                    Bildirimler
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($notifications)): ?>
                                    <div class="text-center py-3">
                                        <i class="fas fa-bell-slash fa-2x text-muted mb-2"></i>
                                        <p class="text-muted mb-0">Yeni bildirim yok</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="alert alert-<?php echo $notification['type']; ?> alert-dismissible fade show" role="alert">
                                            <strong><?php echo htmlspecialchars($notification['title']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($notification['message']); ?></small>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>