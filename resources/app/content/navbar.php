<?php
namespace app\content;

class navbar {

    public static function css() {
        ?><style>
            .navbar-toggler{
                margin: 0;
            }
            .navbar {
                border-bottom: 1px solid #e9ecef;
                padding: 0.5rem 1rem;
            }
            
            .navbar-brand img {
                height: 40px;
            }
            
            .nav-link {
                font-weight: 500;
                padding: 1rem 1rem;
                color: #1e293b;
            }
            
            .nav-link.active {
                color: #206bc4;
                border-bottom: 2px solid #206bc4;
            }
        </style><?php
    }

    public static function html() {
        ?><!-- Navbar -->
        <header class="navbar navbar-expand-md navbar-light">
            <div class="container-fluid tablerbar-menu-initialize">
                <a class="navbar-brand" href="#">
                    <img src="/assets/www/img/logo.png" alt="Logo">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbar-menu">
                    <div class="navbar-nav flex-row me-auto">
                        <a href="#" class="nav-link" onclick="switchtab('#home');">Home</a>
                        <a href="#" class="nav-link" onclick="switchtab('#todo');">Example</a>
                    </div>
                </div>
            </div>
        </header><?php
    }

}
