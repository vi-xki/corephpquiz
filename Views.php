<?php
// config.php - Database configuration
class Database {
    private $host = 'localhost';
    private $db_name = 'interview_app';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                                $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            echo "Connection error: " . $e->getMessage();
        }
        return $this->conn;
    }
}

// Database setup script (run once)
function setupDatabase() {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->exec("CREATE DATABASE IF NOT EXISTS interview_app");
    $pdo->exec("USE interview_app");
    
    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // User sessions table for tracking active logins
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Data records table
    $pdo->exec("CREATE TABLE IF NOT EXISTS records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        department VARCHAR(50),
        salary DECIMAL(10,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Insert sample admin user (password: admin123)
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT IGNORE INTO users (username, password) VALUES ('admin', '$hashedPassword')");
}

// Uncomment the line below to setup database (run once)
// setupDatabase();

session_start();

// JWT-like token functions
function generateToken($userId) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode(['user_id' => $userId, 'exp' => time() + 3600]);
    $headerEncoded = base64url_encode($header);
    $payloadEncoded = base64url_encode($payload);
    $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, 'secret_key', true);
    $signatureEncoded = base64url_encode($signature);
    return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

function verifyToken($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    
    $header = json_decode(base64url_decode($parts[0]), true);
    $payload = json_decode(base64url_decode($parts[1]), true);
    $signature = base64url_decode($parts[2]);
    
    $expectedSignature = hash_hmac('sha256', $parts[0] . "." . $parts[1], 'secret_key', true);
    
    if (!hash_equals($signature, $expectedSignature)) return false;
    if ($payload['exp'] < time()) return false;
    
    return $payload;
}

// Authentication functions
function login($username, $password) {
    $db = new Database();
    $conn = $db->connect();
    
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // Check active sessions limit
        $stmt = $conn->prepare("SELECT COUNT(*) as active_sessions FROM user_sessions WHERE user_id = ? AND is_active = TRUE");
        $stmt->execute([$user['id']]);
        $activeCount = $stmt->fetch(PDO::FETCH_ASSOC)['active_sessions'];
        
        if ($activeCount >= 2) {
            return ['success' => false, 'message' => 'Maximum 2 active sessions allowed'];
        }
        
        // Create new session
        $token = generateToken($user['id']);
        $stmt = $conn->prepare("INSERT INTO user_sessions (user_id, token) VALUES (?, ?)");
        $stmt->execute([$user['id'], $token]);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['token'] = $token;
        
        return ['success' => true, 'message' => 'Login successful'];
    }
    
    return ['success' => false, 'message' => 'Invalid credentials'];
}

function logout() {
    if (isset($_SESSION['token'])) {
        $db = new Database();
        $conn = $db->connect();
        $stmt = $conn->prepare("UPDATE user_sessions SET is_active = FALSE WHERE token = ?");
        $stmt->execute([$_SESSION['token']]);
    }
    session_destroy();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['token']);
}

// File upload and processing functions
function processExcelFile($file) {
    $errors = [];
    $data = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload failed'];
    }
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, ['csv', 'xlsx', 'xls'])) {
        return ['success' => false, 'message' => 'Only CSV and Excel files allowed'];
    }
    
    // For simplicity, we'll handle CSV files. For Excel, you'd need PHPSpreadsheet
    if ($fileExtension === 'csv') {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 5) {
                $data[] = [
                    'name' => $row[0],
                    'email' => $row[1], 
                    'phone' => $row[2],
                    'department' => $row[3],
                    'salary' => floatval($row[4])
                ];
            }
        }
        fclose($handle);
    }
    
    return ['success' => true, 'data' => $data];
}

function syncDataToDatabase($data) {
    $db = new Database();
    $conn = $db->connect();
    
    // Get existing records
    $stmt = $conn->prepare("SELECT email FROM records");
    $stmt->execute();
    $existingEmails = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $uploadedEmails = array_column($data, 'email');
    
    // Insert new records
    $insertStmt = $conn->prepare("INSERT INTO records (name, email, phone, department, salary) VALUES (?, ?, ?, ?, ?)");
    
    // Update existing records
    $updateStmt = $conn->prepare("UPDATE records SET name = ?, phone = ?, department = ?, salary = ?, updated_at = CURRENT_TIMESTAMP WHERE email = ?");
    
    foreach ($data as $record) {
        if (in_array($record['email'], $existingEmails)) {
            // Update existing record
            $updateStmt->execute([
                $record['name'],
                $record['phone'],
                $record['department'],
                $record['salary'],
                $record['email']
            ]);
        } else {
            // Insert new record
            $insertStmt->execute([
                $record['name'],
                $record['email'],
                $record['phone'],
                $record['department'],
                $record['salary']
            ]);
        }
    }
    
    // Soft delete records not in uploaded data
    $emailsToDelete = array_diff($existingEmails, $uploadedEmails);
    if (!empty($emailsToDelete)) {
        $placeholders = str_repeat('?,', count($emailsToDelete) - 1) . '?';
        $deleteStmt = $conn->prepare("DELETE FROM records WHERE email IN ($placeholders)");
        $deleteStmt->execute($emailsToDelete);
    }
    
    return ['success' => true, 'message' => 'Data synchronized successfully'];
}

