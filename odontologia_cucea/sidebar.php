<style>
    /* Sidebar Styles */
    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        height: 100vh;
        width: 250px;
        background: #2c3e50;
        display: flex;
        flex-direction: column;
        z-index: 1000;
    }

    .sidebar-header {
        padding: 25px;
        text-align: center;
        border-bottom: 1px solid #34495e;
    }

    .sidebar-logo {
        max-width: 100%;
        height: auto;
    }

    .sidebar-menu {
        flex: 1;
        padding: 20px 0;
        overflow-y: auto;
    }

    .menu-item {
        display: flex;
        align-items: center;
        color: white;
        text-decoration: none;
        padding: 15px 25px;
        transition: all 0.3s;
        border-left: 4px solid transparent;
    }

    .menu-item:hover {
        background: #3498db;
    }

    .menu-item i {
        font-size: 1.2rem;
        margin-right: 15px;
        width: 25px;
    }

    .sidebar-footer {
        border-top: 1px solid #34495e;
        padding: 15px 0;
    }

    /* Expanded Menu Styles */
    .submenu {
        display: none;
        background-color: rgba(0,0,0,0.1);
    }

    .submenu-item {
        padding: 12px 25px 12px 60px !important;
        font-size: 0.9rem;
        border-left: 4px solid #3498db;
    }

    .submenu-item i {
        font-size: 1rem;
    }

    .menu-item.active {
        background: #34495e;
        border-left: 4px solid #3498db;
    }

    .menu-item.has-submenu::after {
        content: '\f078';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        margin-left: auto;
        transition: transform 0.3s;
    }

    .menu-item.has-submenu.active::after {
        transform: rotate(180deg);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle pacientes menu
        const pacientesMenu = document.querySelector('.pacientes-menu');
        pacientesMenu.addEventListener('click', function(e) {
            e.preventDefault();
            this.classList.toggle('active');
            const submenu = this.nextElementSibling;
            submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
        });
        
        // Handle odontologos menu
        const odontologosMenu = document.querySelector('.odontologos-menu');
        odontologosMenu.addEventListener('click', function(e) {
            e.preventDefault();
            this.classList.toggle('active');
            const submenu = this.nextElementSibling;
            submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
        });
        // Handle inventario menu
        const inventarioMenu = document.querySelector('.inventario-menu');
        inventarioMenu.addEventListener('click', function(e) {
            e.preventDefault();
            this.classList.toggle('active');
            const submenu = this.nextElementSibling;
            submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
        });
    });
</script>

<div class="sidebar">
    <div class="sidebar-header">
        <img src="https://f365.cucea.udg.mx/images/logo-cucea.png" 
             alt="CUCEA Logo" 
             class="sidebar-logo">
    </div>
    
    <div class="sidebar-menu">
        <a href="admin.php" class="menu-item">
            <i class="fas fa-calendar-alt"></i>
            <span>Citas</span>
        </a>
        
        <!-- Pacientes Section -->
        <a href="#" class="menu-item has-submenu pacientes-menu">
            <i class="fas fa-users"></i>
            <span>Pacientes</span>
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
        <a href="#" class="menu-item">
            <i class="fas fa-cog"></i>
            <span>Configuración</span>
        </a>
        <a href="logout.php" class="menu-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</div>