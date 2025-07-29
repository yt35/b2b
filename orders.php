<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdmin();

$page_title = 'Sipariş Yönetimi';

// Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

// Sipariş durumu güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (verifyCSRFToken($csrf_token)) {
        $order_id = intval($_POST['order_id'] ?? 0);
        $action = $_POST['action'];
        
        switch ($action) {
            case 'update_status':
                $new_status = sanitizeInput($_POST['status'] ?? '');
                if (in_array($new_status, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])) {
                    $query = "UPDATE orders SET status = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$new_status, $order_id])) {
                        $_SESSION['success_message'] = 'Sipariş durumu güncellendi!';
                        
                        // Müşteriye bildirim gönder
                        $query = "SELECT customer_id, order_number FROM orders WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$order_id]);
                        $order = $stmt->fetch();
                        
                        if ($order) {
                            $status_labels = [
                                'pending' => 'Beklemede',
                                'processing' => 'İşlemde',
                                'shipped' => 'Kargoda',
                                'delivered' => 'Teslim Edildi',
                                'cancelled' => 'İptal Edildi'
                            ];
                            
                            addNotification(
                                $order['customer_id'], 
                                'Sipariş Durumu Güncellendi', 
                                "Sipariş numaranız {$order['order_number']} durumu: {$status_labels[$new_status]}", 
                                'info'
                            );
                        }
                    }
                }
                break;
                
            case 'update_payment':
                $payment_status = sanitizeInput($_POST['payment_status'] ?? '');
                if (in_array($payment_status, ['pending', 'paid', 'partial', 'cancelled'])) {
                    $query = "UPDATE orders SET payment_status = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$payment_status, $order_id])) {
                        $_SESSION['success_message'] = 'Ödeme durumu güncellendi!';
                    }
                }
                break;
        }
    }
    
    header('Location: orders.php');
    exit();
}

// Filtreleme
$status_filter = sanitizeInput($_GET['status'] ?? '');
$payment_filter = sanitizeInput($_GET['payment'] ?? '');
$search = sanitizeInput($_GET['search'] ?? '');

// Sayfalama
$page = intval($_GET['page'] ?? 1);
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Sorgu koşulları
$where_conditions = ["1=1"];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if (!empty($payment_filter)) {
    $where_conditions[] = "o.payment_status = ?";
    $params[] = $payment_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(o.order_number LIKE ? OR u.company_name LIKE ? OR u.contact_person LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Toplam sipariş sayısı
$count_query = "SELECT COUNT(*) as total FROM orders o 
                LEFT JOIN users u ON o.customer_id = u.id 
                WHERE {$where_clause}";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_orders = $stmt->fetch()['total'];

// Sayfalama bilgileri
$pagination = paginate($total_orders, $page, $limit);

// Siparişleri getir
$query = "SELECT o.*, u.company_name, u.contact_person, u.phone, u.email 
          FROM orders o 
          LEFT JOIN users u ON o.customer_id = u.id 
          WHERE {$where_clause} 
          ORDER BY o.created_at DESC 
          LIMIT {$limit} OFFSET {$offset}";
$stmt = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 px-0">
            <div class="sidebar">
                <div class="p-3">
                    <h6 class="text-muted">YÖNETİCİ PANELİ</h6>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-tachometer-alt"></i>
                        Ana Sayfa
                    </a>
                    <a class="nav-link active" href="orders.php">
                        <i class="fas fa-shopping-cart"></i>
                        Siparişler
                    </a>
                    <a class="nav-link" href="products.php">
                        <i class="fas fa-box"></i>
                        Ürünler
                    </a>
                    <a class="nav-link" href="categories.php">
                        <i class="fas fa-tags"></i>
                        Kategoriler
                    </a>
                    <a class="nav-link" href="customers.php">
                        <i class="fas fa-users"></i>
                        Müşteriler
                    </a>
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-credit-card"></i>
                        Ödemeler
                    </a>
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar"></i>
                        Raporlar
                    </a>
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i>
                        Ayarlar
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
                        <h2>Sipariş Yönetimi</h2>
                        <p class="text-muted"><?php echo number_format($total_orders); ?> sipariş bulundu</p>
                    </div>
                </div>

                <!-- Filtreler -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Sipariş Durumu</label>
                                    <select class="form-select" name="status">
                                        <option value="">Tüm Durumlar</option>
                                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Beklemede</option>
                                        <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>İşlemde</option>
                                        <option value="shipped" <?php echo $status_filter == 'shipped' ? 'selected' : ''; ?>>Kargoda</option>
                                        <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Teslim Edildi</option>
                                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>İptal</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Ödeme Durumu</label>
                                    <select class="form-select" name="payment">
                                        <option value="">Tüm Durumlar</option>
                                        <option value="pending" <?php echo $payment_filter == 'pending' ? 'selected' : ''; ?>>Beklemede</option>
                                        <option value="paid" <?php echo $payment_filter == 'paid' ? 'selected' : ''; ?>>Ödendi</option>
                                        <option value="partial" <?php echo $payment_filter == 'partial' ? 'selected' : ''; ?>>Kısmi</option>
                                        <option value="cancelled" <?php echo $payment_filter == 'cancelled' ? 'selected' : ''; ?>>İptal</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Arama</label>
                                    <input type="text" class="form-control" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Sipariş no, şirket adı...">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sipariş Listesi -->
                <?php if (empty($orders)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                            <h4>Sipariş Bulunamadı</h4>
                            <p class="text-muted">Filtrelere uygun sipariş bulunamadı.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Sipariş No</th>
                                            <th>Müşteri</th>
                                            <th>Tutar</th>
                                            <th>Sipariş Durumu</th>
                                            <th>Ödeme Durumu</th>
                                            <th>Tarih</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($order['company_name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($order['contact_person']); ?></small>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($order['phone']); ?></small>
                                                    </div>
                                                </td>
                                                <td><?php echo formatPrice($order['total_amount']); ?></td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                                                            <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Beklemede</option>
                                                            <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>İşlemde</option>
                                                            <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Kargoda</option>
                                                            <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Teslim Edildi</option>
                                                            <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>İptal</option>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="update_payment">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <select class="form-select form-select-sm" name="payment_status" onchange="this.form.submit()">
                                                            <option value="pending" <?php echo $order['payment_status'] == 'pending' ? 'selected' : ''; ?>>Beklemede</option>
                                                            <option value="paid" <?php echo $order['payment_status'] == 'paid' ? 'selected' : ''; ?>>Ödendi</option>
                                                            <option value="partial" <?php echo $order['payment_status'] == 'partial' ? 'selected' : ''; ?>>Kısmi</option>
                                                            <option value="cancelled" <?php echo $order['payment_status'] == 'cancelled' ? 'selected' : ''; ?>>İptal</option>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td><?php echo formatDate($order['created_at']); ?></td>
                                                <td>
                                                    <a href="../order-detail.php?id=<?php echo $order['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Detayları Görüntüle">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Sayfalama -->
                    <?php if ($pagination['total_pages'] > 1): ?>
                        <nav aria-label="Sipariş sayfalama" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&payment=<?php echo $payment_filter; ?>&search=<?php echo urlencode($search); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&payment=<?php echo $payment_filter; ?>&search=<?php echo urlencode($search); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $pagination['total_pages']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&payment=<?php echo $payment_filter; ?>&search=<?php echo urlencode($search); ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>