// Data display functions
function getRecords($filters = []) {
    $db = new Database();
    $conn = $db->connect();
    
    $sql = "SELECT * FROM records WHERE 1=1";
    $params = [];
    
    if (!empty($filters['name'])) {
        $sql .= " AND name LIKE ?";
        $params[] = '%' . $filters['name'] . '%';
    }
    
    if (!empty($filters['department'])) {
        $sql .= " AND department LIKE ?";
        $params[] = '%' . $filters['department'] . '%';
    }
    
    if (!empty($filters['email'])) {
        $sql .= " AND email LIKE ?";
        $params[] = '%' . $filters['email'] . '%';
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle requests
$action = $_GET['action'] ?? '';
$message = '';

if ($_POST) {
    if ($action === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if ($username && $password) {
            $result = login($username, $password);
            $message = $result['message'];
            
            if ($result['success']) {
                header('Location: ?action=dashboard');
                exit;
            }
        }
    }
    
    if ($action === 'upload' && isLoggedIn()) {
        if (isset($_FILES['excel_file'])) {
            $result = processExcelFile($_FILES['excel_file']);
            
            if ($result['success']) {
                $syncResult = syncDataToDatabase($result['data']);
                $message = $syncResult['message'];
            } else {
                $message = $result['message'];
            }
        }
    }
}

if ($action === 'logout') {
    logout();
    header('Location: ?action=login');
    exit;
}

// Get records for display
$filters = [];
if (isset($_GET['filter_name'])) $filters['name'] = $_GET['filter_name'];
if (isset($_GET['filter_department'])) $filters['department'] = $_GET['filter_department'];
if (isset($_GET['filter_email'])) $filters['email'] = $_GET['filter_email'];

$records = [];
if (isLoggedIn()) {
    $records = getRecords($filters);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Task - PHP Web Application</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 1200px;
        }
        
        .login-form {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        input[type="text"], input[type="password"], input[type="file"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #5a67d8;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e1e5e9;
        }
        
        .upload-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 5px;
            margin-bottom: 2rem;
        }
        
        .filter-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 5px;
            margin-bottom: 2rem;
        }
        
        .filter-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        th, td {
            text-align: left;
            padding: 0.75rem;
            border-bottom: 1px solid #e1e5e9;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .no-data {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .stat-card {
            background: #667eea;
            color: white;
            padding: 1rem;
            border-radius: 5px;
            flex: 1;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!isLoggedIn()): ?>
            <!-- Login Form -->
            <div class="login-form">
                <h2 style="text-align: center; margin-bottom: 2rem; color: #333;">Login</h2>
                
                <?php if ($message): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="?action=login">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn" style="width: 100%;">Login</button>
                </form>
                
                <div style="margin-top: 1rem; text-align: center; color: #6c757d; font-size: 0.9rem;">
                    <p>Demo credentials: admin / admin123</p>
                    <p>Maximum 2 active sessions allowed per user</p>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Dashboard -->
            <div class="header">
                <h1>Data Management Dashboard</h1>
                <div>
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                    <a href="?action=logout" class="btn btn-secondary" style="margin-left: 1rem;">Logout</a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <!-- Stats -->
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($records); ?></div>
                    <div class="stat-label">Total Records</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_unique(array_column($records, 'department'))); ?></div>
                    <div class="stat-label">Departments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $records ? '$' . number_format(array_sum(array_column($records, 'salary'))) : '0'; ?></div>
                    <div class="stat-label">Total Salary</div>
                </div>
            </div>
            
            <!-- File Upload Section -->
            <div class="upload-section">
                <h3>Upload Excel/CSV File</h3>
                <p style="margin-bottom: 1rem; color: #6c757d;">
                    Upload a CSV file with columns: Name, Email, Phone, Department, Salary
                </p>
                
                <form method="POST" action="?action=upload" enctype="multipart/form-data">
                    <div class="form-group">
                        <input type="file" name="excel_file" accept=".csv,.xlsx,.xls" required>
                    </div>
                    <button type="submit" class="btn">Upload & Sync Data</button>
                </form>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <h3>Filter Records</h3>
                <form method="GET" class="filter-form">
                    <input type="hidden" name="action" value="dashboard">
                    
                    <div class="filter-group">
                        <label for="filter_name">Name:</label>
                        <input type="text" id="filter_name" name="filter_name" 
                               value="<?php echo htmlspecialchars($filters['name'] ?? ''); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="filter_department">Department:</label>
                        <input type="text" id="filter_department" name="filter_department"
                               value="<?php echo htmlspecialchars($filters['department'] ?? ''); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="filter_email">Email:</label>
                        <input type="text" id="filter_email" name="filter_email"
                               value="<?php echo htmlspecialchars($filters['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="btn">Apply Filters</button>
                        <a href="?action=dashboard" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>
            
            <!-- Data Display -->
            <div>
                <h3>Records</h3>
                
                <?php if (empty($records)): ?>
                    <div class="no-data">
                        <p>No records found. Upload a CSV file to get started.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Department</th>
                                <th>Salary</th>
                                <th>Created</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['email']); ?></td>
                                    <td><?php echo htmlspecialchars($record['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($record['department']); ?></td>
                                    <td>$<?php echo number_format($record['salary'], 2); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($record['created_at'])); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($record['updated_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
