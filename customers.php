<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdmin();

$page_title = 'Müşteri Yönetimi';

// Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

// Müşteri durumu güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (verifyCSRFToken($csrf_token)) {
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $action = $_POST['action'];
        
        switch ($action) {
            case 'update_status':
                $new_status = sanitizeInput($_POST['status'] ?? '');
                if (in_array($new_status, ['active', 'inactive', 'pending'])) {
                    $query = "UPDATE users SET status = ? WHERE id = ? AND role = 'customer'";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$new_status, $customer_id])) {
                        $_SESSION['success_message'] = 'Müşteri durumu güncellendi!';
                        
                        // Müşteriye bildirim gönder
                        $status_labels = [
                            'active' => 'Aktif',
                            'inactive' => 'Pasif',
                            'pending' => 'Beklemede'
                        ];
                        
                        addNotification(
                            $customer_id, 
                            'Hesap Durumu Güncellendi', 
                            "Hesap durumunuz: {$status_labels[$new_status]}", 
                            $new_status == 'active' ? 'success' : 'warning'
                        );
                        
                        logActivity($_SESSION['user_id'], 'customer_status_updated', "Müşteri durumu güncellendi: ID {$customer_id}");
                    }
                }
                break;
        }
    }
    
    header('Location: customers.php');
    exit();
}

// Filtreleme
$status_filter = sanitizeInput($_GET['status'] ?? '');
$search = sanitizeInput($_GET['search'] ?? '');

// Sayfalama
$page = intval($_GET['page'] ?? 1);
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Sorgu koşulları
$where_conditions = ["role = 'customer'"];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(company_name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR username LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Toplam müşteri sayısı
$count_query = "SELECT COUNT(*) as total FROM users WHERE {$where_clause}";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_customers = $stmt->fetch()['total'];

// Sayfalama bilgileri
$pagination = paginate($total_customers, $page, $limit);

// Müşterileri getir
$query = "SELECT u.*, 
                 COUNT(o.id) as total_orders,
                 SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END) as total_spent,
                 MAX(o.created_at) as last_order_date
          FROM users u 
          LEFT JOIN orders o ON u.id = o.customer_id 
          WHERE {$where_clause} 
          GROUP BY u.id
          ORDER BY u.created_at DESC 
          LIMIT {$limit} OFFSET {$offset}";
$stmt = $db->prepare($query);
$stmt->execute($params);
$customers = $stmt->fetchAll();

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
                    <a class="nav-link" href="orders.php">
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
                    <a class="nav-link active" href="customers.php">
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
                        <h2>Müşteri Yönetimi</h2>
                        <p class="text-muted"><?php echo number_format($total_customers); ?> müşteri bulundu</p>
                    </div>
                </div>

                <!-- Filtreler -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Durum</label>
                                    <select class="form-select" name="status">
                                        <option value="">Tüm Durumlar</option>
                                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Pasif</option>
                                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Beklemede</option>
                                    </select>
                                </div>
                                <div class="col-md-7 mb-3">
                                    <label class="form-label">Arama</label>
                                    <input type="text" class="form-control" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Şirket adı, kişi adı, e-posta...">
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

                <!-- Müşteri Listesi -->
                <?php if (empty($customers)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-users fa-4x text-muted mb-4"></i>
                            <h4>Müşteri Bulunamadı</h4>
                            <p class="text-muted">Filtrelere uygun müşteri bulunamadı.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Şirket / Kişi</th>
                                            <th>İletişim</th>
                                            <th>Kayıt Tarihi</th>
                                            <th>Sipariş İstatistikleri</th>
                                            <th>Durum</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($customers as $customer): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($customer['company_name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($customer['contact_person']); ?></small>
                                                        <br><small class="text-muted">@<?php echo htmlspecialchars($customer['username']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <i class="fas fa-envelope me-1"></i>
                                                        <small><?php echo htmlspecialchars($customer['email']); ?></small>
                                                        <?php if ($customer['phone']): ?>
                                                            <br><i class="fas fa-phone me-1"></i>
                                                            <small><?php echo htmlspecialchars($customer['phone']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php echo formatDate($customer['created_at']); ?>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo number_format($customer['total_orders']); ?></strong> sipariş
                                                        <br><small class="text-success"><?php echo formatPrice($customer['total_spent'] ?: 0); ?> toplam</small>
                                                        <?php if ($customer['last_order_date']): ?>
                                                            <br><small class="text-muted">Son: <?php echo formatDate($customer['last_order_date']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                                                        <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                                                            <option value="active" <?php echo $customer['status'] == 'active' ? 'selected' : ''; ?>>Aktif</option>
                                                            <option value="inactive" <?php echo $customer['status'] == 'inactive' ? 'selected' : ''; ?>>Pasif</option>
                                                            <option value="pending" <?php echo $customer['status'] == 'pending' ? 'selected' : ''; ?>>Beklemede</option>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#customerDetailModal"
                                                                onclick="showCustomerDetail(<?php echo htmlspecialchars(json_encode($customer)); ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <a href="orders.php?customer=<?php echo $customer['id']; ?>" 
                                                           class="btn btn-sm btn-outline-success" title="Siparişleri Görüntüle">
                                                            <i class="fas fa-shopping-cart"></i>
                                                        </a>
                                                    </div>
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
                        <nav aria-label="Müşteri sayfalama" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $pagination['total_pages']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
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

<!-- Müşteri Detay Modal -->
<div class="modal fade" id="customerDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Müşteri Detayları</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="customerDetailContent">
                <!-- İçerik JavaScript ile doldurulacak -->
            </div>
        </div>
    </div>
</div>

<script>
function showCustomerDetail(customer) {
    const content = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-primary">Şirket Bilgileri</h6>
                <table class="table table-borderless table-sm">
                    <tr><td><strong>Şirket Adı:</strong></td><td>${customer.company_name || '-'}</td></tr>
                    <tr><td><strong>İletişim Kişisi:</strong></td><td>${customer.contact_person || '-'}</td></tr>
                    <tr><td><strong>Vergi Numarası:</strong></td><td>${customer.tax_number || '-'}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-primary">İletişim Bilgileri</h6>
                <table class="table table-borderless table-sm">
                    <tr><td><strong>E-posta:</strong></td><td>${customer.email}</td></tr>
                    <tr><td><strong>Telefon:</strong></td><td>${customer.phone || '-'}</td></tr>
                    <tr><td><strong>Kullanıcı Adı:</strong></td><td>${customer.username}</td></tr>
                </table>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <h6 class="text-primary">Adres</h6>
                <p class="bg-light p-3 rounded">${customer.address || 'Adres bilgisi girilmemiş'}</p>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4>${customer.total_orders}</h4>
                        <small>Toplam Sipariş</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h4>${parseFloat(customer.total_spent || 0).toLocaleString('tr-TR', {style: 'currency', currency: 'TRY'})}</h4>
                        <small>Toplam Harcama</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h4>${customer.status === 'active' ? 'Aktif' : customer.status === 'inactive' ? 'Pasif' : 'Beklemede'}</h4>
                        <small>Hesap Durumu</small>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('customerDetailContent').innerHTML = content;
}
</script>

<?php include '../includes/footer.php'; ?>