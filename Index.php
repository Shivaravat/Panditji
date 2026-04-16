<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$dbname = 'panditseva_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    try {
        $pdo = new PDO("mysql:host=$host", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS panditseva_db");
        $pdo->exec("USE panditseva_db");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100),
                mobile VARCHAR(15) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(20) DEFAULT 'user',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS pandits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                experience INT DEFAULT 0,
                rating DECIMAL(3,1) DEFAULT 0,
                reviews INT DEFAULT 0,
                price INT DEFAULT 5000,
                city VARCHAR(100),
                languages VARCHAR(200),
                specialization VARCHAR(500),
                about TEXT,
                image VARCHAR(50) DEFAULT '🕉️',
                mobile VARCHAR(15),
                email VARCHAR(100),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
            
            CREATE TABLE IF NOT EXISTS bookings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                booking_no VARCHAR(20) UNIQUE,
                user_id INT NOT NULL,
                pandit_id INT NOT NULL,
                user_name VARCHAR(100),
                pandit_name VARCHAR(100),
                service VARCHAR(100),
                event_date DATE,
                event_time VARCHAR(20),
                address TEXT,
                instructions TEXT,
                amount INT DEFAULT 0,
                status VARCHAR(20) DEFAULT 'Pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (pandit_id) REFERENCES pandits(id)
            );
            
            CREATE TABLE IF NOT EXISTS reviews (
                id INT AUTO_INCREMENT PRIMARY KEY,
                booking_id INT NOT NULL,
                user_id INT NOT NULL,
                pandit_id INT NOT NULL,
                rating INT,
                comment TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (booking_id) REFERENCES bookings(id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (pandit_id) REFERENCES pandits(id)
            );
        ");
        
        $checkUser = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if($checkUser == 0) {
            $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
            $userPass = password_hash('password', PASSWORD_DEFAULT);
            
            $pdo->exec("
                INSERT INTO users (name, email, mobile, password, role) VALUES 
                ('Admin User', 'admin@test.com', '9999999999', '$adminPass', 'admin'),
                ('Pandit Rajesh Sharma', 'rajesh@test.com', '9876543210', '$userPass', 'pandit'),
                ('Pandit Suresh Tiwari', 'suresh@test.com', '9876543211', '$userPass', 'pandit'),
                ('Rahul Kumar', 'rahul@test.com', '9876543213', '$userPass', 'user');
                
                INSERT INTO pandits (user_id, name, experience, rating, reviews, price, city, languages, specialization, about, image, mobile, email) VALUES 
                (2, 'Pandit Rajesh Sharma', 15, 4.8, 234, 11000, 'Delhi', 'Hindi, Sanskrit', 'Wedding, Havan, Grih Pravesh', 'Expert in Vedic rituals with 15+ years experience.', '🔱', '9876543210', 'rajesh@test.com'),
                (3, 'Pandit Suresh Tiwari', 20, 4.9, 456, 15000, 'Mumbai', 'Hindi, Marathi, Sanskrit', 'Wedding, Satyanarayan, Mundan, Naamkaran', '20 years of experience in wedding rituals.', '🕉️', '9876543211', 'suresh@test.com');
            ");
        }
    } catch(PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}

// Handle POST requests
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Login
    if($_POST['action'] === 'login') {
        $mobile = $_POST['mobile'];
        $password = $_POST['password'];
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE mobile = ?");
        $stmt->execute([$mobile]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            $_SESSION['user'] = $user;
            
            if($user['role'] == 'pandit') {
                $stmt2 = $pdo->prepare("SELECT * FROM pandits WHERE user_id = ?");
                $stmt2->execute([$user['id']]);
                $pandit = $stmt2->fetch(PDO::FETCH_ASSOC);
                if($pandit) {
                    $user['pandit_id'] = $pandit['id'];
                    $user['price'] = $pandit['price'];
                    $user['city'] = $pandit['city'];
                    $user['experience'] = $pandit['experience'];
                    $user['rating'] = $pandit['rating'];
                    $_SESSION['user'] = $user;
                }
            }
            
            echo json_encode(['status' => true, 'message' => 'Login successful', 'user' => $user]);
        } else {
            echo json_encode(['status' => false, 'message' => 'Invalid credentials']);
        }
        exit();
    }
    
    // Register
    if($_POST['action'] === 'register') {
        $check = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
        $check->execute([$_POST['mobile']]);
        if($check->rowCount() > 0) {
            echo json_encode(['status' => false, 'message' => 'Mobile already registered']);
            exit();
        }
        
        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, mobile, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['email'], $_POST['mobile'], $hashed_password, $_POST['role']]);
        $user_id = $pdo->lastInsertId();
        
        if($_POST['role'] == 'pandit') {
            $stmt2 = $pdo->prepare("INSERT INTO pandits (user_id, name, experience, city, languages, specialization, price, mobile, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt2->execute([$user_id, $_POST['name'], $_POST['experience'], $_POST['city'], $_POST['languages'], $_POST['specialization'], $_POST['price'], $_POST['mobile'], $_POST['email']]);
        }
        
        echo json_encode(['status' => true, 'message' => 'Registration successful! Please login.']);
        exit();
    }
    
    // Add Pandit (Admin only)
    if($_POST['action'] === 'add_pandit') {
        if(!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            echo json_encode(['status' => false, 'message' => 'Unauthorized']);
            exit();
        }
        
        $check = $pdo->prepare("SELECT id FROM users WHERE mobile = ?");
        $check->execute([$_POST['mobile']]);
        if($check->rowCount() > 0) {
            echo json_encode(['status' => false, 'message' => 'Mobile number already exists']);
            exit();
        }
        
        $hashed_password = password_hash('password', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, mobile, password, role) VALUES (?, ?, ?, ?, 'pandit')");
        $stmt->execute([$_POST['name'], $_POST['email'], $_POST['mobile'], $hashed_password]);
        $user_id = $pdo->lastInsertId();
        
        $stmt2 = $pdo->prepare("INSERT INTO pandits (user_id, name, experience, city, languages, specialization, price, mobile, email, about, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt2->execute([$user_id, $_POST['name'], $_POST['experience'], $_POST['city'], $_POST['languages'], $_POST['specialization'], $_POST['price'], $_POST['mobile'], $_POST['email'], $_POST['about'], $_POST['image']]);
        
        echo json_encode(['status' => true, 'message' => 'Pandit added successfully']);
        exit();
    }
    
    // Create Booking
    if($_POST['action'] === 'create_booking') {
        if(!isset($_SESSION['user'])) {
            echo json_encode(['status' => false, 'message' => 'Please login first']);
            exit();
        }
        
        $booking_no = 'BK' . time() . rand(100, 999);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO bookings (booking_no, user_id, pandit_id, user_name, pandit_name, service, event_date, event_time, address, instructions, amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
            
            $stmt->execute([
                $booking_no,
                $_SESSION['user']['id'],
                $_POST['pandit_id'],
                $_SESSION['user']['name'],
                $_POST['pandit_name'],
                $_POST['service'],
                $_POST['event_date'],
                $_POST['event_time'],
                $_POST['address'],
                $_POST['instructions'],
                $_POST['amount']
            ]);
            
            echo json_encode(['status' => true, 'message' => 'Booking created successfully!']);
        } catch(Exception $e) {
            echo json_encode(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Update Booking Status
    if($_POST['action'] === 'update_booking') {
        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], $_POST['booking_id']]);
        echo json_encode(['status' => true, 'message' => 'Status updated']);
        exit();
    }
    
    // Add Review
    if($_POST['action'] === 'add_review') {
        if(!isset($_SESSION['user'])) {
            echo json_encode(['status' => false, 'message' => 'Please login']);
            exit();
        }
        
        $check = $pdo->prepare("SELECT id FROM reviews WHERE booking_id = ? AND user_id = ?");
        $check->execute([$_POST['booking_id'], $_SESSION['user']['id']]);
        if($check->rowCount() > 0) {
            echo json_encode(['status' => false, 'message' => 'You have already reviewed this booking']);
            exit();
        }
        
        $stmt = $pdo->prepare("INSERT INTO reviews (booking_id, user_id, pandit_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['booking_id'], $_SESSION['user']['id'], $_POST['pandit_id'], $_POST['rating'], $_POST['comment']]);
        
        $update = $pdo->prepare("UPDATE pandits SET rating = (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE pandit_id = ?), reviews = (SELECT COUNT(*) FROM reviews WHERE pandit_id = ?) WHERE id = ?");
        $update->execute([$_POST['pandit_id'], $_POST['pandit_id'], $_POST['pandit_id']]);
        
        echo json_encode(['status' => true, 'message' => 'Review submitted successfully!']);
        exit();
    }
    
    // Logout
    if($_POST['action'] === 'logout') {
        session_destroy();
        echo json_encode(['status' => true, 'message' => 'Logged out']);
        exit();
    }
}

// Handle GET requests
if(isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if($_GET['action'] === 'get_pandits') {
        $stmt = $pdo->query("SELECT * FROM pandits ORDER BY rating DESC");
        $pandits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => true, 'pandits' => $pandits]);
        exit();
    }
    
    if($_GET['action'] === 'my_bookings') {
        if(!isset($_SESSION['user'])) {
            echo json_encode(['status' => false]);
            exit();
        }
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['user']['id']]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => true, 'bookings' => $bookings]);
        exit();
    }
    
    if($_GET['action'] === 'pandit_bookings') {
        if(!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'pandit') {
            echo json_encode(['status' => false]);
            exit();
        }
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE pandit_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['user']['pandit_id']]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => true, 'bookings' => $bookings]);
        exit();
    }
}

