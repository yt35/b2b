<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdmin();

$page_title = 'Ödeme Yönetimi';

// Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

// Ödeme durumu güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (verifyCSRFToken($csrf_token)) {
        $payment_id = intval($_POST['payment_id'] ?? 0);
        $action = $_POST['action'];
        
        switch ($action) {
            case 'update_status':
                $new_status = sanitizeInput($_POST['status'] ?? '');
                if (in_array($new_status, ['pending', 'completed', 'failed', 'cancelled'])) {
                    $query = "UPDATE payments SET status = ?, payment_date = CASE WHEN ? = 'completed' THEN CURRENT_DATE ELSE payment_date END WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$new_status, $new_status, $payment_id])) {
                        $_SESSION['success_message'] = 'Ödeme durumu güncellendi!';
                        
                        // Sipariş durumunu da güncelle
                        if ($new_status == 'completed') {
                            $query = "UPDATE orders SET payment_status = 'paid' WHERE id = (SELECT order_id FROM payments WHERE id = ?)";
                            $stmt = $db->prepare($query);
                            $stmt->execute([$payment_id]);
                        }
                        
                        logActivity($_SESSION['user_id'], 'payment_status_updated', "Ödeme durumu güncellendi: ID {$payment_id}");
                    }
                }
                break;
        }
    }
    
    header('Location: payments.php');
    exit();
}

// Filtreleme
$status_filter = sanitizeInput($_GET['status'] ?? '');
$payment_method_filter = sanitizeInput($_GET['payment_method'] ?? '');
$search = sanitizeInput($_GET['search'] ?? '');

