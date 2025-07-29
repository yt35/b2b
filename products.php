<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdmin();

$page_title = 'Ürün Yönetimi';

// Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

// Ürün işlemleri
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (verifyCSRFToken($csrf_token)) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add':
                $category_id = intval($_POST['category_id']);
                $name = sanitizeInput($_POST['name']);
                $description = sanitizeInput($_POST['description']);
                $sku = sanitizeInput($_POST['sku']);
                $price = floatval($_POST['price']);
                $cost_price = floatval($_POST['cost_price']);
                $stock_quantity = intval($_POST['stock_quantity']);
                $min_stock_level = intval($_POST['min_stock_level']);
                $unit = sanitizeInput($_POST['unit']);
                $status = sanitizeInput($_POST['status']);
                $featured = isset($_POST['featured']) ? 1 : 0;
                
                // SKU kontrolü
                $query = "SELECT id FROM products WHERE sku = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$sku]);
                
                if ($stmt->fetch()) {
                    $_SESSION['error_message'] = 'Bu SKU zaten kullanılıyor!';
                } else {
                    $query = "INSERT INTO products (category_id, name, description, sku, price, cost_price, stock_quantity, min_stock_level, unit, status, featured) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute([$category_id, $name, $description, $sku, $price, $cost_price, $stock_quantity, $min_stock_level, $unit, $status, $featured])) {
                        $_SESSION['success_message'] = 'Ürün başarıyla eklendi!';
                        logActivity($_SESSION['user_id'], 'product_added', "Yeni ürün eklendi: {$name}");
                    } else {
                        $_SESSION['error_message'] = 'Ürün eklenirken bir hata oluştu!';
                    }
                }
                break;
                
            case 'update':
                $product_id = intval($_POST['product_id']);
                $category_id = intval($_POST['category_id']);
                $name = sanitizeInput($_POST['name']);
                $description = sanitizeInput($_POST['description']);
                $price = floatval($_POST['price']);
                $cost_price = floatval($_POST['cost_price']);
                $stock_quantity = intval($_POST['stock_quantity']);
                $min_stock_level = intval($_POST['min_stock_level']);
                $unit = sanitizeInput($_POST['unit']);
                $status = sanitizeInput($_POST['status']);
                $featured = isset($_POST['featured']) ? 1 : 0;
                
                $query = "UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, cost_price = ?, 
                          stock_quantity = ?, min_stock_level = ?, unit = ?, status = ?, featured = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$category_id, $name, $description, $price, $cost_price, $stock_quantity, $min_stock_level, $unit, $status, $featured, $product_id])) {
                    $_SESSION['success_message'] = 'Ürün başarıyla güncellendi!';
                    logActivity($_SESSION['user_id'], 'product_updated', "Ürün güncellendi: {$name}");
                } else {
                    $_SESSION['error_message'] = 'Ürün güncellenirken bir hata oluştu!';
                }
                break;
                
            case 'delete':
                $product_id = intval($_POST['product_id']);
                
                // Ürün adını al
                $query = "SELECT name FROM products WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                
                if ($product) {
                    $query = "DELETE FROM products WHERE id = ?";
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute([$product_id])) {
                        $_SESSION['success_message'] = 'Ürün başarıyla silindi!';
                        logActivity($_SESSION['user_id'], 'product_deleted', "Ürün silindi: {$product['name']}");
                    } else {
                        $_SESSION['error_message'] = 'Ürün silinirken bir hata oluştu!';
                    }
                }
                break;
        }
    }
    
    header('Location: products.php');
    exit();
}

// Kategorileri getir
$query = "SELECT * FROM categories WHERE status = 'active' ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll();

// Filtreleme
$category_filter = intval($_GET['category'] ?? 0);
$status_filter = sanitizeInput($_GET['status'] ?? '');
$search = sanitizeInput($_GET['search'] ?? '');

// Sayfalama
$page = intval($_GET['page'] ?? 1);
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Sorgu koşulları
$where_conditions = ["1=1"];
$params = [];

if ($category_filter > 0) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Toplam ürün sayısı
$count_query = "SELECT COUNT(*) as total FROM products p WHERE {$where_clause}";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_products = $stmt->fetch()['total'];

// Sayfalama bilgileri
$pagination = paginate($total_products, $page, $limit);

// Ürünleri getir
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE {$where_clause} 
          ORDER BY p.created_at DESC 
          LIMIT {$limit} OFFSET {$offset}";
