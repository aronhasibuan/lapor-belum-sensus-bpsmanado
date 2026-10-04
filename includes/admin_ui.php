<?php
// includes/admin_ui.php
// Komponen tampilan bersama untuk halaman admin: badge petugas & periode.

require_once __DIR__ . '/petugas_lib.php';

function h($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function wa_link_number($phone)
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '0')) {
        return '62' . substr($digits, 1);
    }
    return $digits;
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

/** Badge periode laporan. */
function badge_periode($periode)
{
    if (periode_is_revisit($periode)) {
        return '<span class="badge badge-periode-revisit"><i class="bi bi-arrow-repeat me-1"></i>' . h(periode_label($periode)) . '</span>';
    }
    return '<span class="badge badge-periode-sensus"><i class="bi bi-clipboard-data me-1"></i>' . h(periode_label($periode)) . '</span>';
}

/**
 * Badge satu petugas. Warna dibedakan antara petugas SENSUS dan REVISIT,
 * dan petugas/nomor dummy diberi garis putus-putus merah.
 */
function badge_petugas(array $p, $periode)
{
    $periode = periode_is_revisit($periode) ? 'revisit' : 'sensus';
    $role = strtoupper($p['role'] ?? '');
    $class = 'badge-petugas badge-' . strtolower($role) . '-' . $periode;
    if (!empty($p['dummy'])) {
        $class .= ' badge-dummy';
    }
    $label = $role . ': ' . h($p['name']);
    $phone = trim((string) ($p['phone'] ?? ''));
    $tag_dummy = !empty($p['dummy']) ? ' <span class="tag-dummy">DUMMY</span>' : '';
    $wa = wa_link_number($phone);

    if ($wa === '') {
        return '<span class="badge ' . $class . '">' . $label . $tag_dummy . '</span>';
    }
    return '<a href="https://wa.me/' . $wa . '" target="_blank" rel="noopener noreferrer" class="badge ' . $class . '">'
        . $label . ' (' . h($phone) . ')' . $tag_dummy . '</a>';
}

function admin_ui_styles()
{
?>
    <style>
        .badge-petugas {
            display: block;
            text-align: left;
            white-space: normal;
            text-decoration: none;
            font-weight: 500;
            line-height: 1.35;
        }

        /* Petugas pendataan Sensus Ekonomi: biru / kuning / biru muda (seperti sebelumnya) */
        .badge-ppl-sensus {
            background: #0d6efd;
            color: #fff;
        }

        .badge-pml-sensus {
            background: #ffc107;
            color: #212529;
        }

        .badge-koseka-sensus {
            background: #cff4fc;
            color: #055160;
            border: 1px solid #9eeaf9;
        }

        /* Petugas Revisit: ungu / ungu muda / hijau toska */
        .badge-ppl-revisit {
            background: #6f42c1;
            color: #fff;
        }

        .badge-pml-revisit {
            background: #e2d9f3;
            color: #432874;
            border: 1px solid #c5b3e6;
        }

        .badge-koseka-revisit {
            background: #d2f4ea;
            color: #0f5132;
            border: 1px solid #a6e9d5;
        }

        .badge-petugas:hover {
            filter: brightness(0.95);
        }

        .badge-dummy {
            outline: 2px dashed #dc3545;
            outline-offset: -2px;
        }

        .tag-dummy {
            background: #dc3545;
            color: #fff;
            border-radius: 4px;
            padding: 0 4px;
            font-size: .65rem;
            font-weight: 700;
            margin-left: 2px;
        }

        .badge-periode-sensus {
            background: #e9ecef;
            color: #495057;
            border: 1px solid #ced4da;
        }

        .badge-periode-revisit {
            background: #6f42c1;
            color: #fff;
        }

        tr.row-periode-pendataan>td:first-child {
            box-shadow: inset 4px 0 0 #adb5bd;
        }

        tr.row-periode-revisit_1>td:first-child {
            box-shadow: inset 4px 0 0 #198754;
        }

        tr.row-periode-revisit_2>td:first-child {
            box-shadow: inset 4px 0 0 #6f42c1;
        }

        .nav-periode .nav-link {
            color: #495057;
            font-weight: 600;
        }

        .nav-periode .nav-link.active.periode-semua {
            background: #0b1f33;
            color: #fff;
        }

        .nav-periode .nav-link.active.periode-sensus {
            background: #6c757d;
            color: #fff;
        }

        .nav-periode .nav-link.active.periode-revisit {
            background: #6f42c1;
            color: #fff;
        }

        .legend-swatch {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 3px;
            vertical-align: -1px;
            margin-right: 4px;
        }

        .text-revisit {
            color: #6f42c1 !important;
        }

        .bg-revisit-subtle {
            background: #efe8fb !important;
        }

        .border-revisit {
            border-color: #6f42c1 !important;
        }
    </style>
<?php
}