function currentUser() {
    return $_SESSION['user'] ?? null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PanditSeva - Book Pandit for Wedding & Puja</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        :root { --primary: #ff6b35; --primary-dark: #e55a2b; --dark: #2c3e50; --light: #f8f9fa; --success: #28a745; --danger: #dc3545; --warning: #ffc107; --info: #17a2b8; }
        body { background: var(--light); }
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 1rem 5%; position: sticky; top: 0; z-index: 1000; }
        .nav-container { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .logo { font-size: 1.8rem; font-weight: 800; background: linear-gradient(135deg, var(--primary), #9b59b6); -webkit-background-clip: text; background-clip: text; color: transparent; cursor: pointer; }
        .nav-links { display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap; }
        .nav-links a { text-decoration: none; color: var(--dark); font-weight: 500; cursor: pointer; }
        .btn-login, .btn-register, .btn-logout { padding: 0.5rem 1.2rem; border-radius: 50px; cursor: pointer; font-weight: 600; border: none; }
        .btn-login { background: transparent; border: 2px solid var(--primary); color: var(--primary); }
        .btn-register { background: var(--primary); color: white; }
        .btn-logout { background: var(--danger); color: white; }
        .hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 4rem 5%; text-align: center; }
        .hero h1 { font-size: 3rem; margin-bottom: 1rem; }
        .search-box { background: white; padding: 2rem; border-radius: 20px; max-width: 800px; margin: 2rem auto 0; display: flex; gap: 1rem; flex-wrap: wrap; }
        .search-box input, .search-box select { flex: 1; padding: 0.8rem; border: 1px solid #ddd; border-radius: 10px; }
        .search-btn { background: var(--primary); color: white; border: none; padding: 0.8rem 2rem; border-radius: 10px; cursor: pointer; }
        .services { padding: 4rem 5%; background: white; }
        .section-title { text-align: center; font-size: 2rem; margin-bottom: 3rem; }
        .services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1.5rem; max-width: 1200px; margin: 0 auto; }
        .service-card { text-align: center; padding: 1.5rem; background: var(--light); border-radius: 15px; cursor: pointer; transition: all 0.3s; }
        .service-card:hover { background: var(--primary); color: white; transform: translateY(-5px); }
        .service-card i { font-size: 2.5rem; margin-bottom: 1rem; }
        .pandits { padding: 4rem 5%; }
        .pandit-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 2rem; max-width: 1400px; margin: 0 auto; }
        .pandit-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.3s; }
        .pandit-card:hover { transform: translateY(-5px); }
        .pandit-img { height: 150px; background: linear-gradient(135deg, var(--primary), #764ba2); display: flex; align-items: center; justify-content: center; font-size: 4rem; }
        .pandit-info { padding: 1.5rem; }
        .pandit-name { font-size: 1.3rem; font-weight: 700; }
        .rating { color: var(--warning); margin: 0.5rem 0; }
        .details { color: #6c757d; font-size: 0.9rem; margin: 0.3rem 0; }
        .price { font-size: 1.2rem; font-weight: 700; color: var(--primary); margin: 1rem 0; }
        .book-btn { width: 100%; padding: 0.8rem; background: var(--primary); color: white; border: none; border-radius: 10px; cursor: pointer; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 2rem; border-radius: 20px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 10px; }
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: var(--dark); color: white; padding: 2rem 1rem; }
        .sidebar a { display: block; padding: 0.8rem; color: white; text-decoration: none; cursor: pointer; border-radius: 10px; margin-bottom: 0.5rem; }
        .sidebar a:hover, .sidebar a.active { background: var(--primary); }
        .main-content { flex: 1; padding: 2rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 15px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stat-card p { font-size: 2rem; font-weight: 700; color: var(--primary); }
        .toast { position: fixed; bottom: 20px; right: 20px; background: var(--success); color: white; padding: 1rem 2rem; border-radius: 10px; display: none; z-index: 3000; animation: slideIn 0.3s; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .stars { color: var(--warning); cursor: pointer; font-size: 1.5rem; }
        .stars i { margin-right: 5px; }
        .booking-card { background: white; padding: 1rem; margin: 1rem 0; border-radius: 10px; border-left: 4px solid var(--primary); }
        .status-pending { color: #ff9800; font-weight: 600; }
        .status-confirmed { color: #4caf50; font-weight: 600; }
        .status-completed { color: #2196f3; font-weight: 600; }
        .status-cancelled { color: #f44336; font-weight: 600; }
        @media (max-width: 768px) { .dashboard-container { flex-direction: column; } .sidebar { width: 100%; } }
    </style>
</head>
<body>
    <div id="app"></div>

    <script>
        let currentUser = <?php echo json_encode(currentUser()); ?>;
        let allPandits = [];
        let userBookings = [];
        let currentView = 'home';
        let selectedPandit = null;

        async function fetchData(action, data = null) {
            if(data) {
                const formData = new FormData();
                for(let key in data) {
                    formData.append(key, data[key]);
                }
                const response = await fetch(`?action=${action}`, { method: 'POST', body: formData });
                return await response.json();
            } else {
                const response = await fetch(`?action=${action}`);
                return await response.json();
            }
        }

        function showToast(message, isError = false) {
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.textContent = message;
            toast.style.background = isError ? '#dc3545' : '#28a745';
            document.body.appendChild(toast);
            toast.style.display = 'block';
            setTimeout(() => toast.remove(), 3000);
        }

        async function loadPandits() {
            const result = await fetchData('get_pandits');
            if (result.status) {
                allPandits = result.pandits;
                renderPanditGrid();
                if(allPandits.length > 0) showToast(`✅ ${allPandits.length} pandits loaded`);
            }
        }

        async function loadMyBookings() {
            const result = await fetchData('my_bookings');
            if (result.status) userBookings = result.bookings;
        }

        async function loadPanditBookings() {
            const result = await fetchData('pandit_bookings');
            if (result.status) userBookings = result.bookings;
        }

        function renderPanditGrid() {
            const grid = document.getElementById('panditGrid');
            if (!grid) return;
            if (allPandits.length === 0) {
                grid.innerHTML = '<div style="text-align:center;padding:2rem;">No pandits available. <br><br>👑 Admin Login: 9999999999 / admin123</div>';
                return;
            }
            grid.innerHTML = allPandits.map(p => `
                <div class="pandit-card" onclick="viewPandit(${p.id})">
                    <div class="pandit-img">${p.image || '🕉️'}</div>
                    <div class="pandit-info">
                        <div class="pandit-name">${escapeHtml(p.name)}</div>
                        <div class="rating">${'★'.repeat(Math.floor(p.rating))}${'☆'.repeat(5-Math.floor(p.rating))} (${p.reviews} reviews)</div>
                        <div class="details"><i class="fas fa-map-marker-alt"></i> ${p.city}</div>
                        <div class="details"><i class="fas fa-calendar"></i> ${p.experience} years experience</div>
                        <div class="details"><i class="fas fa-language"></i> ${p.languages}</div>
                        <div class="details"><i class="fas fa-tags"></i> ${p.specialization ? p.specialization.substring(0, 50) + (p.specialization.length > 50 ? '...' : '') : 'N/A'}</div>
                        <div class="price">💰 ₹${parseInt(p.price).toLocaleString()}</div>
                        <button class="book-btn" onclick="event.stopPropagation(); bookPandit(${p.id})">📅 Book Now</button>
                    </div>
                </div>
            `).join('');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function render() {
            const app = document.getElementById('app');
            if (currentView === 'home') {
                app.innerHTML = renderHome();
                loadPandits();
            } else if (currentView === 'panditDetail') {
                app.innerHTML = renderPanditDetail();
            } else if (currentView === 'booking') {
                app.innerHTML = renderBooking();
            } else if (currentView === 'userDashboard') {
                app.innerHTML = renderUserDashboard();
                loadMyBookings();
            } else if (currentView === 'panditDashboard') {
                app.innerHTML = renderPanditDashboard();
                loadPanditBookings();
            } else if (currentView === 'adminDashboard') {
                app.innerHTML = renderAdminDashboard();
                loadPandits();
            }
        }

        function renderHome() {
            return `
                <div class="navbar">
                    <div class="nav-container">
                        <div class="logo" onclick="changeView('home')">🕉️ PanditSeva</div>
                        <div class="nav-links">
                            <a onclick="changeView('home')">Home</a>
                            ${currentUser ? `<a onclick="changeView('${currentUser.role === 'pandit' ? 'panditDashboard' : 'userDashboard'}')">Dashboard</a>` : ''}
                            ${currentUser && currentUser.role === 'admin' ? `<a onclick="changeView('adminDashboard')">Admin Panel</a>` : ''}
                            ${!currentUser ? `<button class="btn-login" onclick="openLogin()">Login</button>` : ''}
                            ${!currentUser ? `<button class="btn-register" onclick="openRegister()">Register</button>` : ''}
                            ${currentUser ? `<span>👋 ${escapeHtml(currentUser.name)}</span>` : ''}
                            ${currentUser ? `<button class="btn-logout" onclick="logout()">Logout</button>` : ''}
                        </div>
                    </div>
                </div>
                <div class="hero">
                    <h1>Find Nearby Pandit for Marriage & Puja</h1>
                    <p>Book experienced pandits for Wedding, Engagement, Grih Pravesh, Mundan, and all ceremonies</p>
                    <div class="search-box">
                        <input type="text" id="searchCity" placeholder="Enter city (Delhi, Mumbai)">
                        <select id="searchService">
                            <option value="">All Services</option>
                            <option>Wedding</option><option>Engagement</option>
                            <option>Grih Pravesh</option><option>Satyanarayan</option>
                            <option>Mundan</option><option>Naamkaran</option><option>Havan</option><option>Kundli Milan</option>
                        </select>
                        <button class="search-btn" onclick="searchPandits()">🔍 Search</button>
                    </div>
                </div>
                <div class="services">
                    <h2 class="section-title">Our Services</h2>
                    <div class="services-grid">
                        <div class="service-card" onclick="filterService('Wedding')"><i class="fas fa-ring"></i><h3>Wedding</h3></div>
                        <div class="service-card" onclick="filterService('Engagement')"><i class="fas fa-handshake"></i><h3>Engagement</h3></div>
                        <div class="service-card" onclick="filterService('Grih Pravesh')"><i class="fas fa-home"></i><h3>Grih Pravesh</h3></div>
                        <div class="service-card" onclick="filterService('Satyanarayan')"><i class="fas fa-book"></i><h3>Satyanarayan</h3></div>
                        <div class="service-card" onclick="filterService('Mundan')"><i class="fas fa-child"></i><h3>Mundan</h3></div>
                        <div class="service-card" onclick="filterService('Naamkaran')"><i class="fas fa-baby"></i><h3>Naamkaran</h3></div>
                        <div class="service-card" onclick="filterService('Havan')"><i class="fas fa-fire"></i><h3>Havan</h3></div>
                        <div class="service-card" onclick="filterService('Kundli Milan')"><i class="fas fa-star"></i><h3>Kundli Milan</h3></div>
                    </div>
                </div>
                <div class="pandits">
                    <h2 class="section-title">Available Pandits Near You</h2>
                    <div class="pandit-grid" id="panditGrid">Loading...</div>
                </div>
                <div style="text-align: center; padding: 2rem; background: var(--dark); color: white;">
                    <p>© 2024 PanditSeva - Your Trusted Platform for Booking Pandits</p>
                </div>
            `;
        }

        function renderPanditDetail() {
            if (!selectedPandit) return '<div>Loading...</div>';
            const p = selectedPandit;
            return `
                <div class="navbar">
                    <div class="nav-container">
                        <div class="logo" onclick="changeView('home')">🕉️ PanditSeva</div>
                        <div class="nav-links"><a onclick="changeView('home')">← Back to Home</a></div>
                    </div>
                </div>
                <div style="max-width: 1000px; margin: 2rem auto; padding: 2rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                        <div style="background: linear-gradient(135deg, var(--primary), #764ba2); padding: 2rem; text-align: center; border-radius: 20px;">
                            <div style="font-size: 5rem;">${p.image || '🕉️'}</div>
                            <h2 style="color: white;">${escapeHtml(p.name)}</h2>
                            <div style="color: white;">${'★'.repeat(Math.floor(p.rating))} ${p.rating} (${p.reviews} reviews)</div>
                        </div>
                        <div>
                            <h3><i class="fas fa-info-circle"></i> About Pandit</h3>
                            <p>${p.about || 'Experienced pandit specializing in Vedic rituals and ceremonies.'}</p>
                            <p><i class="fas fa-map-marker-alt"></i> <strong>Location:</strong> ${p.city}</p>
                            <p><i class="fas fa-calendar"></i> <strong>Experience:</strong> ${p.experience} years</p>
                            <p><i class="fas fa-language"></i> <strong>Languages:</strong> ${p.languages}</p>
                            <p><i class="fas fa-tags"></i> <strong>Specialization:</strong> ${p.specialization}</p>
                            <div class="price" style="font-size: 1.8rem;">💰 Fees: ₹${parseInt(p.price).toLocaleString()}</div>
                            <button class="book-btn" onclick="bookPandit(${p.id})">📅 Book This Pandit</button>
                        </div>
                    </div>
                </div>
            `;
        }

        function renderBooking() {
            if (!selectedPandit) return '<div>Loading...</div>';
            if (!currentUser) {
                showToast('Please login first', true);
                setTimeout(() => changeView('home'), 1500);
                return '<div></div>';
            }
            return `
                <div class="navbar">
                    <div class="nav-container">
                        <div class="logo" onclick="changeView('home')">🕉️ PanditSeva</div>
                        <div class="nav-links"><a onclick="changeView('home')">← Back</a></div>
                    </div>
                </div>
                <div style="max-width: 500px; margin: 2rem auto; padding: 2rem; background: white; border-radius: 20px;">
                    <h2>📅 Book ${escapeHtml(selectedPandit.name)}</h2>
                    <div class="form-group"><label>Select Service *</label>
                        <select id="bookService">${selectedPandit.specialization.split(',').map(s => `<option>${s.trim()}</option>`).join('')}</select>
                    </div>
                    <div class="form-group"><label>Select Date *</label>
                        <input type="date" id="bookDate" min="${new Date().toISOString().split('T')[0]}">
                    </div>
                    <div class="form-group"><label>Select Time *</label>
                        <select id="bookTime">
                            <option>09:00 AM</option><option>11:00 AM</option>
                            <option>02:00 PM</option><option>04:00 PM</option><option>06:00 PM</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Event Address *</label>
                        <textarea id="bookAddress" rows="3" placeholder="Enter complete address"></textarea>
                    </div>
                    <div class="form-group"><label>Special Instructions</label>
                        <textarea id="bookInstructions" rows="2" placeholder="Any specific requirements"></textarea>
                    </div>
                    <div class="price">💰 Total: ₹${parseInt(selectedPandit.price).toLocaleString()}</div>
                    <button class="book-btn" onclick="confirmBooking()">Confirm Booking</button>
                </div>
            `;
        }

        function renderUserDashboard() {
            const pendingBookings = userBookings.filter(b => b.status === 'Pending');
            const confirmedBookings = userBookings.filter(b => b.status === 'Confirmed');
            const completedBookings = userBookings.filter(b => b.status === 'Completed');
            
            return `
                <div class="dashboard-container">
                    <div class="sidebar">
                        <h3>🕉️ PanditSeva</h3>
                        <a class="active" onclick="changeView('userDashboard')">📊 Dashboard</a>
                        <a onclick="changeView('home')">🔍 Find Pandits</a>
                        <a onclick="logout()">🚪 Logout</a>
                    </div>
                    <div class="main-content">
                        <h2>Welcome, ${escapeHtml(currentUser?.name)}! 👋</h2>
                        <div class="stats-grid">
                            <div class="stat-card"><h3>Total Bookings</h3><p>${userBookings.length}</p></div>
                            <div class="stat-card"><h3>Pending</h3><p>${pendingBookings.length}</p></div>
                            <div class="stat-card"><h3>Confirmed</h3><p>${confirmedBookings.length}</p></div>
                            <div class="stat-card"><h3>Completed</h3><p>${completedBookings.length}</p></div>
                        </div>
                        <h3 style="margin-top: 2rem;">📋 My Bookings</h3>
                        ${userBookings.length === 0 ? '<p>No bookings yet. <a onclick="changeView(\'home\')">Book a pandit</a></p>' : 
                            userBookings.map(b => `
                                <div class="booking-card">
                                    <p><strong>${escapeHtml(b.pandit_name)}</strong> - ${b.service}</p>
                                    <p>📅 ${b.event_date} at ${b.event_time}</p>
                                    <p>📍 ${b.address ? b.address.substring(0, 100) : 'N/A'}</p>
                                    <p>💰 Amount: ₹${parseInt(b.amount).toLocaleString()}</p>
                                    <p>Status: <span class="status-${b.status.toLowerCase()}">${b.status}</span></p>
                                    ${b.status === 'Pending' ? `<button onclick="cancelBooking(${b.id})" style="background: #dc3545; color: white; border: none; padding: 0.3rem 1rem; border-radius: 5px; margin-top:0.5rem;">Cancel Booking</button>` : ''}
                                    ${b.status === 'Completed' ? `<button onclick="showReviewForm(${b.id}, ${b.pandit_id})" style="background: #ffc107; color: black; border: none; padding: 0.3rem 1rem; border-radius: 5px; margin-top:0.5rem;">⭐ Write Review</button>` : ''}
                                </div>
                            `).join('')
                        }
                    </div>
                </div>
            `;
        }

        function renderPanditDashboard() {
            const pendingBookings = userBookings.filter(b => b.status === 'Pending');
            const confirmedBookings = userBookings.filter(b => b.status === 'Confirmed');
            const completedBookings = userBookings.filter(b => b.status === 'Completed');
            
            return `
                <div class="dashboard-container">
                    <div class="sidebar">
                        <h3>🕉️ Pandit Panel</h3>
                        <a class="active" onclick="changeView('panditDashboard')">📊 Dashboard</a>
                        <a onclick="logout()">🚪 Logout</a>
                    </div>
                    <div class="main-content">
                        <h2>Welcome, Pandit ${escapeHtml(currentUser?.name)}! 🙏</h2>
                        <div class="stats-grid">
                            <div class="stat-card"><h3>Total Earnings</h3><p>₹${completedBookings.reduce((sum, b) => sum + parseInt(b.amount), 0).toLocaleString()}</p></div>
                            <div class="stat-card"><h3>Pending Requests</h3><p>${pendingBookings.length}</p></div>
                            <div class="stat-card"><h3>Confirmed</h3><p>${confirmedBookings.length}</p></div>
                            <div class="stat-card"><h3>Completed</h3><p>${completedBookings.length}</p></div>
                        </div>
                        <h3>📋 Pending Booking Requests</h3>
                        ${pendingBookings.length === 0 ? '<p>No pending requests</p>' :
                            pendingBookings.map(b => `
                                <div class="booking-card">
                                    <p><strong>${escapeHtml(b.user_name)}</strong> - ${b.service}</p>
                                    <p>📅 ${b.event_date} at ${b.event_time}</p>
                                    <p>📍 ${b.address ? b.address.substring(0, 100) : 'N/A'}</p>
                                    <p>💰 Amount: ₹${parseInt(b.amount).toLocaleString()}</p>
                                    <button onclick="acceptBooking(${b.id})" style="background: #28a745; color: white; border: none; padding: 0.5rem 1rem; margin-right: 0.5rem; border-radius: 5px; margin-top:0.5rem;">✓ Accept</button>
                                    <button onclick="rejectBooking(${b.id})" style="background: #dc3545; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; margin-top:0.5rem;">✗ Reject</button>
                                </div>
                            `).join('')
                        }
                        <h3 style="margin-top: 2rem;">📋 Confirmed Bookings</h3>
                        ${confirmedBookings.length === 0 ? '<p>No confirmed bookings</p>' :
                            confirmedBookings.map(b => `
                                <div class="booking-card">
                                    <p><strong>${escapeHtml(b.user_name)}</strong> - ${b.service}</p>
                                    <p>📅 ${b.event_date} at ${b.event_time}</p>
                                    <p>📍 ${b.address ? b.address.substring(0, 100) : 'N/A'}</p>
                                    <button onclick="completeBooking(${b.id})" style="background: #007bff; color: white; border: none; padding: 0.3rem 1rem; border-radius: 5px;">✓ Mark Completed</button>
                                </div>
                            `).join('')
                        }
                    </div>
                </div>
            `;
        }

        function renderAdminDashboard() {
            return `
                <div class="dashboard-container">
                    <div class="sidebar">
                        <h3>🕉️ Admin Panel</h3>
                        <a class="active" onclick="changeView('adminDashboard')">📊 Dashboard</a>
                        <a onclick="showAddPanditForm()">➕ Add New Pandit</a>
                        <a onclick="logout()">🚪 Logout</a>
                    </div>
                    <div class="main-content">
                        <h2>Admin Dashboard</h2>
                        <div class="stats-grid">
                            <div class="stat-card"><h3>Total Pandits</h3><p>${allPandits.length}</p></div>
                        </div>
                        <div style="background: white; padding: 1.5rem; border-radius: 15px; margin-top: 2rem;">
                            <h3>📋 All Pandits List</h3>
                            <table style="width:100%; border-collapse: collapse;">
                                <tr style="background: #f0f0f0;">
                                    <th style="padding: 10px;">ID</th><th style="padding: 10px;">Name</th>
                                    <th style="padding: 10px;">City</th><th style="padding: 10px;">Price</th>
                                    <th style="padding: 10px;">Rating</th>
                                </tr>
                                ${allPandits.map(p => `
                                    <tr style="border-bottom: 1px solid #ddd;">
                                        <td style="padding: 10px;">${p.id}</td>
                                        <td style="padding: 10px;">${escapeHtml(p.name)}</td>
                                        <td style="padding: 10px;">${p.city}</td>
                                        <td style="padding: 10px;">₹${parseInt(p.price).toLocaleString()}</td>
                                        <td style="padding: 10px;">${p.rating}★ (${p.reviews})</td>
                                    </tr>
                                `).join('')}
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }

        function showReviewForm(bookingId, panditId) {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.style.display = 'flex';
            modal.innerHTML = `
                <div class="modal-content">
                    <h2>⭐ Write a Review</h2>
                    <div class="form-group">
                        <label>Rating</label>
                        <div class="stars" id="ratingStars">
                            <i class="far fa-star" data-rating="1"></i>
                            <i class="far fa-star" data-rating="2"></i>
                            <i class="far fa-star" data-rating="3"></i>
                            <i class="far fa-star" data-rating="4"></i>
                            <i class="far fa-star" data-rating="5"></i>
                        </div>
                        <input type="hidden" id="reviewRating">
                    </div>
                    <div class="form-group"><label>Your Review</label><textarea id="reviewComment" rows="4" placeholder="Share your experience with this pandit..."></textarea></div>
                    <button class="book-btn" onclick="submitReview(${bookingId}, ${panditId})">Submit Review</button>
                    <button onclick="closeModal(this)" style="margin-top:1rem;">Cancel</button>
                </div>
            `;
            document.body.appendChild(modal);
            
            let stars = modal.querySelectorAll('#ratingStars i');
            stars.forEach(star => {
                star.onclick = function() {
                    let rating = this.getAttribute('data-rating');
                    document.getElementById('reviewRating').value = rating;
                    stars.forEach((s, i) => { i < rating ? s.className = 'fas fa-star' : s.className = 'far fa-star'; });
                };
            });
        }

        window.submitReview = async function(bookingId, panditId) {
            const rating = document.getElementById('reviewRating').value;
            const comment = document.getElementById('reviewComment').value;
            if (!rating) { showToast('Please select a rating', true); return; }
            
            const formData = new FormData();
            formData.append('action', 'add_review');
            formData.append('booking_id', bookingId);
            formData.append('pandit_id', panditId);
            formData.append('rating', rating);
            formData.append('comment', comment);
            
            const response = await fetch('', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.status) { 
                showToast('✅ Review submitted successfully!'); 
                closeModal(document.querySelector('.modal')); 
                loadMyBookings();
                loadPandits();
            } else { 
                showToast(result.message, true); 
            }
        };

        function showAddPanditForm() {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.style.display = 'flex';
            modal.innerHTML = `
                <div class="modal-content">
                    <h2>➕ Add New Pandit</h2>
                    <div class="form-group"><label>Full Name *</label><input type="text" id="newName"></div>
                    <div class="form-group"><label>Mobile *</label><input type="tel" id="newMobile"></div>
                    <div class="form-group"><label>Email</label><input type="email" id="newEmail"></div>
                    <div class="form-group"><label>City *</label><input type="text" id="newCity"></div>
                    <div class="form-group"><label>Experience (years) *</label><input type="number" id="newExp"></div>
                    <div class="form-group"><label>Languages *</label><input type="text" id="newLang" placeholder="Hindi, Sanskrit, English"></div>
                    <div class="form-group"><label>Specialization *</label><input type="text" id="newSpec" placeholder="Wedding, Havan, Grih Pravesh"></div>
                    <div class="form-group"><label>Price (₹) *</label><input type="number" id="newPrice"></div>
                    <div class="form-group"><label>About</label><textarea id="newAbout" rows="3" placeholder="About the pandit"></textarea></div>
                    <div class="form-group"><label>Image Emoji</label><input type="text" id="newImage" value="🕉️"></div>
                    <button class="book-btn" onclick="addNewPandit()">Add Pandit</button>
                    <button onclick="closeModal(this)" style="margin-top:1rem;">Cancel</button>
                </div>
            `;
            document.body.appendChild(modal);
        }

        window.addNewPandit = async function() {
            const formData = new FormData();
            formData.append('action', 'add_pandit');
            formData.append('name', document.getElementById('newName').value);
            formData.append('mobile', document.getElementById('newMobile').value);
            formData.append('email', document.getElementById('newEmail').value);
            formData.append('city', document.getElementById('newCity').value);
            formData.append('experience', document.getElementById('newExp').value);
            formData.append('languages', document.getElementById('newLang').value);
            formData.append('specialization', document.getElementById('newSpec').value);
            formData.append('price', document.getElementById('newPrice').value);
            formData.append('about', document.getElementById('newAbout').value);
            formData.append('image', document.getElementById('newImage').value);
            
            const response = await fetch('', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.status) { 
                showToast('✅ Pandit added successfully!'); 
                closeModal(document.querySelector('.modal')); 
                loadPandits(); 
                render(); 
            } else { 
                showToast(result.message, true); 
            }
        };

        window.searchPandits = function() {
            const city = document.getElementById('searchCity')?.value.toLowerCase();
            const service = document.getElementById('searchService')?.value;
            let filtered = [...allPandits];
            if (city) filtered = filtered.filter(p => p.city?.toLowerCase().includes(city));
            if (service) filtered = filtered.filter(p => p.specialization?.includes(service));
            allPandits = filtered;
            renderPanditGrid();
            if(filtered.length === 0) showToast('No pandits found', true);
        };

        window.filterService = function(service) {
            loadPandits().then(() => {
                allPandits = allPandits.filter(p => p.specialization?.includes(service));
                renderPanditGrid();
                showToast(`Showing ${service} pandits`);
            });
        };

        window.changeView = function(view) { currentView = view; render(); };
        window.viewPandit = function(id) { selectedPandit = allPandits.find(p => p.id === id); currentView = 'panditDetail'; render(); };
        
        window.bookPandit = function(id) {
            if (!currentUser) { showToast('Please login first', true); openLogin(); return; }
            selectedPandit = allPandits.find(p => p.id === id);
            currentView = 'booking';
            render();
        };
        
        window.confirmBooking = async function() {
            const date = document.getElementById('bookDate')?.value;
            const address = document.getElementById('bookAddress')?.value;
            
            if (!date) { showToast('Please select date', true); return; }
            if (!address) { showToast('Please enter address', true); return; }
            
            const formData = new FormData();
            formData.append('action', 'create_booking');
            formData.append('pandit_id', selectedPandit.id);
            formData.append('pandit_name', selectedPandit.name);
            formData.append('service', document.getElementById('bookService').value);
            formData.append('event_date', date);
            formData.append('event_time', document.getElementById('bookTime').value);
            formData.append('address', address);
            formData.append('instructions', document.getElementById('bookInstructions').value);
            formData.append('amount', selectedPandit.price);
            
            const response = await fetch('', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.status) { 
                showToast('✅ Booking created successfully!'); 
                currentView = 'userDashboard'; 
                render(); 
            } else { 
                showToast(result.message, true); 
            }
        };
        
        window.cancelBooking = async function(id) { 
            if (confirm('Cancel this booking?')) { 
                const formData = new FormData();
                formData.append('action', 'update_booking');
                formData.append('booking_id', id);
                formData.append('status', 'Cancelled');
                await fetch('', { method: 'POST', body: formData });
                showToast('Booking cancelled'); 
                loadMyBookings(); 
                render(); 
            } 
        };
        
        window.acceptBooking = async function(id) { 
            if (confirm('Accept this booking request?')) { 
                const formData = new FormData();
                formData.append('action', 'update_booking');
                formData.append('booking_id', id);
                formData.append('status', 'Confirmed');
                await fetch('', { method: 'POST', body: formData });
                showToast('Booking accepted'); 
                loadPanditBookings(); 
                render(); 
            } 
        };
        
        window.rejectBooking = async function(id) { 
            if (confirm('Reject this booking request?')) { 
                const formData = new FormData();
                formData.append('action', 'update_booking');
                formData.append('booking_id', id);
                formData.append('status', 'Cancelled');
                await fetch('', { method: 'POST', body: formData });
                showToast('Booking rejected'); 
                loadPanditBookings(); 
                render(); 
            } 
        };
        
        window.completeBooking = async function(id) { 
            if (confirm('Mark this booking as completed?')) { 
                const formData = new FormData();
                formData.append('action', 'update_booking');
                formData.append('booking_id', id);
                formData.append('status', 'Completed');
                await fetch('', { method: 'POST', body: formData });
                showToast('Booking marked as completed'); 
                loadPanditBookings(); 
                render(); 
            } 
        };
        
        window.logout = async function() { 
            const formData = new FormData();
            formData.append('action', 'logout');
            await fetch('', { method: 'POST', body: formData });
            currentUser = null; 
            window.location.reload(); 
        };

        async function openLogin() {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.style.display = 'flex';
            modal.innerHTML = `
                <div class="modal-content">
                    <h2>Login to PanditSeva</h2>
                    <div class="form-group"><label>Mobile Number</label><input type="tel" id="loginMobile"></div>
                    <div class="form-group"><label>Password</label><input type="password" id="loginPassword"></div>
                    <div class="form-group"><small>🔑 Demo Logins:<br>👤 User: 9876543213 / password<br>🙏 Pandit: 9876543210 / password<br>👑 Admin: 9999999999 / admin123</small></div>
                    <button class="book-btn" onclick="handleLogin()">Login</button>
                    <button onclick="closeModal(this)" style="margin-top:1rem;">Cancel</button>
                </div>
            `;
            document.body.appendChild(modal);
        }

        async function openRegister() {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.style.display = 'flex';
            modal.innerHTML = `
                <div class="modal-content">
                    <h2>Register with PanditSeva</h2>
                    <div class="form-group"><label>Full Name *</label><input type="text" id="regName"></div>
                    <div class="form-group"><label>Mobile Number *</label><input type="tel" id="regMobile"></div>
                    <div class="form-group"><label>Email</label><input type="email" id="regEmail"></div>
                    <div class="form-group"><label>Register as *</label><select id="regRole"><option value="user">User (Book Pandits)</option><option value="pandit">Pandit (Offer Services)</option></select></div>
                    <div id="panditFields" style="display:none">
                        <div class="form-group"><label>Experience (years)</label><input type="number" id="regExp"></div>
                        <div class="form-group"><label>City</label><input type="text" id="regCity"></div>
                        <div class="form-group"><label>Languages</label><input type="text" id="regLang" placeholder="Hindi, Sanskrit"></div>
                        <div class="form-group"><label>Specialization</label><input type="text" id="regSpec" placeholder="Wedding, Havan"></div>
                        <div class="form-group"><label>Price (₹)</label><input type="number" id="regPrice"></div>
                    </div>
                    <div class="form-group"><label>Password *</label><input type="password" id="regPass"></div>
                    <button class="book-btn" onclick="handleRegister()">Register</button>
                    <button onclick="closeModal(this)">Cancel</button>
                </div>
            `;
            document.body.appendChild(modal);
            document.getElementById('regRole').onchange = function() {
                document.getElementById('panditFields').style.display = this.value === 'pandit' ? 'block' : 'none';
            };
        }

        window.handleLogin = async function() {
            const mobile = document.getElementById('loginMobile').value;
            const password = document.getElementById('loginPassword').value;
            if(!mobile || !password) { showToast('Please enter mobile and password', true); return; }
            
            const formData = new FormData();
            formData.append('action', 'login');
            formData.append('mobile', mobile);
            formData.append('password', password);
            
            const response = await fetch('', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.status) { 
                currentUser = result.user; 
                showToast('Login successful'); 
                closeModal(document.querySelector('.modal')); 
                window.location.reload(); 
            } else { 
                showToast(result.message, true); 
            }
        };

        window.handleRegister = async function() {
            const name = document.getElementById('regName').value;
            const mobile = document.getElementById('regMobile').value;
            const password = document.getElementById('regPass').value;
            
            if(!name || !mobile || !password) { showToast('Please fill all required fields', true); return; }
            
            const formData = new FormData();
            formData.append('action', 'register');
            formData.append('name', name);
            formData.append('mobile', mobile);
            formData.append('email', document.getElementById('regEmail').value);
            formData.append('role', document.getElementById('regRole').value);
            formData.append('password', password);
            
            if(document.getElementById('regRole').value === 'pandit') {
                formData.append('experience', document.getElementById('regExp').value);
                formData.append('city', document.getElementById('regCity').value);
                formData.append('languages', document.getElementById('regLang').value);
                formData.append('specialization', document.getElementById('regSpec').value);
                formData.append('price', document.getElementById('regPrice').value);
            }
            
            const response = await fetch('', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.status) { 
                showToast(result.message); 
                closeModal(document.querySelector('.modal')); 
                openLogin(); 
            } else { 
                showToast(result.message, true); 
            }
        };

        window.closeModal = function(btn) { btn.closest('.modal')?.remove(); };
        window.openLogin = openLogin;
        window.openRegister = openRegister;

        render();
    </script>
</body>
</html>