<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sistem Absensi Sekolah</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Bootstrap --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- Bootstrap Icons --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    {{-- App Font --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        (function() {
            try {
                const savedTheme = localStorage.getItem('app_theme');
                const preferredTheme = savedTheme || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', preferredTheme === 'dark' ? 'dark' : 'light');
            } catch (error) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

    <style>
        body {
            margin: 0;
            background: #f5f6f8;
            font-family: Plus Jakarta Sans, sans-serif;
        }

        .topbar {
            height: 64px;
            background: #001362;
            color: white;
            display: flex;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            padding: 0 18px;
        }

        .topbar-left,
        .topbar-right {
            width: 300px;
            display: flex;
            align-items: center;
        }

        .topbar-left {
            justify-content: flex-start;
        }

        .topbar-right {
            justify-content: flex-end;
        }

        .topbar-title {
            flex: 1;
            text-align: center;
            font-size: 21px;
            font-weight: 600;
            letter-spacing: .3px;
        }

        .btn-add-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #001362;
            color: #fff;
            border: none;
            border-radius: 7px;
            padding: 10px 18px;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(13, 110, 253, 0.22);
            transition: all 0.2s ease;
        }

        .btn-add-primary:hover {
            background: #001362;
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(13, 110, 253, 0.28);
        }

        .btn-add-primary i {
            font-size: 16px;
        }

        .brand-toggle {
            border: none;
            background: rgba(255, 255, 255);
            color: #001362;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 12px;
            border-radius: 12px;
            transition: .2s ease;
            max-width: 220px;
        }

        .brand-toggle:hover {
            background: rgba(255, 255, 255, .25);
        }

        .brand-logo {
            width: 36px;
            height: 36px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .brand-text {
            font-size: 16px;
            font-weight: 600;
            white-space: nowrap;
        }

        body.sidebar-is-collapsed .brand-text {
            display: none;
        }

        body.sidebar-is-collapsed .brand-toggle {
            padding: 7px;
            width: 50px;
            justify-content: center;
        }

        .sidebar-toggle:hover {
            background: rgba(255, 255, 255, .25);
        }

        .profile-button {
            border: none;
            background: transparent;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 8px;
            border-radius: 12px;
        }

        .profile-button:hover {
            background: rgba(255, 255, 255, .15);
        }

        .profile-text {
            text-align: right;
            line-height: 18px;
        }

        .profile-name {
            font-size: 14px;
            font-weight: 600;
        }

        .profile-role {
            font-size: 13px;
            opacity: .9;
        }

        .profile-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
        }

        .profile-initial {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            color: #001362;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            border: 2px solid white;
        }

        .profile-dropdown {
            display: none;
            position: absolute;
            top: 58px;
            right: 18px;
            width: 200px;
            background: white;
            border-radius: 12px;
            border: 1px solid #001362;
            overflow: hidden;
            z-index: 2000;
        }

        .profile-dropdown.show {
            display: block;
        }

        .profile-dropdown a,
        .profile-dropdown button {
            display: block;
            width: 100%;
            padding: 12px 15px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            background: white;
            border: none;
            text-align: left;
        }

        .profile-dropdown a:hover,
        .profile-dropdown button:hover {
            background: #f3f6fb;
        }

        .app-sidebar {
            position: fixed;
            top: 64px;
            left: 0;
            bottom: 0;
            width: 240px;
            background: #ffffff;
            border-right: 1px solid #e2e5ea;
            overflow-y: auto;
            transition: width .25s ease;
            z-index: 900;
        }

        .app-sidebar.collapsed {
            width: 78px;
        }

        .sidebar-user {
            padding: 18px 16px 10px;
            font-size: 14px;
            color: #444;
            white-space: nowrap;
            overflow: hidden;
        }

        .app-sidebar.collapsed .sidebar-user {
            display: none;
        }

        .sidebar-menu {
            padding: 10px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 11px 14px;
            margin-bottom: 5px;
            border-radius: 10px;
            color: #001362;
            text-decoration: none;
            font-size: 15px;
            transition: .2s ease;
        }

        .sidebar-link:hover {
            background: #eef5ff;
            color: #001362;
        }

        .sidebar-link.active {
            background: #001362;
            color: white;
        }

        .sidebar-link i {
            font-size: 18px;
            min-width: 22px;
            text-align: center;
        }

        .app-sidebar.collapsed .sidebar-link {
            justify-content: center;
            padding: 12px 0;
            gap: 0;
        }

        .app-sidebar.collapsed .sidebar-text {
            display: none;
        }

        .main-content {
            margin-top: 64px;
            margin-left: 240px;
            padding: 28px 36px;
            transition: margin-left .25s ease;
            min-height: calc(100vh - 64px);
        }

        .main-content.expanded {
            margin-left: 78px;
        }

        .table-responsive-mobile {
            font-size: 14px;
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-responsive-mobile table {
            min-width: 760px;
            margin-bottom: 0;
        }

        .table-responsive-mobile::-webkit-scrollbar {
            height: 8px;
        }

        .table-responsive-mobile::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 20px;
        }

        .table-responsive-mobile::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }

        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .password-wrapper .form-control {
            padding-right: 48px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 14px;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #001362;
            font-size: 18px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle:focus {
            outline: none;
        }

        @media (max-width: 768px) {
            .page-header {
                align-items: flex-start;
                gap: 14px;
            }

            .page-header h3 {
                margin-bottom: 0;
                line-height: 1.25;
            }

            .page-header .btn {
                white-space: nowrap;
                padding: 10px 14px;
            }
        }

        @media (max-width: 768px) {
            .table-responsive-mobile {
                border-radius: 10px;
                border: 1px solid #e5e7eb;
            }

            .table-responsive-mobile table {
                font-size: 14px;
            }

            .table-responsive-mobile th,
            .table-responsive-mobile td {
                white-space: nowrap;
                vertical-align: middle;
                padding: 10px 12px;
            }

            .table-responsive-mobile .btn {
                padding: 6px 10px;
                font-size: 13px;
                margin-bottom: 4px;
            }

            .table-responsive-mobile .badge {
                font-size: 12px;
            }
        }

        @media (max-width: 768px) {
            .topbar {
                height: 72px;
                padding: 0 14px;
                display: grid;
                grid-template-columns: auto 1fr auto;
                gap: 10px;
            }

            .topbar-left,
            .topbar-right {
                width: auto;
                min-width: 0;
            }

            .topbar-title {
                font-size: 17px;
                font-weight: 700;
                text-align: center;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .brand-toggle {
                width: 54px;
                height: 48px;
                padding: 6px;
                justify-content: center;
                border-radius: 12px;
                max-width: 54px;
            }

            .brand-logo {
                width: 38px;
                height: 38px;
            }

            .brand-text {
                display: none;
            }

            body.mobile-sidebar-open .brand-toggle {
                width: auto;
                max-width: 220px;
                padding: 7px 12px;
                justify-content: flex-start;
            }

            body.mobile-sidebar-open .brand-text {
                display: inline-block;
                font-size: 15px;
                font-weight: 700;
                white-space: nowrap;
            }

            .profile-text {
                display: none;
            }

            .profile-photo,
            .profile-initial {
                width: 42px;
                height: 42px;
            }

            .app-sidebar {
                top: 72px;
                width: 285px;
                max-width: 82vw;
                transform: translateX(-100%);
                transition: transform .25s ease;
                box-shadow: 8px 0 24px rgba(0, 0, 0, .12);
                border-right: 1px solid #e5e7eb;
            }

            .app-sidebar.mobile-open {
                transform: translateX(0);
            }

            .app-sidebar.collapsed {
                width: 285px;
            }

            .app-sidebar.collapsed .sidebar-text,
            .app-sidebar.collapsed .sidebar-user {
                display: block;
            }

            .app-sidebar.collapsed .sidebar-link {
                justify-content: flex-start;
                padding: 13px 16px;
                gap: 14px;
            }

            .sidebar-menu {
                padding: 14px 10px;
            }

            .sidebar-link {
                padding: 13px 16px;
                font-size: 16px;
                border-radius: 12px;
                margin-bottom: 8px;
            }

            .sidebar-link i {
                font-size: 19px;
                min-width: 26px;
            }

            .main-content,
            .main-content.expanded {
                margin-top: 72px;
                margin-left: 0;
                padding: 20px 16px;
                overflow-x: hidden;
            }

            .mobile-sidebar-backdrop {
                display: none;
                position: fixed;
                top: 72px;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, .35);
                z-index: 850;
            }

            .mobile-sidebar-backdrop.show {
                display: block;
            }

            h1, h2, h3 {
                font-size: 26px;
            }

            .card {
                border-radius: 12px;
            }

            .card-body {
                padding: 16px;
            }

            .btn {
                border-radius: 10px;
            }
        }


        /* =========================
           GLOBAL UI/UX POLISH KIT
           Berlaku untuk seluruh menu tanpa mengubah logic fitur.
        ========================= */
        :root {
            --app-primary: #001362;
            --app-primary-2: #1769ff;
            --app-bg: #f4f7fb;
            --app-card: #ffffff;
            --app-border: #dbe3ef;
            --app-text: #0f172a;
            --app-muted: #64748b;
            --app-shadow: 0 18px 45px rgba(15, 23, 42, .08);
            --app-shadow-soft: 0 10px 28px rgba(15, 23, 42, .06);
            --app-radius: 18px;
        }

        body {
            background:
                radial-gradient(circle at top left, rgba(23, 105, 255, .08), transparent 28rem),
                linear-gradient(180deg, #f8fbff 0%, var(--app-bg) 100%) !important;
            color: var(--app-text);
        }

        .main-content {
            padding: 30px 48px;
        }

        .container-fluid {
            max-width: 1240px;
            margin-left: auto;
            margin-right: auto;
        }

        .ui-page-hero {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            padding: 24px 24px;
            margin-bottom: 18px;
            color: #fff;
            background: linear-gradient(135deg, var(--app-primary) 0%, var(--app-primary-2) 100%);
            box-shadow: 0 18px 36px rgba(0, 19, 98, .18);
        }

        .ui-page-hero::after {
            content: '';
            position: absolute;
            right: -70px;
            top: -70px;
            width: 190px;
            height: 190px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .16);
        }

        .ui-page-hero h1,
        .ui-page-hero h2,
        .ui-page-hero h3 {
            position: relative;
            z-index: 1;
            margin: 0;
            color: #fff;
            font-weight: 800;
            letter-spacing: -.4px;
        }

        .ui-page-hero p {
            position: relative;
            z-index: 1;
            margin: 6px 0 0;
            color: rgba(255, 255, 255, .88);
            font-size: 15px;
        }

        .ui-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            margin-bottom: 18px;
        }

        .ui-section-title i,
        .ui-title-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--app-primary);
            background: #eef4ff;
        }

        .card,
        .ui-card {
            border: 1px solid rgba(219, 227, 239, .9) !important;
            border-radius: var(--app-radius) !important;
            box-shadow: var(--app-shadow-soft);
            background: var(--app-card);
        }

        .card-body {
            padding: 22px;
        }

        .page-header {
            border-radius: 20px;
            padding: 22px 24px;
            color: #fff;
            background: linear-gradient(135deg, var(--app-primary) 0%, var(--app-primary-2) 100%);
            box-shadow: 0 18px 36px rgba(0, 19, 98, .16);
        }

        .page-header h3,
        .page-header h2,
        .page-header h1 {
            color: #fff;
        }

        .form-label,
        label {
            font-weight: 600;
            color: #16213e;
            margin-bottom: 7px;
        }

        .form-control,
        .form-select {
            border-radius: 12px;
            border: 1px solid #d6e0ee;
            padding: 10px 13px;
            min-height: 44px;
            box-shadow: none !important;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--app-primary-2);
            box-shadow: 0 0 0 .22rem rgba(23, 105, 255, .12) !important;
        }

        .btn,
        .btn-add-primary {
            border-radius: 12px !important;
            font-weight: 700;
        }

        .btn-primary,
        .btn-add-primary {
            background: var(--app-primary) !important;
            border-color: var(--app-primary) !important;
        }

        .btn-success { background: #138a57 !important; border-color: #138a57 !important; }
        .btn-danger { background: #dc3545 !important; border-color: #dc3545 !important; }
        .btn-warning { background: #ffbd18 !important; border-color: #ffbd18 !important; color: #111827 !important; }

        .table {
            --bs-table-striped-bg: #f6f8fb;
            border-color: #dbe3ef;
        }

        .table thead th {
            background: #f0f5ff !important;
            color: #0f1f56;
            font-weight: 800;
            border-bottom: 1px solid #dbe3ef;
            vertical-align: middle;
        }

        .table td,
        .table th {
            padding: 12px 12px;
            vertical-align: middle;
        }

        .badge {
            border-radius: 9px;
            padding: 6px 9px;
            font-weight: 800;
        }

        .alert {
            border-radius: 16px;
            border-width: 1px;
            box-shadow: var(--app-shadow-soft);
        }

        .pagination .page-link {
            border-radius: 10px !important;
            margin: 0 3px;
            color: var(--app-primary);
            font-weight: 700;
        }

        .pagination .active .page-link {
            background: var(--app-primary);
            border-color: var(--app-primary);
            color: #fff;
        }

        .ui-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .ui-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .ui-empty-state {
            border: 1px dashed #cbd5e1;
            border-radius: 18px;
            padding: 28px;
            text-align: center;
            color: var(--app-muted);
            background: #fbfdff;
        }

        .ui-empty-state i {
            width: 48px;
            height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            background: #eef4ff;
            color: var(--app-primary);
            font-size: 24px;
            margin-bottom: 10px;
        }

        .ui-soft-panel {
            border: 1px solid #dbe3ef;
            background: #fbfdff;
            border-radius: 16px;
            padding: 16px;
        }

        @media (max-width: 768px) {
            .main-content,
            .main-content.expanded {
                padding: 20px 14px !important;
            }

            .ui-page-hero,
            .page-header {
                padding: 20px 18px;
                border-radius: 18px;
            }

            .ui-page-hero h3,
            .page-header h3 {
                font-size: 24px;
            }

            .card-body {
                padding: 16px;
            }
        }

    

        /* =========================
           FINISHING UI V3 - lebih tegas dan menyeluruh
           Fokus: header aksi kanan, sidebar/topbar lebih modern,
           table/card/form lebih bersih. Tidak mengubah logic fitur.
        ========================= */
        :root {
            --app-primary: #001362;
            --app-primary-2: #1769ff;
            --app-primary-3: #0b2fb3;
            --app-bg: #f3f7ff;
            --app-card: rgba(255,255,255,.94);
            --app-border: #d7e1f1;
            --app-text: #071633;
            --app-muted: #667085;
            --app-radius: 18px;
            --app-shadow: 0 22px 55px rgba(0, 19, 98, .11);
            --app-shadow-soft: 0 12px 32px rgba(15, 23, 42, .075);
        }

        html { scroll-behavior: smooth; }

        body {
            background:
                radial-gradient(circle at 15% 0%, rgba(23, 105, 255, .11), transparent 28rem),
                radial-gradient(circle at 85% 20%, rgba(0, 19, 98, .08), transparent 24rem),
                linear-gradient(180deg, #fbfdff 0%, var(--app-bg) 100%) !important;
            color: var(--app-text) !important;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .topbar {
            height: 70px !important;
            background: linear-gradient(135deg, #001362 0%, #001c84 58%, #0d48d8 100%) !important;
            box-shadow: 0 12px 34px rgba(0, 19, 98, .22);
            border-bottom: 1px solid rgba(255,255,255,.12);
        }

        .topbar-title {
            font-weight: 800 !important;
            letter-spacing: .2px;
            text-shadow: 0 4px 18px rgba(0,0,0,.18);
        }

        .brand-toggle {
            min-width: 176px;
            height: 48px;
            background: rgba(255,255,255,.96) !important;
            box-shadow: 0 10px 24px rgba(0,0,0,.12);
        }

        .profile-button {
            background: rgba(255,255,255,.08) !important;
            border: 1px solid rgba(255,255,255,.12) !important;
        }

        .app-sidebar {
            top: 70px !important;
            background: rgba(255,255,255,.96) !important;
            box-shadow: 12px 0 35px rgba(15, 23, 42, .07);
            border-right: 1px solid rgba(215, 225, 241, .9) !important;
        }

        .sidebar-menu { padding: 14px 10px !important; }

        .sidebar-link {
            border-radius: 14px !important;
            padding: 13px 15px !important;
            font-weight: 600;
            color: var(--app-primary) !important;
        }

        .sidebar-link:hover {
            background: #edf4ff !important;
            transform: translateX(2px);
        }

        .sidebar-link.active {
            color: #fff !important;
            background: linear-gradient(135deg, var(--app-primary) 0%, #071f8a 100%) !important;
            box-shadow: 0 12px 26px rgba(0, 19, 98, .20);
        }

        .main-content {
            margin-top: 70px !important;
            padding: 32px 48px !important;
        }

        .container-fluid {
            max-width: 1240px;
            margin-left: auto;
            margin-right: auto;
        }

        .page-header,
        .ui-page-hero {
            min-height: 108px;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 18px;
            border-radius: 22px !important;
            padding: 25px 25px !important;
            margin-bottom: 20px !important;
            color: #fff !important;
            background: linear-gradient(135deg, var(--app-primary) 0%, #123fcb 52%, var(--app-primary-2) 100%) !important;
            box-shadow: var(--app-shadow) !important;
            position: relative;
            overflow: hidden;
        }

        .page-header::before,
        .ui-page-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(255,255,255,.10), transparent 45%);
            pointer-events: none;
        }

        .page-header::after,
        .ui-page-hero::after {
            content: '';
            position: absolute;
            right: -58px;
            top: -72px;
            width: 210px;
            height: 210px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .17);
            pointer-events: none;
        }

        .page-header > *,
        .ui-page-hero > * {
            position: relative;
            z-index: 1;
        }

        .page-header h1,
        .page-header h2,
        .page-header h3,
        .ui-page-hero h1,
        .ui-page-hero h2,
        .ui-page-hero h3 {
            margin: 0 !important;
            color: #fff !important;
            font-weight: 900 !important;
            letter-spacing: -.6px;
            line-height: 1.15;
        }

        .page-header p,
        .ui-page-hero p {
            margin: 7px 0 0 !important;
            color: rgba(255,255,255,.90) !important;
            font-weight: 500;
        }

        .page-header .btn,
        .page-header .btn-add-primary,
        .ui-page-hero .btn,
        .ui-page-hero .btn-add-primary {
            margin-left: auto;
        }

        .btn-add-primary,
        .btn.btn-primary {
            min-height: 44px;
            padding: 10px 18px !important;
            background: var(--app-primary) !important;
            border-color: var(--app-primary) !important;
            box-shadow: 0 12px 24px rgba(0, 19, 98, .20) !important;
        }

        .ui-page-hero .btn-add-primary,
        .page-header .btn-add-primary,
        .ui-page-hero .btn-primary,
        .page-header .btn-primary {
            background: #001362 !important;
            border-color: rgba(255,255,255,.14) !important;
            color: #fff !important;
        }

        .card,
        .ui-card {
            background: var(--app-card) !important;
            border: 1px solid rgba(215, 225, 241, .92) !important;
            border-radius: 18px !important;
            box-shadow: var(--app-shadow-soft) !important;
        }

        .card-body { padding: 22px !important; }

        .table {
            border-color: var(--app-border) !important;
            --bs-table-striped-bg: #f5f8fe !important;
        }

        .table thead th {
            background: #edf4ff !important;
            color: #071f6e !important;
            font-weight: 850 !important;
            border-bottom: 1px solid var(--app-border) !important;
        }

        .table td,
        .table th {
            padding: 13px 13px !important;
            vertical-align: middle !important;
        }

        .table tbody tr:hover td {
            background: #f8fbff !important;
        }

        .form-control,
        .form-select {
            min-height: 46px;
            border-radius: 14px !important;
            border: 1px solid #d7e1f1 !important;
            background-color: #fbfdff;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--app-primary-2) !important;
            box-shadow: 0 0 0 .22rem rgba(23, 105, 255, .13) !important;
        }

        .badge {
            border-radius: 999px !important;
            padding: 6px 10px !important;
            font-weight: 800 !important;
        }

        .alert {
            border-radius: 16px !important;
            box-shadow: var(--app-shadow-soft);
            border-width: 1px !important;
        }

        .ui-toolbar {
            background: rgba(255,255,255,.80);
            border: 1px solid rgba(215, 225, 241, .92);
            border-radius: 18px;
            padding: 14px;
            box-shadow: 0 10px 28px rgba(15,23,42,.05);
        }

        .profile-dropdown {
            border: 1px solid rgba(215,225,241,.95) !important;
            box-shadow: 0 18px 42px rgba(15,23,42,.16);
        }

        @media (max-width: 768px) {
            .topbar { height: 72px !important; }
            .app-sidebar { top: 72px !important; }
            .main-content,
            .main-content.expanded {
                margin-top: 72px !important;
                padding: 20px 14px !important;
            }
            .page-header,
            .ui-page-hero {
                align-items: flex-start !important;
                flex-direction: column !important;
                min-height: auto;
                padding: 21px 18px !important;
            }
            .page-header .btn,
            .page-header .btn-add-primary,
            .ui-page-hero .btn,
            .ui-page-hero .btn-add-primary {
                margin-left: 0;
                width: 100%;
                justify-content: center;
            }
            .card-body { padding: 16px !important; }
        }
</style>
    @stack('styles')

    {{-- Final UI polish override --}}
    <link href="{{ asset('css/absensi-ui-final.css') }}?v=20260705-sidebar-search-report-fix" rel="stylesheet">
</head>
<body class="app-shell">

<a href="#mainContent" class="skip-link">Lewati ke konten utama</a>

@php
    $user = auth()->user();
    $displayName = $user->name ?? $user->nip;
    $roleLabel = ucwords(str_replace('_', ' ', $user->role));
    $appPageTitle = match (true) {
        request()->routeIs('dashboard') => 'Dashboard',
        request()->routeIs('attendance.*') => 'Absensi',
        request()->routeIs('leave.*') => in_array($user->role, ['kepala_sekolah', 'super_admin']) ? 'Approval Izin/Cuti' : 'Pengajuan Izin/Cuti',
        request()->routeIs('admin.users*') => 'Data Pengguna',
        request()->routeIs('admin.teachers*') => 'Data Guru',
        request()->routeIs('admin.location*') => 'Setting Lokasi Sekolah',
        request()->routeIs('admin.attendance-sessions*') => 'Setting Sesi',
        request()->routeIs('admin.holidays*') => 'Setting Hari Libur',
        request()->routeIs('admin.duty-schedules*') => 'Setting Hari Piket',
        request()->routeIs('reports.*') => 'Laporan Rekap Absensi',
        request()->routeIs('payroll.*') => 'Laporan Penggajian',
        request()->routeIs('infal.report.*') => 'Laporan Rekap Infal',
        request()->routeIs('profile.*') => 'Profil',
        default => 'Sistem Absensi Sekolah',
    };

    $mobileNavItems = [
        [
            'url' => route('dashboard'),
            'active' => request()->routeIs('dashboard'),
            'icon' => 'bi-speedometer2',
            'label' => 'Dashboard',
        ],
    ];

    if (in_array($user->role, ['guru', 'bendahara', 'kepala_sekolah'])) {
        $mobileNavItems[] = [
            'url' => route('attendance.index'),
            'active' => request()->routeIs('attendance.*'),
            'icon' => 'bi-camera',
            'label' => 'Absensi',
        ];
    }

    if (in_array($user->role, ['guru', 'bendahara', 'kepala_sekolah', 'super_admin'])) {
        $mobileNavItems[] = [
            'url' => route('leave.index'),
            'active' => request()->routeIs('leave.*'),
            'icon' => 'bi-file-earmark-text',
            'label' => in_array($user->role, ['kepala_sekolah', 'super_admin']) ? 'Approval' : 'Pengajuan',
        ];
    }

    if (in_array($user->role, ['super_admin', 'kepala_sekolah'])) {
        $mobileNavItems[] = [
            'url' => route('reports.index'),
            'active' => request()->routeIs('reports.*'),
            'icon' => 'bi-bar-chart',
            'label' => 'Laporan',
        ];
    }

    if ($user->role === 'bendahara') {
        $mobileNavItems[] = [
            'url' => route('infal.report.index'),
            'active' => request()->routeIs('infal.report.*'),
            'icon' => 'bi-file-earmark-bar-graph',
            'label' => 'Infal',
        ];
    }

    if (in_array($user->role, ['super_admin', 'bendahara'])) {
        $mobileNavItems[] = [
            'url' => route('payroll.index'),
            'active' => request()->routeIs('payroll.*'),
            'icon' => 'bi-cash-coin',
            'label' => 'Gaji',
        ];
    }

    if ($user->role === 'super_admin') {
        $mobileNavItems[] = [
            'url' => route('admin.teachers'),
            'active' => request()->routeIs('admin.teachers*'),
            'icon' => 'bi-person-badge',
            'label' => 'Guru',
        ];
    }

    $mobileNavItems = array_slice($mobileNavItems, 0, 5);

    $mainSidebarItems = [
        [
            'url' => route('dashboard'),
            'active' => request()->routeIs('dashboard'),
            'icon' => 'bi-speedometer2',
            'label' => 'Dashboard',
        ],
    ];

    if ($user->role === 'super_admin') {
        $mainSidebarItems[] = [
            'url' => route('admin.users'),
            'active' => request()->routeIs('admin.users*'),
            'icon' => 'bi-people',
            'label' => 'Data Pengguna',
        ];
        $mainSidebarItems[] = [
            'url' => route('admin.teachers'),
            'active' => request()->routeIs('admin.teachers*'),
            'icon' => 'bi-person-badge',
            'label' => 'Data Guru',
        ];
        $mainSidebarItems[] = [
            'url' => route('leave.index'),
            'active' => request()->routeIs('leave.*'),
            'icon' => 'bi-file-earmark-text',
            'label' => 'Approval Izin/Cuti',
        ];
    } else {
        if (in_array($user->role, ['guru', 'bendahara', 'kepala_sekolah'])) {
            $mainSidebarItems[] = [
                'url' => route('attendance.index'),
                'active' => request()->routeIs('attendance.*'),
                'icon' => 'bi-camera',
                'label' => 'Absensi',
            ];
        }

        if (in_array($user->role, ['guru', 'bendahara', 'kepala_sekolah'])) {
            $mainSidebarItems[] = [
                'url' => route('leave.index'),
                'active' => request()->routeIs('leave.*'),
                'icon' => 'bi-file-earmark-text',
                'label' => $user->role === 'kepala_sekolah' ? 'Approval Izin/Cuti' : 'Pengajuan Izin/Cuti',
            ];
        }
    }

    $settingSidebarItems = [];

    if ($user->role === 'super_admin') {
        $settingSidebarItems = [
            [
                'url' => route('admin.attendance-sessions.index'),
                'active' => request()->routeIs('admin.attendance-sessions*'),
                'icon' => 'bi-clock-history',
                'label' => 'Setting Sesi dan Jam Absensi',
            ],
            [
                'url' => route('admin.location'),
                'active' => request()->routeIs('admin.location*'),
                'icon' => 'bi-geo-alt',
                'label' => 'Setting Lokasi Sekolah',
            ],
            [
                'url' => route('admin.duty-schedules.index'),
                'active' => request()->routeIs('admin.duty-schedules*'),
                'icon' => 'bi-person-check',
                'label' => 'Setting Hari Piket',
            ],
            [
                'url' => route('admin.holidays.index'),
                'active' => request()->routeIs('admin.holidays*'),
                'icon' => 'bi-calendar-x',
                'label' => 'Setting Hari Libur',
            ],
        ];
    }

    $reportSidebarItems = [];

    if ($user->role === 'super_admin') {
        $reportSidebarItems = [
            [
                'url' => route('reports.index'),
                'active' => request()->routeIs('reports.*'),
                'icon' => 'bi-bar-chart',
                'label' => 'Laporan Rekap Absensi',
            ],
            [
                'url' => route('infal.report.index'),
                'active' => request()->routeIs('infal.report.*'),
                'icon' => 'bi-file-earmark-bar-graph',
                'label' => 'Laporan Rekap Infal',
            ],
            [
                'url' => route('payroll.index'),
                'active' => request()->routeIs('payroll.*'),
                'icon' => 'bi-cash-coin',
                'label' => 'Laporan Penggajian',
            ],
        ];
    } elseif ($user->role === 'kepala_sekolah') {
        $reportSidebarItems = [
            [
                'url' => route('reports.index'),
                'active' => request()->routeIs('reports.*'),
                'icon' => 'bi-bar-chart',
                'label' => 'Laporan Rekap Absensi',
            ],
            [
                'url' => route('infal.report.index'),
                'active' => request()->routeIs('infal.report.*'),
                'icon' => 'bi-file-earmark-bar-graph',
                'label' => 'Laporan Rekap Infal',
            ],
        ];
    } elseif ($user->role === 'bendahara') {
        $reportSidebarItems = [
            [
                'url' => route('infal.report.index'),
                'active' => request()->routeIs('infal.report.*'),
                'icon' => 'bi-file-earmark-bar-graph',
                'label' => 'Laporan Rekap Infal',
            ],
            [
                'url' => route('payroll.index'),
                'active' => request()->routeIs('payroll.*'),
                'icon' => 'bi-cash-coin',
                'label' => 'Laporan Penggajian',
            ],
        ];
    }

    $sidebarGroups = array_values(array_filter([
        [
            'label' => 'MAIN',
            'icon' => 'bi-grid',
            'items' => $mainSidebarItems,
        ],
        [
            'label' => 'SETTING',
            'icon' => 'bi-sliders',
            'items' => $settingSidebarItems,
        ],
        [
            'label' => 'REPORT',
            'icon' => 'bi-clipboard2-data',
            'items' => $reportSidebarItems,
        ],
    ], fn ($group) => count($group['items']) > 0));
@endphp

{{-- TOPBAR --}}
<div class="topbar">
    <div class="topbar-left">
        <button
            class="app-menu-button"
            type="button"
            onclick="toggleSidebar()"
            aria-label="Buka atau tutup menu navigasi"
            aria-controls="sidebar"
            aria-expanded="true"
        >
            <i class="bi bi-list"></i>
        </button>
    </div>

    <div class="topbar-title">
        <span class="topbar-mobile-brand">
            <img src="{{ asset('images/logo-MI.png') }}" alt="Logo MI">
            <span>MI Lantaburo</span>
        </span>
        <span class="topbar-title-main">{{ $appPageTitle }}</span>
        <span class="topbar-title-sub">{{ now()->translatedFormat('l, d F Y') }}</span>
    </div>

    <div class="topbar-right">
        <button
            type="button"
            class="theme-toggle"
            id="themeToggle"
            aria-label="Ganti tema tampilan"
            aria-pressed="false"
            title="Ganti tema"
        >
            <i class="bi bi-moon-stars"></i>
            <span class="theme-toggle-text">Tema</span>
        </button>

        <div class="notification-wrapper">
            <button
                type="button"
                class="notification-button"
                onclick="toggleNotificationDropdown()"
                title="Notifikasi"
                aria-label="Buka notifikasi"
                aria-controls="notificationDropdown"
                aria-expanded="false"
            >
                <i class="bi bi-bell-fill"></i>
                @if(($appUnreadNotificationCount ?? 0) > 0)
                    <span class="notification-count">{{ $appUnreadNotificationCount > 99 ? '99+' : $appUnreadNotificationCount }}</span>
                @endif
            </button>

            <div id="notificationDropdown" class="notification-dropdown">
                <div class="notification-head">
                    <strong>Notifikasi</strong>
                    @if(($appUnreadNotificationCount ?? 0) > 0)
                        <form action="{{ route('notifications.readAll') }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit">Tandai dibaca</button>
                        </form>
                    @endif
                </div>

                <div class="notification-list">
                    @forelse(($appNotifications ?? collect()) as $notification)
                        @php
                            $icon = match($notification->type ?? 'info') {
                                'attendance' => 'bi-clock-history',
                                'leave' => 'bi-file-earmark-text',
                                'infal' => 'bi-people',
                                default => 'bi-info-circle',
                            };

                            $notificationUrl = $notification->is_dynamic
                                ? ($notification->url ?? route('dashboard'))
                                : route('notifications.read', $notification->id);
                        @endphp

                        <a href="{{ $notificationUrl }}" class="notification-item {{ !$notification->read_at ? 'unread' : '' }}">
                            <span class="notification-icon"><i class="bi {{ $icon }}"></i></span>
                            <span>
                                <span class="notification-title d-block">{{ $notification->title }}</span>
                                <span class="notification-message d-block">{{ $notification->message }}</span>
                                @if($notification->created_at)
                                    <span class="notification-time d-block">{{ $notification->created_at->diffForHumans() }}</span>
                                @endif
                            </span>
                        </a>
                    @empty
                        <div class="notification-empty">
                            <i class="bi bi-bell-slash d-block mb-2 fs-4"></i>
                            Belum ada notifikasi.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <button
            type="button"
            class="profile-button"
            onclick="toggleProfileDropdown()"
            aria-label="Buka menu profil"
            aria-controls="profileDropdown"
            aria-expanded="false"
        >
            <div class="profile-text">
                <div class="profile-name">{{ $displayName }}</div>
                <div class="profile-role">{{ $roleLabel }}</div>
            </div>

            @if(auth()->user()->profile_photo)
            <img 
                src="{{ asset(auth()->user()->profile_photo) }}" 
                alt="Foto Profil"
                class="profile-photo"
            >
        @else
            <div class="profile-initial">
                {{ strtoupper(substr(auth()->user()->name ?? auth()->user()->nip, 0, 1)) }}
            </div>
        @endif
            <i class="bi bi-chevron-down profile-chevron" aria-hidden="true"></i>
        </button>

        <div id="profileDropdown" class="profile-dropdown shadow" role="menu">
            <a href="{{ route('profile.edit') }}">
                <i class="bi bi-person me-2"></i> Ubah Profil
            </a>

            <a href="{{ route('profile.password.form') }}">
                <i class="bi bi-key me-2"></i> Ubah Password
            </a>

            <form action="{{ route('logout') }}" method="POST" class="m-0 logout-form" data-confirm-logout="true" data-no-loading="true">
                @csrf
                <button type="submit" class="text-danger">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </button>
            </form>
        </div>
    </div>
</div>

{{-- SIDEBAR --}}
<div id="mobileSidebarBackdrop" class="mobile-sidebar-backdrop" onclick="toggleSidebar()"></div>
<aside id="sidebar" class="app-sidebar" aria-label="Navigasi utama">
    <div class="sidebar-brand">
        <img src="{{ asset('images/logo-MI.png') }}" alt="Logo MI" class="sidebar-brand-logo">
        <span class="sidebar-brand-name">MI Lantaburo</span>
    </div>

    <div class="sidebar-menu">
        @foreach($sidebarGroups as $group)
            @php
                $isGroupActive = collect($group['items'])->contains(fn ($item) => $item['active']);
            @endphp
            <details class="sidebar-group {{ $isGroupActive ? 'is-active' : '' }}" open>
                <summary class="sidebar-group-toggle">
                    <span class="sidebar-group-title">
                        <i class="bi {{ $group['icon'] }}"></i>
                        <span class="sidebar-text">{{ $group['label'] }}</span>
                    </span>
                    <i class="bi bi-chevron-down sidebar-group-chevron" aria-hidden="true"></i>
                </summary>

                <div class="sidebar-submenu">
                    @foreach($group['items'] as $item)
                        <a href="{{ $item['url'] }}" class="sidebar-link {{ $item['active'] ? 'active' : '' }}">
                            <i class="bi {{ $item['icon'] }}"></i>
                            <span class="sidebar-text">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </details>
        @endforeach
    </div>
</aside>

<nav class="mobile-bottom-nav" aria-label="Navigasi cepat">
    @foreach($mobileNavItems as $item)
        <a href="{{ $item['url'] }}" class="mobile-bottom-link {{ $item['active'] ? 'active' : '' }}">
            <i class="bi {{ $item['icon'] }}"></i>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>

{{-- CONTENT --}}
<main id="mainContent" class="main-content" tabindex="-1">
    @yield('content')
</main>

<div id="appToastStack" class="app-toast-stack" aria-live="polite" aria-atomic="true">
    @if(session('success'))
        <div class="app-toast app-toast-success" role="status" data-app-toast>
            <div class="app-toast-icon">
                <i class="bi bi-check2-circle"></i>
            </div>
            <div class="app-toast-copy">
                <strong>Berhasil</strong>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="app-toast-close" aria-label="Tutup notifikasi" data-toast-close>
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="app-toast app-toast-danger" role="alert" data-app-toast>
            <div class="app-toast-icon">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="app-toast-copy">
                <strong>Perlu Diperiksa</strong>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" class="app-toast-close" aria-label="Tutup notifikasi" data-toast-close>
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    @endif

    @if($errors->any())
        <div class="app-toast app-toast-danger" role="alert" data-app-toast>
            <div class="app-toast-icon">
                <i class="bi bi-list-check"></i>
            </div>
            <div class="app-toast-copy">
                <strong>Form Belum Lengkap</strong>
                <span>{{ $errors->count() }} bagian perlu diperbaiki sebelum disimpan.</span>
            </div>
            <button type="button" class="app-toast-close" aria-label="Tutup notifikasi" data-toast-close>
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    @endif
</div>

<div id="logoutConfirmDialog" class="logout-confirm-dialog" aria-hidden="true">
    <div class="logout-confirm-backdrop" data-logout-cancel></div>
    <section
        class="logout-confirm-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="logoutConfirmTitle"
        aria-describedby="logoutConfirmMessage"
        tabindex="-1"
    >
        <div class="logout-confirm-icon">
            <i class="bi bi-box-arrow-right"></i>
        </div>
        <div class="logout-confirm-copy">
            <h2 id="logoutConfirmTitle">Keluar dari aplikasi?</h2>
            <p id="logoutConfirmMessage">Sesi Anda akan ditutup. Pastikan semua perubahan yang sedang dikerjakan sudah tersimpan.</p>
        </div>
        <div class="logout-confirm-actions">
            <button type="button" class="logout-confirm-cancel" data-logout-cancel>
                Batal
            </button>
            <button type="button" class="logout-confirm-submit" id="logoutConfirmSubmit">
                <i class="bi bi-check2-circle"></i>
                Ya, Logout
            </button>
        </div>
    </section>
</div>

<div id="appConfirmDialog" class="app-confirm-dialog" aria-hidden="true">
    <div class="app-confirm-backdrop" data-confirm-cancel></div>
    <section
        class="app-confirm-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="appConfirmTitle"
        aria-describedby="appConfirmMessage"
        tabindex="-1"
    >
        <div class="app-confirm-icon" id="appConfirmIconWrap">
            <i id="appConfirmIcon" class="bi bi-question-circle"></i>
        </div>
        <div class="app-confirm-copy">
            <h2 id="appConfirmTitle">Konfirmasi Aksi</h2>
            <p id="appConfirmMessage">Pastikan data yang dipilih sudah benar sebelum melanjutkan.</p>
        </div>
        <div class="app-confirm-actions">
            <button type="button" class="app-confirm-cancel" data-confirm-cancel>
                Batal
            </button>
            <button type="button" class="app-confirm-submit" id="appConfirmSubmit">
                <i class="bi bi-check2-circle"></i>
                <span id="appConfirmSubmitText">Lanjutkan</span>
            </button>
        </div>
    </section>
</div>

<script>
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const sidebarToggleButtons = document.querySelectorAll('.app-menu-button');
    const profileToggleButton = document.querySelector('.profile-button');
    const notificationToggleButton = document.querySelector('.notification-button');
    const themeToggleButton = document.getElementById('themeToggle');
    const logoutConfirmDialog = document.getElementById('logoutConfirmDialog');
    const logoutConfirmSubmit = document.getElementById('logoutConfirmSubmit');
    const appConfirmDialog = document.getElementById('appConfirmDialog');
    const appConfirmSubmit = document.getElementById('appConfirmSubmit');
    const appConfirmSubmitText = document.getElementById('appConfirmSubmitText');
    const appConfirmTitle = document.getElementById('appConfirmTitle');
    const appConfirmMessage = document.getElementById('appConfirmMessage');
    const appConfirmIcon = document.getElementById('appConfirmIcon');
    const appConfirmIconWrap = document.getElementById('appConfirmIconWrap');
    let pendingLogoutForm = null;
    let pendingConfirmForm = null;
    let pendingConfirmSubmitter = null;

    function updateSidebarToggleState() {
        const isOpen = window.innerWidth <= 768
            ? sidebar.classList.contains('mobile-open')
            : !sidebar.classList.contains('collapsed');

        sidebarToggleButtons.forEach(function(button) {
            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    function getPreferredAppTheme() {
        const savedTheme = localStorage.getItem('app_theme');

        if (savedTheme === 'light' || savedTheme === 'dark') {
            return savedTheme;
        }

        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyAppTheme(theme) {
        const normalizedTheme = theme === 'dark' ? 'dark' : 'light';
        const isDark = normalizedTheme === 'dark';

        document.documentElement.setAttribute('data-theme', normalizedTheme);
        localStorage.setItem('app_theme', normalizedTheme);

        if (!themeToggleButton) {
            return;
        }

        const icon = themeToggleButton.querySelector('i');
        const label = themeToggleButton.querySelector('.theme-toggle-text');

        themeToggleButton.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        themeToggleButton.setAttribute('title', isDark ? 'Gunakan tema terang' : 'Gunakan tema gelap');
        themeToggleButton.setAttribute('aria-label', isDark ? 'Gunakan tema terang' : 'Gunakan tema gelap');

        if (icon) {
            icon.classList.toggle('bi-moon-stars', !isDark);
            icon.classList.toggle('bi-sun', isDark);
        }

        if (label) {
            label.textContent = isDark ? 'Terang' : 'Gelap';
        }
    }

    function toggleSidebar() {
        const backdrop = document.getElementById('mobileSidebarBackdrop');

        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('mobile-open');
            document.body.classList.toggle('mobile-sidebar-open');

            if (backdrop) {
                backdrop.classList.toggle('show');
            }

            updateSidebarToggleState();

            return;
        }

        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        document.body.classList.toggle('sidebar-is-collapsed');

        if (sidebar.classList.contains('collapsed')) {
            localStorage.setItem('sidebar_state', 'collapsed');
        } else {
            localStorage.setItem('sidebar_state', 'open');
        }

        updateSidebarToggleState();
    }

    window.addEventListener('resize', function () {
        const backdrop = document.getElementById('mobileSidebarBackdrop');

        if (window.innerWidth > 768) {
            sidebar.classList.remove('mobile-open');
            document.body.classList.remove('mobile-sidebar-open');

            if (backdrop) {
                backdrop.classList.remove('show');
            }
        }

        updateSidebarToggleState();
    });

    function toggleProfileDropdown() {
        const notificationDropdown = document.getElementById('notificationDropdown');
        if (notificationDropdown) {
            notificationDropdown.classList.remove('show');
        }

        if (notificationToggleButton) {
            notificationToggleButton.setAttribute('aria-expanded', 'false');
        }

        const profileDropdown = document.getElementById('profileDropdown');
        profileDropdown.classList.toggle('show');

        if (profileToggleButton) {
            profileToggleButton.setAttribute('aria-expanded', profileDropdown.classList.contains('show') ? 'true' : 'false');
        }
    }

    function toggleNotificationDropdown() {
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileDropdown) {
            profileDropdown.classList.remove('show');
        }

        if (profileToggleButton) {
            profileToggleButton.setAttribute('aria-expanded', 'false');
        }

        const notificationDropdown = document.getElementById('notificationDropdown');
        notificationDropdown.classList.toggle('show');

        if (notificationToggleButton) {
            notificationToggleButton.setAttribute('aria-expanded', notificationDropdown.classList.contains('show') ? 'true' : 'false');
        }
    }

    function openLogoutConfirm(form) {
        pendingLogoutForm = form;

        if (!logoutConfirmDialog) {
            form.submit();
            return;
        }

        const profileDropdown = document.getElementById('profileDropdown');

        if (profileDropdown) {
            profileDropdown.classList.remove('show');
        }

        if (profileToggleButton) {
            profileToggleButton.setAttribute('aria-expanded', 'false');
        }

        logoutConfirmDialog.classList.add('show');
        logoutConfirmDialog.setAttribute('aria-hidden', 'false');
        document.body.classList.add('logout-confirm-open');

        const dialogCard = logoutConfirmDialog.querySelector('.logout-confirm-card');

        if (dialogCard) {
            dialogCard.focus();
        }
    }

    function closeLogoutConfirm() {
        if (!logoutConfirmDialog) {
            return;
        }

        logoutConfirmDialog.classList.remove('show');
        logoutConfirmDialog.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('logout-confirm-open');
        pendingLogoutForm = null;
    }

    function closeToast(toast) {
        if (!toast) {
            return;
        }

        toast.classList.add('is-hiding');
        window.setTimeout(function() {
            toast.remove();
        }, 180);
    }

    function setupToast(toast) {
        if (!toast) {
            return;
        }

        const closeButton = toast.querySelector('[data-toast-close]');
        const isDanger = toast.classList.contains('app-toast-danger');
        const timeout = isDanger ? 8500 : 6200;

        if (closeButton) {
            closeButton.addEventListener('click', function() {
                closeToast(toast);
            });
        }

        window.setTimeout(function() {
            closeToast(toast);
        }, timeout);
    }

    window.showAppToast = function(type, message, title) {
        const stack = document.getElementById('appToastStack');

        if (!stack || !message) {
            return;
        }

        const normalizedType = type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'danger');
        const iconClass = normalizedType === 'success'
            ? 'bi-check2-circle'
            : (normalizedType === 'warning' ? 'bi-exclamation-circle' : 'bi-exclamation-triangle');
        const defaultTitle = normalizedType === 'success'
            ? 'Berhasil'
            : (normalizedType === 'warning' ? 'Perhatian' : 'Perlu Diperiksa');

        const toast = document.createElement('div');
        toast.className = 'app-toast app-toast-' + normalizedType;
        toast.setAttribute('role', normalizedType === 'success' ? 'status' : 'alert');
        toast.setAttribute('data-app-toast', '');
        toast.innerHTML = `
            <div class="app-toast-icon"><i class="bi ${iconClass}"></i></div>
            <div class="app-toast-copy">
                <strong>${title || defaultTitle}</strong>
                <span></span>
            </div>
            <button type="button" class="app-toast-close" aria-label="Tutup notifikasi" data-toast-close>
                <i class="bi bi-x-lg"></i>
            </button>
        `;

        toast.querySelector('.app-toast-copy span').textContent = message;
        stack.appendChild(toast);
        setupToast(toast);
    };

    function openActionConfirm(form, submitter) {
        pendingConfirmForm = form;
        pendingConfirmSubmitter = submitter || null;

        if (!appConfirmDialog) {
            form.submit();
            return;
        }

        const type = form.dataset.confirmType || submitter?.dataset.confirmType || 'danger';
        const title = form.dataset.confirmTitle || submitter?.dataset.confirmTitle || 'Konfirmasi Aksi';
        const message = form.dataset.confirmMessage || submitter?.dataset.confirmMessage || 'Pastikan data yang dipilih sudah benar sebelum melanjutkan.';
        const buttonText = form.dataset.confirmSubmit || submitter?.dataset.confirmSubmit || 'Lanjutkan';
        const iconClass = form.dataset.confirmIcon || submitter?.dataset.confirmIcon || (type === 'success' ? 'bi-check2-circle' : (type === 'warning' ? 'bi-exclamation-circle' : 'bi-trash3'));

        appConfirmDialog.dataset.confirmType = type;
        appConfirmTitle.textContent = title;
        appConfirmMessage.textContent = message;
        appConfirmSubmitText.textContent = buttonText;
        appConfirmIcon.className = 'bi ' + iconClass;
        appConfirmIconWrap.className = 'app-confirm-icon app-confirm-' + type;
        appConfirmSubmit.className = 'app-confirm-submit app-confirm-submit-' + type;

        appConfirmSubmit.disabled = false;
        appConfirmSubmit.classList.remove('is-loading');
        appConfirmDialog.classList.add('show');
        appConfirmDialog.setAttribute('aria-hidden', 'false');
        document.body.classList.add('app-confirm-open');

        const dialogCard = appConfirmDialog.querySelector('.app-confirm-card');

        if (dialogCard) {
            dialogCard.focus();
        }
    }

    function closeActionConfirm() {
        if (!appConfirmDialog) {
            return;
        }

        appConfirmDialog.classList.remove('show');
        appConfirmDialog.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('app-confirm-open');
        pendingConfirmForm = null;
        pendingConfirmSubmitter = null;
    }

    function submitPendingConfirmForm() {
        if (!pendingConfirmForm) {
            closeActionConfirm();
            return;
        }

        const submitter = pendingConfirmSubmitter || pendingConfirmForm.querySelector('button[type="submit"], input[type="submit"]');

        appConfirmSubmit.disabled = true;
        appConfirmSubmit.classList.add('is-loading');

        if (submitter) {
            submitter.classList.add('is-loading');
            submitter.setAttribute('aria-busy', 'true');
            submitter.disabled = true;
        }

        pendingConfirmForm.dataset.confirmed = 'true';
        pendingConfirmForm.submit();
    }

    document.addEventListener('click', function(e) {
        const profileDropdown = document.getElementById('profileDropdown');
        const profileButton = e.target.closest('.profile-button');
        const profileArea = e.target.closest('#profileDropdown');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationButton = e.target.closest('.notification-button');
        const notificationArea = e.target.closest('#notificationDropdown');

        if (profileDropdown && !profileButton && !profileArea) {
            profileDropdown.classList.remove('show');
            if (profileToggleButton) {
                profileToggleButton.setAttribute('aria-expanded', 'false');
            }
        }

        if (notificationDropdown && !notificationButton && !notificationArea) {
            notificationDropdown.classList.remove('show');
            if (notificationToggleButton) {
                notificationToggleButton.setAttribute('aria-expanded', 'false');
            }
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') {
            return;
        }

        const profileDropdown = document.getElementById('profileDropdown');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const backdrop = document.getElementById('mobileSidebarBackdrop');

        if (appConfirmDialog && appConfirmDialog.classList.contains('show')) {
            closeActionConfirm();
            return;
        }

        if (logoutConfirmDialog && logoutConfirmDialog.classList.contains('show')) {
            closeLogoutConfirm();
            return;
        }

        if (profileDropdown) {
            profileDropdown.classList.remove('show');
        }

        if (notificationDropdown) {
            notificationDropdown.classList.remove('show');
        }

        if (profileToggleButton) {
            profileToggleButton.setAttribute('aria-expanded', 'false');
        }

        if (notificationToggleButton) {
            notificationToggleButton.setAttribute('aria-expanded', 'false');
        }

        if (window.innerWidth <= 768) {
            sidebar.classList.remove('mobile-open');
            document.body.classList.remove('mobile-sidebar-open');

            if (backdrop) {
                backdrop.classList.remove('show');
            }
        }

        updateSidebarToggleState();
    });

    document.addEventListener('DOMContentLoaded', function() {
        const sidebarState = localStorage.getItem('sidebar_state');

        if (sidebarState === 'collapsed' && window.innerWidth > 768) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
            document.body.classList.add('sidebar-is-collapsed');
        }

        updateSidebarToggleState();

        applyAppTheme(getPreferredAppTheme());

        if (themeToggleButton) {
            themeToggleButton.addEventListener('click', function() {
                const currentTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                applyAppTheme(currentTheme === 'dark' ? 'light' : 'dark');
            });
        }

        document.querySelectorAll('form[data-confirm-logout="true"]').forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (form.dataset.logoutConfirmed === 'true') {
                    return;
                }

                event.preventDefault();
                openLogoutConfirm(form);
            });
        });

        document.querySelectorAll('[data-logout-cancel]').forEach(function(button) {
            button.addEventListener('click', closeLogoutConfirm);
        });

        if (logoutConfirmSubmit) {
            logoutConfirmSubmit.addEventListener('click', function() {
                if (!pendingLogoutForm) {
                    closeLogoutConfirm();
                    return;
                }

                logoutConfirmSubmit.disabled = true;
                logoutConfirmSubmit.classList.add('is-loading');
                pendingLogoutForm.dataset.logoutConfirmed = 'true';
                pendingLogoutForm.submit();
            });
        }

        document.querySelectorAll('[data-app-toast]').forEach(setupToast);

        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(event) {
                const submitter = event.submitter || null;
                const needsConfirm = form.dataset.confirmAction === 'true' || submitter?.dataset.confirmAction === 'true';

                if (!needsConfirm) {
                    return;
                }

                if (form.dataset.confirmed === 'true') {
                    return;
                }

                event.preventDefault();
                openActionConfirm(form, submitter);
            });
        });

        document.querySelectorAll('[data-confirm-cancel]').forEach(function(button) {
            button.addEventListener('click', closeActionConfirm);
        });

        if (appConfirmSubmit) {
            appConfirmSubmit.addEventListener('click', submitPendingConfirmForm);
        }

        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (form.dataset.noLoading === 'true') {
                    return;
                }

                if (event.defaultPrevented) {
                    return;
                }

                const submitter = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');

                if (!submitter || submitter.disabled) {
                    return;
                }

                submitter.classList.add('is-loading');
                submitter.setAttribute('aria-busy', 'true');
                submitter.disabled = true;
            });
        });
    });
</script>

<script>
    function togglePasswordField(button) {
        const wrapper = button.closest('.password-wrapper');
        const input = wrapper.querySelector('input');
        const icon = button.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
</script>

@stack('scripts')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