// Sayfalama
$page = intval($_GET['page'] ?? 1);
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Sorgu koşulları
$where_conditions = ["1=1"];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if (!empty($payment_method_filter)) {
    $where_conditions[] = "p.payment_method = ?";
    $params[] = $payment_method_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(o.order_number LIKE ? OR u.company_name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Toplam ödeme sayısı
$count_query = "SELECT COUNT(*) as total FROM payments p 
                LEFT JOIN orders o ON p.order_id = o.id 
                LEFT JOIN users u ON o.customer_id = u.id 
                WHERE {$where_clause}";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_payments = $stmt->fetch()['total'];

// Sayfalama bilgileri
$pagination = paginate($total_payments, $page, $limit);

// Ödemeleri getir
$query = "SELECT p.*, o.order_number, o.total_amount as order_total, u.company_name, u.contact_person 
          FROM payments p 
          LEFT JOIN orders o ON p.order_id = o.id 
          LEFT JOIN users u ON o.customer_id = u.id 
          WHERE {$where_clause} 
          ORDER BY p.created_at DESC 
          LIMIT {$limit} OFFSET {$offset}";
$stmt = $db->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Ödeme özeti
$summary_query = "SELECT 
                    SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as completed_total,
                    SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END) as pending_total,
                    COUNT(CASE WHEN p.status = 'pending' THEN 1 END) as pending_count,
                    COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as completed_count
                  FROM payments p";
$stmt = $db->prepare($summary_query);
$stmt->execute();
$summary = $stmt->fetch();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="p-4">
                <!-- Başlık -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2>Ödeme Yönetimi</h2>
                        <p class="text-muted"><?php echo number_format($total_payments); ?> ödeme kaydı bulundu</p>
                    </div>
                </div>

                <!-- Ödeme Özeti -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x mb-3"></i>
                                <h3><?php echo formatPrice($summary['completed_total'] ?: 0); ?></h3>
                                <p class="mb-0">Tamamlanan</p>
                                <small><?php echo $summary['completed_count']; ?> ödeme</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x mb-3"></i>
                                <h3><?php echo formatPrice($summary['pending_total'] ?: 0); ?></h3>
                                <p class="mb-0">Bekleyen</p>
                                <small><?php echo $summary['pending_count']; ?> ödeme</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-calculator fa-2x mb-3"></i>
                                <h3><?php echo formatPrice(($summary['completed_total'] ?: 0) + ($summary['pending_total'] ?: 0)); ?></h3>
                                <p class="mb-0">Toplam</p>
                                <small><?php echo $total_payments; ?> ödeme</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-percentage fa-2x mb-3"></i>
                                <h3><?php echo $total_payments > 0 ? round(($summary['completed_count'] / $total_payments) * 100, 1) : 0; ?>%</h3>
                                <p class="mb-0">Başarı Oranı</p>
                                <small>Tamamlanan ödemeler</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtreler -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Ödeme Durumu</label>
                                    <select class="form-select" name="status">
                                        <option value="">Tüm Durumlar</option>
                                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Beklemede</option>
                                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                                        <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Başarısız</option>
                                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>İptal</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Ödeme Yöntemi</label>
                                    <select class="form-select" name="payment_method">
                                        <option value="">Tüm Yöntemler</option>
                                        <option value="cash" <?php echo $payment_method_filter == 'cash' ? 'selected' : ''; ?>>Nakit</option>
                                        <option value="card" <?php echo $payment_method_filter == 'card' ? 'selected' : ''; ?>>Kredi Kartı</option>
                                        <option value="bank_transfer" <?php echo $payment_method_filter == 'bank_transfer' ? 'selected' : ''; ?>>Banka Havalesi</option>
                                        <option value="check" <?php echo $payment_method_filter == 'check' ? 'selected' : ''; ?>>Çek</option>
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

                <!-- Ödeme Listesi -->
                <?php if (empty($payments)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-credit-card fa-4x text-muted mb-4"></i>
                            <h4>Ödeme Bulunamadı</h4>
                            <p class="text-muted">Filtrelere uygun ödeme bulunamadı.</p>
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
                                            <th>Ödeme Yöntemi</th>
                                            <th>Tutar</th>
                                            <th>Durum</th>
                                            <th>Ödeme Tarihi</th>
                                            <th>Vade Tarihi</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($payment['order_number']); ?></strong>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($payment['company_name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($payment['contact_person']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $payment_methods = [
                                                        'cash' => '<i class="fas fa-money-bill-wave me-1"></i>Nakit',
                                                        'card' => '<i class="fas fa-credit-card me-1"></i>Kredi Kartı',
                                                        'bank_transfer' => '<i class="fas fa-university me-1"></i>Banka Havalesi',
                                                        'check' => '<i class="fas fa-file-invoice me-1"></i>Çek'
                                                    ];
                                                    echo $payment_methods[$payment['payment_method']] ?? $payment['payment_method'];
                                                    ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo formatPrice($payment['amount']); ?></strong>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                        <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                                                            <option value="pending" <?php echo $payment['status'] == 'pending' ? 'selected' : ''; ?>>Beklemede</option>
                                                            <option value="completed" <?php echo $payment['status'] == 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                                                            <option value="failed" <?php echo $payment['status'] == 'failed' ? 'selected' : ''; ?>>Başarısız</option>
                                                            <option value="cancelled" <?php echo $payment['status'] == 'cancelled' ? 'selected' : ''; ?>>İptal</option>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td>
                                                    <?php echo $payment['payment_date'] ? formatDate($payment['payment_date']) : '-'; ?>
                                                </td>
                                                <td>
                                                    <?php if ($payment['due_date']): ?>
                                                        <?php 
                                                        $due_date = new DateTime($payment['due_date']);
                                                        $today = new DateTime();
                                                        $is_overdue = $due_date < $today && $payment['status'] == 'pending';
                                                        ?>
                                                        <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                                            <?php echo formatDate($payment['due_date']); ?>
                                                            <?php if ($is_overdue): ?>
                                                                <i class="fas fa-exclamation-triangle ms-1"></i>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="../order-detail.php?id=<?php echo $payment['order_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Sipariş Detayı">
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
                        <nav aria-label="Ödeme sayfalama" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&payment_method=<?php echo $payment_method_filter; ?>&search=<?php echo urlencode($search); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&payment_method=<?php echo $payment_method_filter; ?>&search=<?php echo urlencode($search); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $pagination['total_pages']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&payment_method=<?php echo $payment_method_filter; ?>&search=<?php echo urlencode($search); ?>">
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