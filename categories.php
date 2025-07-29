<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdmin();

$page_title = 'Kategori Yönetimi';

// Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

// Kategori işlemleri
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (verifyCSRFToken($csrf_token)) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add':
                $name = sanitizeInput($_POST['name']);
                $description = sanitizeInput($_POST['description']);
                $status = sanitizeInput($_POST['status']);
                $sort_order = intval($_POST['sort_order']);
                
                $query = "INSERT INTO categories (name, description, status, sort_order) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$name, $description, $status, $sort_order])) {
                    $_SESSION['success_message'] = 'Kategori başarıyla eklendi!';
                    logActivity($_SESSION['user_id'], 'category_added', "Yeni kategori eklendi: {$name}");
                } else {
                    $_SESSION['error_message'] = 'Kategori eklenirken bir hata oluştu!';
                }
                break;
                
            case 'update':
                $category_id = intval($_POST['category_id']);
                $name = sanitizeInput($_POST['name']);
                $description = sanitizeInput($_POST['description']);
                $status = sanitizeInput($_POST['status']);
                $sort_order = intval($_POST['sort_order']);
                
                $query = "UPDATE categories SET name = ?, description = ?, status = ?, sort_order = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$name, $description, $status, $sort_order, $category_id])) {
                    $_SESSION['success_message'] = 'Kategori başarıyla güncellendi!';
                    logActivity($_SESSION['user_id'], 'category_updated', "Kategori güncellendi: {$name}");
                } else {
                    $_SESSION['error_message'] = 'Kategori güncellenirken bir hata oluştu!';
                }
                break;
                
            case 'delete':
                $category_id = intval($_POST['category_id']);
                
                // Kategori adını al
                $query = "SELECT name FROM categories WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$category_id]);
                $category = $stmt->fetch();
                
                if ($category) {
                    // Kategoriye ait ürün var mı kontrol et
                    $query = "SELECT COUNT(*) as count FROM products WHERE category_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$category_id]);
                    $product_count = $stmt->fetch()['count'];
                    
                    if ($product_count > 0) {
                        $_SESSION['error_message'] = 'Bu kategoriye ait ürünler bulunduğu için silinemez!';
                    } else {
                        $query = "DELETE FROM categories WHERE id = ?";
                        $stmt = $db->prepare($query);
                        
                        if ($stmt->execute([$category_id])) {
                            $_SESSION['success_message'] = 'Kategori başarıyla silindi!';
                            logActivity($_SESSION['user_id'], 'category_deleted', "Kategori silindi: {$category['name']}");
                        } else {
                            $_SESSION['error_message'] = 'Kategori silinirken bir hata oluştu!';
                        }
                    }
                }
                break;
        }
    }
    
    header('Location: categories.php');
    exit();
}

// Kategorileri getir
$query = "SELECT c.*, COUNT(p.id) as product_count 
          FROM categories c 
          LEFT JOIN products p ON c.id = p.category_id 
          GROUP BY c.id 
          ORDER BY c.sort_order, c.name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="p-4">
                <!-- Başlık -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2>Kategori Yönetimi</h2>
                        <p class="text-muted"><?php echo count($categories); ?> kategori bulundu</p>
                    </div>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-plus me-2"></i>
                            Yeni Kategori Ekle
                        </button>
                    </div>
                </div>

                <!-- Kategori Listesi -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Kategori Adı</th>
                                        <th>Açıklama</th>
                                        <th>Ürün Sayısı</th>
                                        <th>Sıralama</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars(substr($category['description'] ?? '', 0, 100)); ?>
                                                <?php if (strlen($category['description'] ?? '') > 100): ?>...<?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $category['product_count']; ?> ürün</span>
                                            </td>
                                            <td><?php echo $category['sort_order']; ?></td>
                                            <td>
                                                <span class="badge <?php echo $category['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $category['status'] == 'active' ? 'Aktif' : 'Pasif'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary edit-category" 
                                                            data-category='<?php echo json_encode($category); ?>'
                                                            data-bs-toggle="modal" data-bs-target="#editCategoryModal">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" class="d-inline" 
                                                          onsubmit="return confirm('Bu kategoriyi silmek istediğinizden emin misiniz?')">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
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
            </div>
        </div>
    </div>
</div>

<!-- Kategori Ekleme Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Kategori Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kategori Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
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
                            <label class="form-label">Sıralama</label>
                            <input type="number" class="form-control" name="sort_order" value="0" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kategori Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Kategori Düzenleme Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kategori Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editCategoryForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="category_id" id="edit_category_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kategori Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Durum</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="active">Aktif</option>
                                <option value="inactive">Pasif</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sıralama</label>
                            <input type="number" class="form-control" name="sort_order" id="edit_sort_order" min="0">
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
// Kategori düzenleme modal'ını doldur
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.edit-category');
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const category = JSON.parse(this.getAttribute('data-category'));
            
            document.getElementById('edit_category_id').value = category.id;
            document.getElementById('edit_name').value = category.name;
            document.getElementById('edit_description').value = category.description || '';
            document.getElementById('edit_status').value = category.status;
            document.getElementById('edit_sort_order').value = category.sort_order;
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>