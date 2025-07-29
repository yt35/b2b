<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

requireAdmin();

$page_title = 'Sistem Ayarları';

// Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

// Ayar güncelleme
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (verifyCSRFToken($csrf_token)) {
        $settings = $_POST['settings'] ?? [];
        
        foreach ($settings as $key => $value) {
            $query = "UPDATE settings SET setting_value = ? WHERE setting_key = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$value, $key]);
        }
        
        $_SESSION['success_message'] = 'Ayarlar başarıyla güncellendi!';
        logActivity($_SESSION['user_id'], 'settings_updated', 'Sistem ayarları güncellendi');
    }
    
    header('Location: settings.php');
    exit();
}

// Ayarları getir
$query = "SELECT * FROM settings ORDER BY setting_key";
$stmt = $db->prepare($query);
$stmt->execute();
$settings = $stmt->fetchAll();

// Ayarları grupla
$grouped_settings = [];
foreach ($settings as $setting) {
    $group = 'Genel';
    if (strpos($setting['setting_key'], 'company_') === 0) {
        $group = 'Şirket Bilgileri';
    } elseif (strpos($setting['setting_key'], 'tax_') === 0 || strpos($setting['setting_key'], 'currency') === 0) {
        $group = 'Mali Ayarlar';
    } elseif (strpos($setting['setting_key'], 'min_order') === 0 || strpos($setting['setting_key'], 'free_shipping') === 0) {
        $group = 'Sipariş Ayarları';
    }
    $grouped_settings[$group][] = $setting;
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="p-4">
                <!-- Başlık -->
                <div class="mb-4">
                    <h2>
                        <i class="fas fa-cog me-2"></i>
                        Sistem Ayarları
                    </h2>
                    <p class="text-muted">Site genelinde kullanılan ayarları buradan yönetebilirsiniz</p>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <?php foreach ($grouped_settings as $group_name => $group_settings): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <?php
                                    $icons = [
                                        'Şirket Bilgileri' => 'fas fa-building',
                                        'Mali Ayarlar' => 'fas fa-calculator',
                                        'Sipariş Ayarları' => 'fas fa-shopping-cart',
                                        'Genel' => 'fas fa-cogs'
                                    ];
                                    ?>
                                    <i class="<?php echo $icons[$group_name] ?? 'fas fa-cog'; ?> me-2"></i>
                                    <?php echo $group_name; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($group_settings as $setting): ?>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">
                                                <?php
                                                $labels = [
                                                    'site_maintenance' => 'Site Bakım Modu',
                                                    'tax_rate' => 'KDV Oranı (%)',
                                                    'min_order_amount' => 'Minimum Sipariş Tutarı (TL)',
                                                    'free_shipping_limit' => 'Ücretsiz Kargo Limiti (TL)',
                                                    'currency' => 'Para Birimi',
                                                    'currency_symbol' => 'Para Birimi Sembolü',
                                                    'company_name' => 'Şirket Adı',
                                                    'company_address' => 'Şirket Adresi',
                                                    'company_phone' => 'Şirket Telefonu',
                                                    'company_email' => 'Şirket E-posta',
                                                    'company_website' => 'Şirket Web Sitesi',
                                                    'working_hours' => 'Çalışma Saatleri',
                                                    'delivery_info' => 'Teslimat Bilgisi',
                                                    'payment_terms' => 'Ödeme Koşulları'
                                                ];
                                                echo $labels[$setting['setting_key']] ?? ucfirst(str_replace('_', ' ', $setting['setting_key']));
                                                ?>
                                            </label>
                                            
                                            <?php if ($setting['setting_type'] == 'boolean'): ?>
                                                <select class="form-select" name="settings[<?php echo $setting['setting_key']; ?>]">
                                                    <option value="0" <?php echo $setting['setting_value'] == '0' ? 'selected' : ''; ?>>Hayır</option>
                                                    <option value="1" <?php echo $setting['setting_value'] == '1' ? 'selected' : ''; ?>>Evet</option>
                                                </select>
                                            <?php elseif ($setting['setting_type'] == 'number'): ?>
                                                <input type="number" class="form-control" 
                                                       name="settings[<?php echo $setting['setting_key']; ?>]" 
                                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                       step="0.01" min="0">
                                            <?php elseif (in_array($setting['setting_key'], ['company_address', 'working_hours', 'delivery_info', 'payment_terms'])): ?>
                                                <textarea class="form-control" rows="3"
                                                          name="settings[<?php echo $setting['setting_key']; ?>]"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                            <?php else: ?>
                                                <input type="text" class="form-control" 
                                                       name="settings[<?php echo $setting['setting_key']; ?>]" 
                                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                            <?php endif; ?>
                                            
                                            <?php if ($setting['description']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($setting['description']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>
                            Ayarları Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>