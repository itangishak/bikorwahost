<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIKORWA SHOP - <?php echo $page_title ?? 'Gestion de Bar'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <style>
        /* Base Styles */
        :root {
            --primary: #4e73df;
            --primary-light: #f8f9fc;
            --secondary: #858796;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #5a5c69;
            --sidebar-width: 240px;
            --topbar-height: 60px;
            --shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        body {
            background-color: var(--light);
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background-color: white;
            box-shadow: var(--shadow);
            z-index: 100;
            transition: all 0.3s;
            overflow-y: auto;
        }
        
        .sidebar-brand {
            height: var(--topbar-height);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            background-color: white;
            color: var(--primary);
            font-weight: 700;
            font-size: 1.2rem;
            border-bottom: 1px solid #eaecf4;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-item {
            position: relative;
            display: flex;
            align-items: center;
            padding: 0.8rem 1.5rem;
            color: var(--secondary);
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .sidebar-item > i:first-child {
            flex: 0 0 24px;
            text-align: center;
            margin-right: 10px;
            font-size: 0.9rem;
        }
        
        .sidebar-item > span {
            flex: 1;
            text-align: left;
        }
        
        .sidebar-item > .fa-chevron-down {
            flex: 0 0 16px;
            text-align: center;
            margin-left: 10px;
            font-size: 0.8rem;
        }
        
        .sidebar-item:hover, .sidebar-item.active {
            color: var(--primary);
            background-color: var(--primary-light);
            border-left: 4px solid var(--primary);
            padding-left: calc(1.5rem - 4px);
        }
        
        .sidebar-submenu {
            padding-left: 1.5rem;
            background-color: rgba(0,0,0,0.02);
        }
        
        .sidebar-subitem {
            display: flex;
            align-items: center;
            padding: 0.6rem 1.5rem;
            color: var(--secondary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .sidebar-subitem:hover {
            color: var(--primary);
            background-color: var(--primary-light);
        }
        
        .sidebar-subitem i {
            flex: 0 0 20px;
            text-align: center;
            margin-right: 10px;
            font-size: 0.8rem;
        }
        
        .sidebar-divider {
            border-top: 1px solid #eaecf4;
            margin: 1rem 0;
        }
        
        .sidebar-heading {
            font-size: 0.65rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--secondary);
            padding: 0 1.5rem;
            margin-bottom: 0.5rem;
            margin-top: 1rem;
        }
        
        /* Topbar Styles */
        .topbar {
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            height: var(--topbar-height);
            background-color: white;
            box-shadow: var(--shadow);
            z-index: 99;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
        }
        
        .topbar-search {
            width: 300px;
        }
        
        .topbar-divider {
            width: 0;
            height: 2rem;
            border-right: 1px solid #e3e6f0;
            margin: 0 1rem;
        }
        
        .topbar-user {
            display: flex;
            align-items: center;
        }
        
        .topbar-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #eaecf4;
        }
        
        .topbar-user-name {
            margin-left: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--topbar-height);
            padding: 1.5rem;
        }
        
        /* Dashboard Cards */
        .dashboard-card {
            border-radius: 0.5rem;
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .dashboard-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.3rem 2rem 0 rgba(58, 59, 69, 0.15);
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            :root {
                --sidebar-width: 0px;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
                width: 240px;
            }
            
            .topbar {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-store me-2"></i>
            <span><?php echo htmlspecialchars(APP_NAME); ?></span>
        </div>
        
        <div class="sidebar-menu">
            <?php
            // Get user role from session for role-based menu access
            $user_role = $_SESSION['user_role'] ?? 'receptionniste';
            
            // Determine correct dashboard URL based on role
            $dashboard_url = ($user_role === 'receptionniste')
                ? BASE_URL . '/src/views/dashboard/receptionniste.php'
                : BASE_URL . '/src/views/dashboard/index.php';
            ?>
            
            <a href="<?php echo $dashboard_url; ?>" class="sidebar-item <?php echo $active_page == 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Tableau de Bord</span>
            </a>
            
            <?php if ($user_role === 'gestionnaire'): ?>
            <!-- Gestion des Ventes (gestionnaire only) -->
            <div class="sidebar-dropdown">
                <a href="#" class="sidebar-item <?php echo $active_page == 'ventes' ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#venteSubMenu" aria-expanded="false">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Gestion des Ventes</span>
                    <i class="fas fa-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo $active_page == 'ventes' ? 'show' : ''; ?>" id="venteSubMenu">
                    <div class="sidebar-submenu">
                        <a href="<?php echo BASE_URL; ?>/src/views/ventes/nouvelle.php" class="sidebar-subitem">
                            <i class="fas fa-plus-circle"></i>
                            <span>Nouvelle Vente</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/src/views/ventes/index.php" class="sidebar-subitem">
                            <i class="fas fa-history"></i>
                            <span>Historique des Ventes</span>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Gestion des Stocks -->
            <div class="sidebar-dropdown">
                <a href="#" class="sidebar-item <?php echo $active_page == 'stock' ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#stockSubMenu" aria-expanded="false">
                    <i class="fas fa-boxes"></i>
                    <span>Gestion des Stocks</span>
                    <i class="fas fa-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo $active_page == 'stock' ? 'show' : ''; ?>" id="stockSubMenu">
                    <div class="sidebar-submenu">
                        <a href="<?php echo BASE_URL; ?>/src/views/stock/inventaire.php" class="sidebar-subitem">
                            <i class="fas fa-clipboard-list"></i>
                            <span>Inventaire</span>
                        </a>
                        <?php if ($user_role === 'gestionnaire'): ?>
                        <a href="<?php echo BASE_URL; ?>/src/views/stock/ajustement.php" class="sidebar-subitem">
                            <i class="fas fa-sliders-h"></i>
                            <span>Ajustement de Stock</span>
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL; ?>/src/views/stock/historique_approvisionnement.php" class="sidebar-subitem">
                            <i class="fas fa-history"></i>
                            <span>Historique d'approvisionnement</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <?php if ($user_role === 'gestionnaire'): ?>
            <!-- Gestion des Dettes (gestionnaire only) -->
            <a href="<?php echo BASE_URL; ?>/src/views/dettes/index.php" class="sidebar-item <?php echo $active_page == 'dettes' ? 'active' : ''; ?>">
                <i class="fas fa-money-check-alt"></i>
                <span>Gestion des Dettes</span>
            </a>
            
            <!-- Gestion des Employés (gestionnaire only) -->
            <div class="sidebar-dropdown">
                <a href="#" class="sidebar-item <?php echo $active_page == 'employes' ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#employesSubMenu" aria-expanded="false">
                    <i class="fas fa-users"></i>
                    <span>Gestion des Employés</span>
                    <i class="fas fa-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo $active_page == 'employes' ? 'show' : ''; ?>" id="employesSubMenu">
                    <div class="sidebar-submenu">
                        <a href="<?php echo BASE_URL; ?>/src/views/employes/liste.php" class="sidebar-subitem">
                            <i class="fas fa-list"></i>
                            <span>Liste du Personnel</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/src/views/employes/paiement.php" class="sidebar-subitem">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Paiement</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/src/views/employes/utilisateurs.php" class="sidebar-subitem">
                            <i class="fas fa-user-cog"></i>
                            <span>Utilisateurs</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Gestion des Dépenses (gestionnaire only) -->
            <div class="sidebar-dropdown">
                <a href="#" class="sidebar-item <?php echo in_array($active_page, ['depenses-jour', 'depenses-historique']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#depensesSubMenu" aria-expanded="false">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Gestion des Dépenses</span>
                    <i class="fas fa-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo in_array($active_page, ['depenses-jour', 'depenses-historique']) ? 'show' : ''; ?>" id="depensesSubMenu">
                    <a href="../depenses/jour.php" class="sidebar-item <?php echo $active_page == 'depenses-jour' ? 'active' : ''; ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Dépenses du Jour</span>
                    </a>
                    <a href="../depenses/historique.php" class="sidebar-item <?php echo $active_page == 'depenses-historique' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i>
                        <span>Historique des Dépenses</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Gestion des Clients -->
            <a href="<?php echo BASE_URL; ?>/src/views/clients/index.php" class="sidebar-item <?php echo $active_page == 'clients' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Gestion des Clients</span>
            </a>
            
            <?php if ($user_role === 'gestionnaire'): ?>
            <!-- Rapports et Analyse (gestionnaire only) -->
            <div class="sidebar-dropdown">
                <a href="#" class="sidebar-item <?php echo $active_page == 'rapports' ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#rapportsSubMenu" aria-expanded="false">
                    <i class="fas fa-chart-bar"></i>
                    <span>Rapports et Analyses</span>
                    <i class="fas fa-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo $active_page == 'rapports' ? 'show' : ''; ?>" id="rapportsSubMenu">
                    <div class="sidebar-submenu">
                        <a href="<?php echo BASE_URL; ?>/src/views/rapports/rapports_ventes.php" class="sidebar-subitem">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <span>Analyse des Ventes</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/src/views/rapports/rapports_inventaire.php" class="sidebar-subitem">
                            <i class="fas fa-warehouse"></i>
                            <span>Suivi des Stocks</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/src/views/rapports/rapports_personnel.php" class="sidebar-subitem">
                            <i class="fas fa-user-tie"></i>
                            <span>Performance du Personnel</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Paramètres (gestionnaire only) -->
            <a href="#" class="sidebar-item <?php echo $active_page == 'parametres' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Paramètres</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Topbar -->
    <div class="topbar">
        <button class="btn btn-link d-md-none sidebar-toggler" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="topbar-search d-none d-md-block">
            <div class="input-group">
                <input type="text" class="form-control bg-light border-0 small" placeholder="Rechercher...">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="button">
                        <i class="fas fa-search fa-sm"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <div class="d-flex align-items-center">
            <div class="position-relative">
                <a href="#" class="btn btn-light position-relative me-3">
                    <i class="fas fa-bell"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        1
                    </span>
                </a>
            </div>
            
            <div class="topbar-divider"></div>
            
            <div class="dropdown">
                <a class="dropdown-toggle text-decoration-none" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="topbar-user">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'User'); ?>&background=4e73df&color=fff" alt="User Avatar" class="topbar-user-avatar">
                        <div class="ms-2 d-none d-lg-inline">
                            <div class="topbar-user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($_SESSION['user_role'] ?? 'Role'); ?></div>
                        </div>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" style="display: none;">
                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/src/views/parametres/profil.php"><i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Paramètres du Compte</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/src/views/auth/logout.php"><i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i> Se Déconnecter</a></li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
    
    <!-- Dropdown Fix Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var dropdownToggle = document.querySelector('.topbar .dropdown-toggle');
        var dropdownMenu = document.querySelector('.topbar .dropdown-menu');
        
        if (dropdownToggle && dropdownMenu) {
            // Ensure menu is hidden initially
            dropdownMenu.style.display = 'none';
            
            dropdownToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Toggle display
                if (dropdownMenu.style.display === 'none') {
                    dropdownMenu.style.display = 'block';
                } else {
                    dropdownMenu.style.display = 'none';
                }
            });
            
            // Close when clicking outside
            document.addEventListener('click', function() {
                dropdownMenu.style.display = 'none';
            });
            
            // Prevent clicks inside menu from closing it
            dropdownMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    });
    </script>
