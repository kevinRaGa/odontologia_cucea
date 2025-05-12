<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <link rel="stylesheet" href="css/sidebar.module.css?v=1.3">
    </head>
    <body>
        <script src="" async defer></script>
    </body>
</html>

<div class="sidebar">
    <div class="sidebar-header" id="sidebarHeader">
        <img src="https://f365.cucea.udg.mx/images/logo-cucea.png" 
             alt="CUCEA Logo" 
             class="sidebar-logo">
    </div>
    
    <div class="sidebar-menu">
        <a href="admin.php" class="menu-item">
            <i class="fas fa-calendar-alt"></i>
            <span>Citas</span>
            <span class="tooltip">Citas</span>
        </a>
        
        <!-- Pacientes Section -->
        <a href="#" class="menu-item has-submenu pacientes-menu">
            <i class="fas fa-users"></i>
            <span>Pacientes</span>
            <span class="tooltip">Pacientes</span>
        </a>
        <div class="submenu">
            <a href="pacientes.php" class="menu-item submenu-item">
                <i class="fas fa-list"></i>
                <span>Lista de Pacientes</span>
            </a>
            <a href="index.php" class="menu-item submenu-item">
                <i class="fas fa-user-plus"></i>
                <span>Nuevo Paciente</span>
            </a>
        </div>
        
        <!-- Odontólogos Section -->
        <a href="#" class="menu-item has-submenu odontologos-menu">
            <i class="fas fa-user-md"></i>
            <span>Odontólogos</span>
            <span class="tooltip">Odontólogos</span>
        </a>
        <div class="submenu">
            <a href="odontologos.php" class="menu-item submenu-item">
                <i class="fas fa-list"></i>
                <span>Lista de Odontólogos</span>
            </a>
            <?php if ($is_admin): ?>
                <a href="agregar_odontologo.php" class="menu-item submenu-item">
                    <i class="fas fa-user-plus"></i>
                    <span>Crear Odontólogo</span>
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Inventario Section -->
        <a href="#" class="menu-item has-submenu inventario-menu">
            <i class="fas fa-boxes"></i>
            <span>Inventario</span>
            <span class="tooltip">Inventario</span>
        </a>
        <div class="submenu">
            <a href="inventory_list.php" class="menu-item submenu-item">
                <i class="fas fa-list"></i>
                <span>Lista de Inventario</span>
            </a>
            <a href="nuevo_item.php" class="menu-item submenu-item">
                <i class="fas fa-plus"></i>
                <span>Producto Nuevo</span>
            </a>
        </div>
    </div>

    <div class="sidebar-footer">
        <a href="configuracion.php" class="menu-item">
            <i class="fas fa-cog"></i>
            <span>Configuración</span>
            <span class="tooltip">Configuración</span>
        </a>
        <a href="logout.php" class="menu-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Cerrar Sesión</span>
            <span class="tooltip">Cerrar Sesión</span>
        </a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.querySelector('.sidebar');
        const sidebarHeader = document.getElementById('sidebarHeader');
        const mobileMenuToggle = document.getElementById('menuToggle');
        const body = document.body;

        // Check localStorage for saved state
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        // Apply saved state
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            body.classList.add('sidebar-collapsed');
        }

        // Toggle sidebar when clicking header (logo area)
        sidebarHeader.addEventListener('click', function(e) {
            // Only toggle if not on mobile (where we have the hamburger menu)
            if (window.innerWidth > 992) {
                e.preventDefault();
                sidebar.classList.toggle('collapsed');
                body.classList.toggle('sidebar-collapsed');
                
                // Save state
                localStorage.setItem('sidebarCollapsed', 
                    sidebar.classList.contains('collapsed'));
            }
        });

        // Handle menu items with double click navigation
        function setupMenuBehavior(menuClass, redirectUrl) {
            const menu = document.querySelector(`.${menuClass}`);
            if (!menu) return;
            
            let clickCount = 0;
            let timer = null;

            menu.addEventListener('click', function(e) {
                e.preventDefault();
                clickCount++;
                
                if (clickCount === 1) {
                    timer = setTimeout(() => {
                        // Single click behavior - toggle submenu if not collapsed
                        if (!sidebar.classList.contains('collapsed')) {
                            this.classList.toggle('active');
                            const submenu = this.nextElementSibling;
                            if (submenu) {
                                submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
                            }
                        } else {
                            // If collapsed, redirect on single click
                            window.location.href = redirectUrl;
                        }
                        clickCount = 0;
                    }, 300);
                } else if (clickCount === 2) {
                    // Double click behavior - redirect
                    clearTimeout(timer);
                    window.location.href = redirectUrl;
                    clickCount = 0;
                }
            });
        }

        // Setup behaviors for each menu
        setupMenuBehavior('pacientes-menu', 'pacientes.php');
        setupMenuBehavior('odontologos-menu', 'odontologos.php');
        setupMenuBehavior('inventario-menu', 'inventory_list.php');

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 992 && 
                !event.target.closest('.sidebar') && 
                !event.target.closest('#menuToggle')) {
                sidebar.classList.remove('active');
            }
        });
    });
</script>