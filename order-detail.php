<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

requireLogin();

$page_title = 'Sipariş Detayı';

// Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

$order_id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($order_id <= 0) {
    header('Location: orders.php');
    exit();
}

// Sipariş bilgilerini getir
if (isCustomer()) {
    $query = "SELECT o.*, u.company_name, u.contact_person, u.phone, u.email, u.address 
              FROM orders o 
              LEFT JOIN users u ON o.customer_id = u.id 
              WHERE o.id = ? AND o.customer_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$order_id, $user_id]);
} else {
    $query = "SELECT o.*, u.company_name, u.contact_person, u.phone, u.email, u.address 
              FROM orders o 
              LEFT JOIN users u ON o.customer_id = u.id 
              WHERE o.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$order_id]);
}

$order = $stmt->fetch();

if (!$order) {
    $_SESSION['error_message'] = 'Sipariş bulunamadı!';
    header('Location: orders.php');
    exit();
}

// Sipariş detaylarını getir
$query = "SELECT oi.*, p.image FROM order_items oi 
          LEFT JOIN products p ON oi.product_id = p.id 
          WHERE oi.order_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

// Ödeme bilgilerini getir
$query = "SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$order_id]);
$payments = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2>Sipariş Detayı</h2>
                    <p class="text-muted">Sipariş No: <?php echo htmlspecialchars($order['order_number']); ?></p>
                </div>
                <div>
                    <a href="<?php echo isCustomer() ? 'orders.php' : (isAdmin() ? 'admin/orders.php' : 'supplier/orders.php'); ?>" 
                       class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>
                        Geri Dön
                    </a>
                    <a href="order-invoice.php?id=<?php echo $order['id']; ?>" 
                       class="btn btn-outline-primary" target="_blank">
                        <i class="fas fa-file-pdf me-2"></i>
                        Fatura
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Sipariş Bilgileri -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Sipariş Bilgileri
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Sipariş No:</strong></td>
                                    <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Tarih:</strong></td>
                                    <td><?php echo formatDate($order['created_at']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Durum:</strong></td>
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
                                </tr>
                                <tr>
                                    <td><strong>Ödeme Durumu:</strong></td>
                                    <td>
                                        <span class="badge payment-status-<?php echo $order['payment_status']; ?>">
                                            <?php
                                            $payment_labels = [
                                                'pending' => 'Beklemede',
                                                'paid' => 'Ödendi',
                                                'partial' => 'Kısmi',
                                                'cancelled' => 'İptal'
                                            ];
                                            echo $payment_labels[$order['payment_status']] ?? $order['payment_status'];
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Ödeme Yöntemi:</strong></td>
                                    <td>
                                        <?php
                                        $payment_methods = [
                                            'cash' => 'Nakit',
                                            'card' => 'Kredi Kartı',
                                            'bank_transfer' => 'Banka Havalesi',
                                            'check' => 'Çek'
                                        ];
                                        echo translatePaymentMethod($order['payment_method'] ?? 'Belirtilmemiş');
                                        //echo $payment_methods[$order['payment_method']] ?? $order['payment_method'];
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Ara Toplam:</strong></td>
                                    <td><?php echo formatPrice($order['subtotal']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>KDV:</strong></td>
                                    <td><?php echo formatPrice($order['tax_amount']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Toplam:</strong></td>
                                    <td><strong class="text-primary"><?php echo formatPrice($order['total_amount']); ?></strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if ($order['notes']): ?>
                        <div class="mt-3">
                            <strong>Notlar:</strong>
                            <p class="text-muted mt-2"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sipariş Ürünleri -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-box me-2"></i>
                        Sipariş Ürünleri
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Ürün</th>
                                    <th>SKU</th>
                                    <th>Birim Fiyat</th>
                                    <th>Miktar</th>
                                    <th>Toplam</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <?php if ($item['image']): ?>
                                                        <img src="uploads/products/<?php echo htmlspecialchars($item['image']); ?>" 
                                                             alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                                             class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-light d-flex align-items-center justify-content-center" 
                                                             style="width: 50px; height: 50px;">
                                                            <i class="fas fa-box text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['product_sku']); ?></td>
                                        <td><?php echo formatPrice($item['unit_price']); ?></td>
                                        <td><?php echo number_format($item['quantity']); ?></td>
                                        <td><strong><?php echo formatPrice($item['total_price']); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Müşteri ve Teslimat Bilgileri -->
        <div class="col-lg-4">
            <!-- Müşteri Bilgileri -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-building me-2"></i>
                        Müşteri Bilgileri
                    </h5>
                </div>
                <div class="card-body">
                    <h6><?php echo htmlspecialchars($order['company_name']); ?></h6>
                    <p class="mb-2"><?php echo htmlspecialchars($order['contact_person']); ?></p>
                    <p class="mb-2">
                        <i class="fas fa-phone me-2"></i>
                        <?php echo htmlspecialchars($order['phone']); ?>
                    </p>
                    <p class="mb-0">
                        <i class="fas fa-envelope me-2"></i>
                        <?php echo htmlspecialchars($order['email']); ?>
                    </p>
                </div>
            </div>

            <!-- Teslimat Adresi -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-truck me-2"></i>
                        Teslimat Adresi
                    </h5>
                </div>
                <div class="card-body">
                    <p class="mb-0">
                        <?php echo nl2br(htmlspecialchars($order['shipping_address'] ?: $order['address'])); ?>
                    </p>
                </div>
            </div>

            <!-- Ödeme Geçmişi -->
            <?php if (!empty($payments)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-credit-card me-2"></i>
                            Ödeme Geçmişi
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($payments as $payment): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <span class="badge payment-status-<?php echo $payment['status']; ?>">
                                        <?php
                                        $payment_status_labels = [
                                            'pending' => 'Beklemede',
                                            'completed' => 'Tamamlandı',
                                            'failed' => 'Başarısız',
                                            'cancelled' => 'İptal'
                                        ];
                                        echo $payment_status_labels[$payment['status']] ?? $payment['status'];
                                        ?>
                                    </span>
                                    <br><small class="text-muted"><?php echo formatDate($payment['created_at']); ?></small>
                                </div>
                                <div class="text-end">
                                    <strong><?php echo formatPrice($payment['amount']); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- İşlemler -->
            <?php if (isCustomer() && $order['status'] == 'pending'): ?>
                <div class="card mt-3">
                    <div class="card-body text-center">
                        <button class="btn btn-danger w-100" onclick="cancelOrder(<?php echo $order['id']; ?>)">
                            <i class="fas fa-times me-2"></i>
                            Siparişi İptal Et
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function cancelOrder(orderId) {
    if (confirm('Bu siparişi iptal etmek istediğinizden emin misiniz?')) {
        $.post('ajax/cancel_order.php', {
            order_id: orderId,
            csrf_token: '<?php echo generateCSRFToken(); ?>'
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message || 'Bir hata oluştu!');
            }
        })
        .fail(function() {
            alert('Bağlantı hatası!');
        });
    }
}
</script>

<?php include 'includes/footer.php'; ?>