$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

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
                    <a class="nav-link active" href="products.php">
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
                        <h2>Ürün Yönetimi</h2>
                        <p class="text-muted"><?php echo number_format($total_products); ?> ürün bulundu</p>
                    </div>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fas fa-plus me-2"></i>
                            Yeni Ürün Ekle
                        </button>
                    </div>
                </div>

                <!-- Filtreler -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Kategori</label>
                                    <select class="form-select" name="category">
                                        <option value="">Tüm Kategoriler</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                    <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Durum</label>
                                    <select class="form-select" name="status">
                                        <option value="">Tüm Durumlar</option>
                                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Pasif</option>
                                        <option value="out_of_stock" <?php echo $status_filter == 'out_of_stock' ? 'selected' : ''; ?>>Stokta Yok</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Arama</label>
                                    <input type="text" class="form-control" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Ürün adı, SKU...">
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

                <!-- Ürün Listesi -->
                <?php if (empty($products)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-box fa-4x text-muted mb-4"></i>
                            <h4>Ürün Bulunamadı</h4>
                            <p class="text-muted">Filtrelere uygun ürün bulunamadı.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Ürün</th>
                                            <th>SKU</th>
                                            <th>Kategori</th>
                                            <th>Fiyat</th>
                                            <th>Stok</th>
                                            <th>Durum</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <?php if ($product['image']): ?>
                                                                <img src="../uploads/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                                     class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                                            <?php else: ?>
                                                                <div class="bg-light d-flex align-items-center justify-content-center" 
                                                                     style="width: 50px; height: 50px;">
                                                                    <i class="fas fa-box text-muted"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                            <?php if ($product['featured']): ?>
                                                                <span class="badge bg-warning text-dark">Öne Çıkan</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                                <td><?php echo formatPrice($product['price']); ?></td>
                                                <td>
                                                    <span class="<?php echo $product['stock_quantity'] <= $product['min_stock_level'] ? 'text-danger fw-bold' : ''; ?>">
                                                        <?php echo number_format($product['stock_quantity']); ?> <?php echo htmlspecialchars($product['unit']); ?>
                                                    </span>
                                                    <?php if ($product['stock_quantity'] <= $product['min_stock_level']): ?>
                                                        <br><small class="text-danger">Düşük Stok!</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_classes = [
                                                        'active' => 'bg-success',
                                                        'inactive' => 'bg-secondary',
                                                        'out_of_stock' => 'bg-danger'
                                                    ];
                                                    $status_labels = [
                                                        'active' => 'Aktif',
                                                        'inactive' => 'Pasif',
                                                        'out_of_stock' => 'Stokta Yok'
                                                    ];
                                                    ?>
                                                    <span class="badge <?php echo $status_classes[$product['status']] ?? 'bg-secondary'; ?>">
                                                        <?php echo $status_labels[$product['status']] ?? $product['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary edit-product" 
                                                                data-product='<?php echo json_encode($product); ?>'
                                                                data-bs-toggle="modal" data-bs-target="#editProductModal">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline" 
                                                              onsubmit="return confirm('Bu ürünü silmek istediğinizden emin misiniz?')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
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
                        <nav aria-label="Ürün sayfalama" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($pagination['total_pages'], $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $pagination['total_pages']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">
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

<!-- Ürün Ekleme Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Ürün Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ürün Adı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SKU <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="sku" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" name="category_id" required>
                                <option value="">Kategori Seçin</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Birim</label>
                            <input type="text" class="form-control" name="unit" value="adet">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Satış Fiyatı <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Maliyet Fiyatı</label>
                            <input type="number" class="form-control" name="cost_price" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stok Miktarı <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="stock_quantity" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Minimum Stok Seviyesi</label>
                            <input type="number" class="form-control" name="min_stock_level" value="10" min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Durum</label>
                            <select class="form-select" name="status">
                                <option value="active">Aktif</option>
                                <option value="inactive">Pasif</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="featured" id="featured">
                                <label class="form-check-label" for="featured">
                                    Öne Çıkan Ürün
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Ürün Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Ürün Düzenleme Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ürün Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editProductForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="product_id" id="edit_product_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ürün Adı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SKU</label>
                            <input type="text" class="form-control" id="edit_sku" readonly>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" name="category_id" id="edit_category_id" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Birim</label>
                            <input type="text" class="form-control" name="unit" id="edit_unit">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Satış Fiyatı <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="price" id="edit_price" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Maliyet Fiyatı</label>
                            <input type="number" class="form-control" name="cost_price" id="edit_cost_price" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stok Miktarı <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="stock_quantity" id="edit_stock_quantity" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Minimum Stok Seviyesi</label>
                            <input type="number" class="form-control" name="min_stock_level" id="edit_min_stock_level" min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Durum</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="active">Aktif</option>
                                <option value="inactive">Pasif</option>
                                <option value="out_of_stock">Stokta Yok</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="featured" id="edit_featured">
                                <label class="form-check-label" for="edit_featured">
                                    Öne Çıkan Ürün
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Güncelle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Ürün düzenleme modal'ını doldur
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.edit-product');
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const product = JSON.parse(this.getAttribute('data-product'));
            
            document.getElementById('edit_product_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_sku').value = product.sku;
            document.getElementById('edit_category_id').value = product.category_id;
            document.getElementById('edit_unit').value = product.unit;
            document.getElementById('edit_description').value = product.description || '';
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_cost_price').value = product.cost_price || '';
            document.getElementById('edit_stock_quantity').value = product.stock_quantity;
            document.getElementById('edit_min_stock_level').value = product.min_stock_level;
            document.getElementById('edit_status').value = product.status;
            document.getElementById('edit_featured').checked = product.featured == 1;
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>