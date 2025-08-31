<?php
require_once __DIR__ . '/../config/Session.php';
Session::start();

// Current page from query string
$current_page = $_GET['page'] ?? 'dashboard';
?>
<aside class="sidebar">
    <div class="sidebar-upper">
        <h2 class="logo">MLUC Sentinel</h2>
        <nav>
            <ul>
                <li><a href="admin.php?page=dashboard" class="<?= $current_page=='dashboard' ? 'active' : '' ?>">📊 Dashboard</a></li>
                <li><a href="admin.php?page=analytics" class="<?= $current_page=='analytics' ? 'active' : '' ?>">📈 Analytics</a></li>
                <li><a href="admin.php?page=users" class="<?= $current_page=='users' ? 'active' : '' ?>">👥 Users</a></li>
                <li>
                    <a href="#">📝 Register ▾</a>
                    <ul class="submenu">
                        <li><a href="admin.php?page=register/student" class="<?= $current_page=='register/student' ? 'active' : '' ?>">Student</a></li>
                        <li><a href="admin.php?page=register/personnel" class="<?= $current_page=='register/personnel' ? 'active' : '' ?>">Personnel</a></li>
                        <li><a href="admin.php?page=register/parent" class="<?= $current_page=='register/parent' ? 'active' : '' ?>">Parent / Guardian</a></li>
                        <li><a href="admin.php?page=register/official" class="<?= $current_page=='register/official' ? 'active' : '' ?>">Student Official</a></li>
                        <li><a href="admin.php?page=register/admin" class="<?= $current_page=='register/admin' ? 'active' : '' ?>">Admin</a></li>
                    </ul>
                </li>
                <li><a href="admin.php?page=reports" class="<?= $current_page=='reports' ? 'active' : '' ?>">🚨 Reports</a></li>
            </ul>
        </nav>
    </div>

    <div class="sidebar-lower">
        <ul>
            <li><a href="admin.php?page=profile" class="<?= $current_page=='profile' ? 'active' : '' ?>">👤 Profile</a></li>
            <li><a href="admin.php?page=settings" class="<?= $current_page=='settings' ? 'active' : '' ?>">⚙️ Settings</a></li>
            <li><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </div>
</aside>

<style>
/* Sidebar styles */
.sidebar {
    width: 240px;
    height: 100vh;
    background: #162051;
    color: #fff;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: fixed;
    top: 0; left: 0;
    padding: 1rem 0;
}

.sidebar .logo {
    text-align: center;
    font-size: 1.3rem;
    font-weight: bold;
    margin-bottom: 1rem;
}

.sidebar nav ul,
.sidebar-lower ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar nav ul li,
.sidebar-lower ul li {
    margin: .25rem 0;
}

.sidebar a {
    display: block;
    padding: .75rem 1rem;
    text-decoration: none;
    color: #ddd;
    font-weight: 500;
    transition: background .2s, color .2s;
}

.sidebar a:hover,
.sidebar a.active {
    background: #0f1530;
    color: #fff;
}

.submenu {
    display: none;
    margin-left: 1rem;
    background: #1e2a54;
    border-left: 2px solid #0f1530;
}

.sidebar li:hover > .submenu {
    display: block;
}

.sidebar-lower {
    border-top: 1px solid rgba(255,255,255,.2);
    padding-top: .5rem;
}
</style>
