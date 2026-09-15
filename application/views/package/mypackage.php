<?php
/* ═══════════════════════════════════════════════════════
   PHP HELPERS
   ═══════════════════════════════════════════════════════ */

function pkg_type_meta($type)
{
    $map = [
        'Monthly'   => ['color' => '#4a90d9', 'light' => '#eaf3fd', 'icon' => 'fe fe-refresh-cw',  'label' => 'Monthly'],
        'Quarterly' => ['color' => '#17a2b8', 'light' => '#e8f7f9', 'icon' => 'fe fe-calendar',    'label' => 'Quarterly'],
        'Yearly'    => ['color' => '#28a745', 'light' => '#eaf7ee', 'icon' => 'fe fe-award',        'label' => 'Yearly'],
        'Once'      => ['color' => '#e67e22', 'light' => '#fef4ec', 'icon' => 'fe fe-zap',          'label' => 'One-Time'],
    ];
    return isset($map[$type]) ? $map[$type] : ['color' => '#6c757d', 'light' => '#f0f0f0', 'icon' => 'fe fe-package', 'label' => $type];
}

function pkg_type_badge_inline($type)
{
    $meta = pkg_type_meta($type);
    return '<span class="pkg-type-pill" style="background:' . $meta['light'] . ';color:' . $meta['color'] . ';border:1px solid ' . $meta['color'] . '40">'
        . '<i class="' . $meta['icon'] . ' me-1" style="font-size:.7rem"></i>'
        . htmlspecialchars($meta['label'])
        . '</span>';
}

function payment_state($pkg)
{
    $expiry  = !empty($pkg['expiry_date']) ? strtotime($pkg['expiry_date']) : 0;
    $expired = $expiry && $expiry <= time();
    if ($expired) return ['label' => 'Expired – Renew',  'cls' => 'state-overdue', 'icon' => 'fe fe-alert-circle'];
    return                ['label' => 'Active',            'cls' => 'state-active',  'icon' => 'fe fe-check-circle'];
}
?>
<!-- ══════════════════════════════════════════════════════════════════════
     PAGE STYLES
     ══════════════════════════════════════════════════════════════════════ -->
<style>
    /* ── design tokens ── */
    :root {
        --pkg-radius: 12px;
        --pkg-shadow: 0 2px 8px rgba(0, 0, 0, .06);
        --pkg-shadow-hover: 0 8px 24px rgba(0, 0, 0, .12);
        --pkg-border: #e8ecef;
        --transition: .25s cubic-bezier(0.4, 0, 0.2, 1);
        --section-gap: 2.5rem;
        --spacing-xs: 0.5rem;
        --spacing-sm: 1rem;
        --spacing-md: 1.5rem;
        --spacing-lg: 2rem;
        --spacing-xl: 3rem;
    }

    /* ── page wrapper ── */
    .pkg-page {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 var(--spacing-md);
    }

    /* ── section title ── */
    .pkg-section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #495057;
        margin: var(--spacing-xl) 0 var(--spacing-md) 0;
        padding-bottom: var(--spacing-sm);
        border-bottom: 2px solid var(--pkg-border);
    }

    .pkg-section-title:first-of-type {
        margin-top: 0;
    }

    .pkg-section-title i {
        font-size: .9rem;
        color: #adb5bd;
    }

    /* ── type pill ── */
    .pkg-type-pill {
        display: inline-flex;
        align-items: center;
        font-size: .71rem;
        font-weight: 600;
        padding: 3px 9px;
        border-radius: 20px;
        letter-spacing: .2px;
        white-space: nowrap;
        line-height: 1.5;
    }

    /* ── state badges ── */
    .state-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: .71rem;
        font-weight: 600;
        padding: 3px 9px;
        border-radius: 20px;
        white-space: nowrap;
    }

    .state-paid {
        background: #d1f0da;
        color: #155724;
    }

    /* state-paid kept for backward compat with account section */

    .state-active {
        background: #fff3cd;
        color: #856404;
    }

    .state-overdue {
        background: #fde8ea;
        color: #721c24;
    }

    /* ── stat chips ── */
    .stat-chips {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: var(--spacing-md);
        margin-bottom: var(--spacing-xl);
    }

    .stat-chip {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border: 1px solid var(--pkg-border);
        border-radius: var(--pkg-radius);
        padding: var(--spacing-md) var(--spacing-lg);
        box-shadow: var(--pkg-shadow);
        transition: all var(--transition);
        position: relative;
        overflow: hidden;
    }

    .stat-chip::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(180deg, #4a90d9 0%, #357abd 100%);
    }

    .stat-chip:hover {
        transform: translateY(-2px);
        box-shadow: var(--pkg-shadow-hover);
        border-color: #d0d7de;
    }

    .stat-chip .chip-icon {
        font-size: 1.5rem;
        line-height: 1;
        opacity: 0.9;
    }

    .stat-chip .chip-val {
        font-size: 1.4rem;
        font-weight: 800;
        line-height: 1.2;
        color: #212529;
        letter-spacing: -0.5px;
    }

    .stat-chip .chip-lbl {
        font-size: .75rem;
        color: #6c757d;
        margin-top: 4px;
        font-weight: 500;
        letter-spacing: 0.3px;
    }

    /* ── generic card wrapper ── */
    .pkg-card-wrap {
        background: #fff;
        border: 1px solid var(--pkg-border);
        border-radius: var(--pkg-radius);
        box-shadow: var(--pkg-shadow);
        overflow: hidden;
        transition: all var(--transition);
        margin-bottom: var(--spacing-lg);
    }

    .pkg-card-wrap:hover {
        box-shadow: var(--pkg-shadow-hover);
        transform: translateY(-4px);
        border-color: #d0d7de;
    }

    /* ── existing package card ── */
    .pkg-card {
        border-radius: var(--pkg-radius);
        border: 1px solid var(--pkg-border);
        box-shadow: var(--pkg-shadow);
        transition: all var(--transition);
        overflow: hidden;
        background: #fff;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .pkg-card:hover {
        box-shadow: var(--pkg-shadow-hover);
        transform: translateY(-4px);
        border-color: #d0d7de;
    }

    .pkg-card.is-overdue {
        border-left: 4px solid #dc3545;
    }

    .pkg-card.is-paid {
        border-left: 4px solid #28a745;
    }

    .pkg-card.is-active {
        border-left: 4px solid #ffc107;
    }

    .pkg-card-header {
        padding: var(--spacing-md) var(--spacing-lg);
        border-bottom: 1px solid #f0f2f4;
        background: linear-gradient(135deg, #fafbfc 0%, #ffffff 100%);
    }

    .pkg-card-body {
        padding: 0;
    }

    /* ── service list inside card ── */
    .svc-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .svc-list li {
        display: flex;
        align-items: center;
        padding: var(--spacing-sm) var(--spacing-lg);
        border-bottom: 1px solid #f4f5f7;
        font-size: .875rem;
        gap: var(--spacing-sm);
        transition: background-color var(--transition);
    }

    .svc-list li:hover {
        background-color: #f8f9fa;
    }

    .svc-list li:last-child {
        border-bottom: none;
    }

    .svc-list li .svc-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .svc-list li .svc-name {
        flex: 1;
        font-weight: 500;
        color: #2c3e50;
    }

    .svc-list li .svc-for {
        font-size: .73rem;
        color: #8d95a0;
        white-space: nowrap;
    }

    .svc-list li .svc-date {
        font-size: .73rem;
        color: #555;
        white-space: nowrap;
    }

    .svc-list li .svc-rate {
        font-weight: 600;
        color: #2c3e50;
        white-space: nowrap;
        font-size: .875rem;
    }

    /* ── card info strip ── */
    .pkg-info-strip {
        display: flex;
        flex-wrap: wrap;
        border-top: 1px solid #f0f2f4;
    }

    .pkg-info-item {
        flex: 1 1 33%;
        padding: var(--spacing-sm) var(--spacing-lg);
        border-right: 1px solid #f0f2f4;
        font-size: .8rem;
    }

    .pkg-info-item:last-child {
        border-right: none;
    }

    .pkg-info-item .label {
        color: #9aa1aa;
        font-size: .68rem;
        text-transform: uppercase;
        letter-spacing: .5px;
        margin-bottom: 3px;
    }

    .pkg-info-item .value {
        font-weight: 600;
        color: #2c3e50;
    }

    .pkg-info-item .value.danger {
        color: #dc3545;
    }

    .pkg-info-item .value.success {
        color: #28a745;
    }

    /* ── bill alert strip ── */
    .pkg-bill-alert {
        margin: 0;
        border-radius: 0;
        padding: var(--spacing-sm) var(--spacing-lg);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: var(--spacing-sm);
        font-size: .875rem;
        border: none;
        border-top: 1px solid rgba(0, 0, 0, .06);
    }

    /* ── card actions ── */
    .pkg-actions {
        padding: var(--spacing-sm) var(--spacing-lg);
        border-top: 1px solid #f0f2f4;
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: var(--spacing-sm);
        background: linear-gradient(135deg, #fafbfc 0%, #ffffff 100%);
        margin-top: auto;
    }

    /* ── Account Work card ── */
    .acct-status-row {
        display: flex;
        align-items: stretch;
        border-left: 5px solid #28a745;
        background: #fff;
    }

    .acct-status-body {
        flex: 1;
        padding: var(--spacing-md) var(--spacing-lg);
    }

    .acct-status-icon {
        display: flex;
        align-items: center;
        padding: 0 20px;
        font-size: 2rem;
        color: #28a745;
        opacity: .25;
    }

    .acct-rate-section {
        border-top: 1px solid #f0f2f4;
        padding: var(--spacing-md) var(--spacing-lg) var(--spacing-lg);
        background: #fafbfc;
    }

    .acct-rate-section .rate-title {
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: #8d95a0;
        margin-bottom: 10px;
    }

    .acct-form-body {
        padding: var(--spacing-lg) var(--spacing-xl);
    }

    /* ── Account Work Service Table ── */
    .acct-form-body .table-responsive {
        border-radius: var(--pkg-radius);
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        margin-bottom: var(--spacing-md);
    }

    .acct-form-body .table {
        margin-bottom: 0;
        background: #fff;
    }

    .acct-form-body .table thead th {
        background: linear-gradient(135deg, #f7f8fa 0%, #ffffff 100%);
        color: #495057;
        font-weight: 700;
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: var(--spacing-sm) var(--spacing-md);
        border-bottom: 2px solid var(--pkg-border);
        white-space: nowrap;
    }

    .acct-form-body .table tbody td {
        padding: var(--spacing-sm) var(--spacing-md);
        vertical-align: middle;
        color: #495057;
    }

    .acct-form-body .table tbody tr {
        transition: background-color var(--transition);
    }

    .acct-form-body .table tbody tr:hover {
        background-color: #f8f9fa;
    }

    /* ── Create Package panel ── */
    .create-panel {
        border-radius: var(--pkg-radius);
        box-shadow: var(--pkg-shadow);
        border: 1px solid var(--pkg-border);
        overflow: hidden;
        background: #fff;
    }

    .create-panel .panel-header {
        background: linear-gradient(135deg, #2c3e50 0%, #3a5068 100%);
        padding: var(--spacing-md) var(--spacing-xl);
        color: #fff;
    }

    .create-panel .panel-header h5 {
        margin: 0;
        font-size: .95rem;
        font-weight: 600;
    }

    .create-panel .panel-header p {
        margin: 4px 0 0;
        font-size: .8rem;
        opacity: .7;
    }

    /* ── Account Work panel header ── */
    .pkg-card-wrap .panel-header {
        background: linear-gradient(135deg, #2c3e50 0%, #3a5068 100%);
        padding: var(--spacing-md) var(--spacing-xl);
        color: #fff;
    }

    .pkg-card-wrap .panel-header h5 {
        margin: 0;
        font-size: .95rem;
        font-weight: 600;
    }

    .pkg-card-wrap .panel-header p {
        margin: 4px 0 0;
        font-size: .8rem;
        opacity: .7;
    }

    /* type-lock banner */
    #type-lock-banner {
        padding: 9px 20px;
        background: #eaf3fd;
        border-bottom: 1px solid #c5ddf7;
        display: none;
        align-items: center;
        gap: 10px;
        font-size: .84rem;
        color: #1a5fa8;
    }

    #type-lock-banner i {
        font-size: .95rem;
    }

    /* service selector table */
    #svc-table {
        margin: 0;
    }

    #svc-table thead th {
        background: linear-gradient(135deg, #f7f8fa 0%, #ffffff 100%);
        color: #495057;
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        border-bottom: 2px solid #e6e9ec;
        padding: var(--spacing-sm) var(--spacing-md);
        white-space: nowrap;
    }

    #svc-table tbody td {
        padding: var(--spacing-sm) var(--spacing-md);
        vertical-align: middle;
        font-size: .875rem;
    }

    #svc-table tbody tr {
        transition: background var(--transition);
    }

    #svc-table tbody tr.row-selected {
        background: #f0fbf2 !important;
    }

    #svc-table tbody tr.row-disabled {
        opacity: .4;
        pointer-events: none;
    }

    #svc-table tbody tr.row-inpkg {
        background: #f8f9fa;
    }

    /* custom checkbox */
    .svc-check-wrap {
        display: flex;
        justify-content: center;
    }

    .svc-check {
        width: 1.1rem;
        height: 1.1rem;
        cursor: pointer;
        accent-color: #28a745;
    }

    /* option select */
    .opt-select {
        font-size: .8rem;
        padding: 4px 8px;
        border-radius: 6px;
        border: 1px solid #dee2e6;
        min-width: 150px;
        background: #fff;
    }

    /* fix double dropdown arrow on form-select - aggressive fix */
    select.form-select,
    select#acct-pkg-select,
    #acct-pkg-select.form-select,
    #acct-pkg-select {
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        appearance: none !important;
        /* Ensure Bootstrap's arrow is the only one showing */
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 0.75rem center !important;
        background-size: 16px 12px !important;
        padding-right: 2.25rem !important;
    }

    /* Hide native arrow in IE/Edge */
    select.form-select::-ms-expand,
    select#acct-pkg-select::-ms-expand,
    #acct-pkg-select::-ms-expand {
        display: none !important;
        opacity: 0 !important;
        width: 0 !important;
        height: 0 !important;
    }

    /* For WebKit browsers - ensure native arrow is hidden */
    select.form-select::-webkit-inner-spin-button,
    select.form-select::-webkit-outer-spin-button,
    #acct-pkg-select::-webkit-inner-spin-button,
    #acct-pkg-select::-webkit-outer-spin-button {
        -webkit-appearance: none !important;
        margin: 0 !important;
    }

    /* For form-select-sm specifically */
    select.form-select-sm,
    #acct-pkg-select.form-select-sm {
        background-size: 16px 12px !important;
        background-position: right 0.5rem center !important;
        padding-right: 1.75rem !important;
    }

    .opt-select:disabled {
        background: #f8f9fa;
        cursor: not-allowed;
    }

    /* rate cell */
    .rate-cell {
        font-weight: 600;
        color: #2c3e50;
        font-size: .875rem;
    }

    /* ── inline save bar ── */
    #pkg-sticky-bar {
        display: none;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: var(--spacing-sm);
        background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
        color: #fff;
        padding: var(--spacing-md) var(--spacing-lg);
        border-top: 1px solid rgba(255, 255, 255, .1);
        box-shadow: 0 -4px 12px rgba(0, 0, 0, .15);
    }

    #pkg-sticky-bar .bar-left {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    #pkg-sticky-bar .bar-total {
        font-size: 1.2rem;
        font-weight: 800;
        letter-spacing: -0.5px;
    }

    #pkg-sticky-bar .bar-count {
        font-size: .8rem;
        opacity: .8;
    }

    #pkg-sticky-bar .bar-type {
        font-size: .76rem;
        background: rgba(255, 255, 255, .14);
        padding: 3px 11px;
        border-radius: 20px;
    }

    /* ── empty state ── */
    .empty-pkg {
        text-align: center;
        padding: 48px 24px;
        color: #bbb;
    }

    .empty-pkg i {
        font-size: 2.8rem;
        display: block;
        margin-bottom: 12px;
    }

    .empty-pkg p {
        font-size: .88rem;
        margin: 0;
    }

    /* ── responsive ── */
    @media (max-width: 992px) {
        .pkg-page {
            padding: 0 var(--spacing-sm);
        }

        .stat-chips {
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: var(--spacing-sm);
        }
    }

    @media (max-width: 768px) {
        .pkg-page {
            padding: 0 var(--spacing-sm);
        }

        .stat-chips {
            grid-template-columns: repeat(2, 1fr);
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-lg);
        }

        .stat-chip {
            padding: var(--spacing-sm) var(--spacing-md);
        }

        .pkg-section-title {
            margin-top: var(--spacing-lg);
            margin-bottom: var(--spacing-sm);
            font-size: .7rem;
        }

        .pkg-card-header {
            padding: var(--spacing-sm) var(--spacing-md);
        }

        .svc-list li {
            padding: var(--spacing-xs) var(--spacing-md);
            flex-wrap: wrap;
        }

        .pkg-info-item {
            flex: 1 1 50%;
            padding: var(--spacing-xs) var(--spacing-md);
        }

        .acct-form-body {
            padding: var(--spacing-md);
        }

        .acct-form-body .col-md-4,
        .acct-form-body .col-md-6 {
            margin-bottom: var(--spacing-md);
        }

        #svc-table {
            font-size: .8rem;
        }

        #svc-table thead th,
        #svc-table tbody td {
            padding: var(--spacing-xs) var(--spacing-sm);
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
    }

    @media (max-width: 576px) {
        .pkg-page {
            padding: 0 var(--spacing-xs);
        }

        .stat-chips {
            grid-template-columns: 1fr;
            gap: var(--spacing-sm);
        }

        .pkg-info-item {
            flex: 1 1 100%;
            border-right: none;
            border-bottom: 1px solid #f0f2f4;
        }

        .pkg-info-item:last-child {
            border-bottom: none;
        }

        .pkg-info-strip {
            flex-direction: column;
        }

        .acct-form-body {
            padding: var(--spacing-sm);
        }

        .pkg-actions {
            flex-direction: column;
        }

        .pkg-actions .btn {
            width: 100%;
        }

        .pkg-bill-alert {
            flex-direction: column;
            align-items: flex-start !important;
        }

        .pkg-bill-alert .btn {
            width: 100%;
            margin-top: var(--spacing-xs);
        }

        #pkg-sticky-bar {
            flex-direction: column;
            align-items: stretch;
        }

        #pkg-sticky-bar .btn {
            width: 100%;
        }
    }
</style>

<?php
/* ════════════════════════════════════════════════════════
   SECTION 0 – Summary chips
   ════════════════════════════════════════════════════════ */
$total_pkg    = !empty($service_packages) ? count($service_packages) : 0;
$active_pkg   = 0;
$overdue_pkg  = 0;
$total_billed = 0;
if (!empty($service_packages)) {
    foreach ($service_packages as $_p) {
        $state = payment_state($_p);
        if ($state['cls'] === 'state-active')  $active_pkg++;
        if ($state['cls'] === 'state-overdue') $overdue_pkg++;
        $total_billed += (float)($_p['bill_amount'] ?? 0);
    }
}
?>
<div class="pkg-page">
    <div class="stat-chips">
        <div class="stat-chip">
            <span class="chip-icon" style="color:#4a90d9"><i class="fe fe-package"></i></span>
            <div class="chip-text">
                <div class="chip-val"><?= $total_pkg ?></div>
                <div class="chip-lbl">Total Packages</div>
            </div>
        </div>
        <div class="stat-chip">
            <span class="chip-icon" style="color:#f6c23e"><i class="fe fe-clock"></i></span>
            <div class="chip-text">
                <div class="chip-val"><?= $active_pkg ?></div>
                <div class="chip-lbl">Active</div>
            </div>
        </div>
        <div class="stat-chip">
            <span class="chip-icon" style="color:#e74a3b"><i class="fe fe-alert-circle"></i></span>
            <div class="chip-text">
                <div class="chip-val"><?= $overdue_pkg ?></div>
                <div class="chip-lbl">Expired</div>
            </div>
        </div>
        <div class="stat-chip">
            <span class="chip-icon" style="color:#6f42c1"><i class="fe fe-credit-card"></i></span>
            <div class="chip-text">
                <div class="chip-val">₹<?= number_format($total_billed, 0) ?></div>
                <div class="chip-lbl">Total Billed</div>
            </div>
        </div>
    </div>

    <?php
    /* ════════════════════════════════════════════════════════
   SECTION 0-B – Account Work (Accountancy Package)
   ════════════════════════════════════════════════════════ */
    ?>
    <div class="pkg-section-title">
        <i class="fe fe-briefcase"></i> Account Work
    </div>

    <div class="pkg-card-wrap mb-4" id="acct-work-card">

        <?php if (!empty($package)) :
            $pkg_type = !empty($package['package_type']) ? $package['package_type'] : 'Turnover';
            // For Monthly type, don't show package name (Prime/Premium)
            // For Turnover type, show package name
            $acct_name = '';
            if ($pkg_type == 'Monthly') {
                $acct_name = 'Account Work Monthly';
            } else {
                $acct_name = !empty($package['package_id']) && $package['package_id'] == 1 ? 'Accountancy Prime' : (!empty($package['package_id']) && $package['package_id'] == 2 ? 'Accountancy Premium' : 'Account Work');
            }
            $expiry_ts = !empty($package['expiry_date']) ? strtotime($package['expiry_date']) : 0;
            $is_expired = $expiry_ts && $expiry_ts <= time();
            $is_unpaid = empty($package['payment_status']) || $package['payment_status'] == 0;

            // Determine status
            if ($is_expired && $is_unpaid) {
                $status_class = 'state-overdue';
                $status_icon = 'fe-alert-circle';
                $status_label = ($pkg_type == 'Monthly') ? 'Pending' : 'Expired – Renew';
            } else {
                $status_class = 'state-paid';
                $status_icon = 'fe-check-circle';
                $status_label = 'Active';
            }
        ?>
            <!-- ── Already selected ── -->
            <div class="acct-status-row" style="border-left-color: <?= $is_expired && $is_unpaid ? '#dc3545' : '#28a745' ?>;">
                <div class="acct-status-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="pkg-type-pill" style="background:#eaf7ee;color:#28a745;border:1px solid #28a74540">
                                <i class="fe fe-briefcase me-1" style="font-size:.68rem"></i>
                                <?= htmlspecialchars($acct_name) ?>
                            </span>
                            <span class="state-pill <?= $status_class ?>">
                                <i class="fe <?= $status_icon ?>"></i> <?= $status_label ?>
                            </span>
                            <span class="pkg-type-pill" style="background:#f0f9f1;color:#3a7d44;border:1px solid #b7e0be">
                                <i class="fe fe-trending-up me-1" style="font-size:.68rem"></i><?= htmlspecialchars($pkg_type) ?>
                            </span>
                            <?php if ($pkg_type == 'Monthly' && !empty($package['amount'])) : ?>
                                <span class="pkg-type-pill" style="background:#e3f2fd;color:#1976d2;border:1px solid #90caf9">
                                    <i class="fe fe-rupee me-1" style="font-size:.68rem"></i>₹<?= number_format($package['amount'], 2) ?>/month
                                </span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <?php if (empty($service_packages)) : ?>
                                <form method="post" action="<?= base_url('package/requestdeleteaccountwork') ?>"
                                    class="delete-pkg-form"
                                    style="display:inline">
                                    <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fe fe-trash-2 me-1"></i>Delete Package
                                    </button>
                                </form>
                            <?php else : ?>
                                <span class="badge bg-secondary text-white px-2 py-1" style="font-size:.75rem;">
                                    <i class="fe fe-info me-1"></i>Delete Service Packages first
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-3" style="font-size:.82rem;color:#555;">
                        <?php if ($pkg_type == 'Monthly') : ?>
                            <span>
                                <i class="fe fe-calendar me-1 text-muted"></i>
                                Selected Month: <strong><?= date('F', strtotime($package['purchase_date'])) ?></strong>
                            </span>
                            <span class="<?= $is_expired && $is_unpaid ? 'text-danger fw-semibold' : '' ?>">
                                <i class="fe fe-clock me-1 text-muted"></i>
                                Renewal Month: <strong><?= date('F', $expiry_ts) ?></strong>
                            </span>
                        <?php else : ?>
                            <span>
                                <i class="fe fe-calendar me-1 text-muted"></i>
                                Selected: <strong><?= date('d M Y', strtotime($package['added_on'])) ?></strong>
                            </span>
                            <?php if (!empty($package['year'])) : ?>
                                <span>
                                    <i class="fe fe-file-text me-1 text-muted"></i>
                                    Year: <strong><?= htmlspecialchars($package['year']) ?></strong>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($package['expiry_date'])) : ?>
                                <span class="<?= $is_expired && $is_unpaid ? 'text-danger fw-semibold' : '' ?>">
                                    <i class="fe fe-clock me-1 text-muted"></i>
                                    Expires: <strong><?= date('d M Y', $expiry_ts) ?></strong>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($is_expired && $is_unpaid) :
                        $bill_amount = !empty($package['bill_amount']) ? (float)$package['bill_amount'] : 0;
                    ?>
                        <div class="mt-3 p-3 bg-light border" style="border-color:#dc3545!important;">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <strong class="text-danger">Package Expired!</strong>
                                    <div class="text-muted" style="font-size:.85rem;">
                                        Bill amount: <strong>₹<?= number_format($bill_amount, 2) ?></strong>
                                    </div>
                                </div>
                                <button class="btn btn-danger btn-sm renew-acct-work-btn"
                                    data-pkg-id="<?= $package['id'] ?>"
                                    data-amount="<?= $bill_amount ?>">
                                    <i class="fe fe-credit-card me-1"></i>Renew & Pay
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="acct-status-icon">
                    <i class="fe fe-briefcase"></i>
                </div>
            </div>

            <!-- ── Rate chart for selected package (only for Turnover type, not for Monthly) ── -->
            <?php if ($pkg_type != 'Monthly' && !empty($package['package_id'])) : ?>
                <?php
                $acct_slug = $package['package_id'] == 1 ? 'accountancy-prime' : 'accountancy-premium';
                $acct_rows = [];
                if (!empty($accountancy_packages)) {
                    foreach ($accountancy_packages as $_ap) {
                        if (generate_slug($_ap['name']) === $acct_slug) $acct_rows[] = $_ap;
                    }
                }
                ?>
                <?php if (!empty($acct_rows)) : ?>
                    <div class="acct-rate-section">
                        <div class="rate-title">
                            <i class="fe fe-bar-chart-2 me-1"></i><?= htmlspecialchars($acct_name) ?> — Rate Chart
                        </div>
                        <div class="table-responsive" style="max-width:460px;">
                            <table class="table table-bordered table-sm mb-0" style="font-size:.8rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Turnover Slab</th>
                                        <th>Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($acct_rows as $_r) : ?>
                                        <tr>
                                            <td><?= htmlspecialchars($_r['remarks']) ?></td>
                                            <td><strong class="text-success">₹<?= number_format($_r['rate'], 0) ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        <?php else : ?>
            <!-- ── Not selected – selection form ── -->
            <div class="panel-header">
                <h5><i class="fe fe-briefcase me-2"></i>Select Accountancy Package</h5>
                <p>Choose your account work plan based on firm turnover.</p>
            </div>
            <div class="acct-form-body">
                <?php
                // Get Account Work service (id=1) details
                $account_work_service = $this->master->getservices(['id' => 1, 'status' => 1], 'single');
                if (!empty($account_work_service)) :
                ?>
                    <!-- Account Work Service Details Table -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold mb-3" style="font-size:.9rem; color: #495057;">
                            <i class="fe fe-info me-2"></i>Account Work Service Details
                        </label>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0" style="font-size:.875rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 60px;">Sl.No.</th>
                                        <th>Service</th>
                                        <th class="text-end">Rate</th>
                                        <th>Service For</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>1</td>
                                        <td class="fw-semibold"><?= htmlspecialchars($account_work_service['name']) ?></td>
                                        <td class="text-end">
                                            <strong class="text-success">₹<?= number_format($account_work_service['rate'], 0) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border"><?= htmlspecialchars($account_work_service['service_for'] ?? 'N/A') ?></span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row g-3 align-items-start">
                    <!-- Type Selection Dropdown -->
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" style="font-size:.83rem">Type</label>
                        <select id="acct-type-select" class="form-select form-select-sm">
                            <option value="">— Choose Type —</option>
                            <option value="Turnover">Turnover</option>
                            <option value="Monthly">Monthly</option>
                        </select>
                    </div>

                    <!-- Start Month Calendar Input (shown when Monthly is selected) -->
                    <div class="col-md-3" id="acct-start-month-wrap" style="display:none;">
                        <label class="form-label fw-semibold" style="font-size:.83rem">Start Month</label>
                        <select id="acct-start-month" class="form-select form-select-sm">
                            <option value="04">April</option>
                            <option value="05">May</option>
                            <option value="06">June</option>
                            <option value="07">July</option>
                            <option value="08">August</option>
                            <option value="09">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                            <option value="01">January</option>
                            <option value="02">February</option>
                            <option value="03">March</option>
                        </select>
                    </div>

                    <!-- Monthly Calendar Input (Select Month / End Month) (shown when Monthly is selected) -->
                    <div class="col-md-3" id="acct-monthly-calendar-wrap" style="display:none;">
                        <label class="form-label fw-semibold" style="font-size:.83rem">Select Month</label>
                        <select id="acct-monthly-calendar" class="form-select form-select-sm">
                            <option value="">— Select Month —</option>
                            <option value="04">April</option>
                            <option value="05">May</option>
                            <option value="06">June</option>
                            <option value="07">July</option>
                            <option value="08">August</option>
                            <option value="09">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                            <option value="01">January</option>
                            <option value="02">February</option>
                            <option value="03">March</option>
                        </select>
                    </div>

                    <!-- Monthly Amount Input (shown when Monthly is selected) -->
                    <div class="col-md-3" id="acct-monthly-amount-wrap" style="display:none;">
                        <label class="form-label fw-semibold" style="font-size:.83rem">Monthly Amount (₹)</label>
                        <input type="number" id="acct-monthly-amount" class="form-control form-control-sm" 
                            placeholder="Enter monthly amount" min="1" step="0.01">
                        <small class="text-muted" style="font-size:.75rem">
                            <i class="fe fe-info me-1"></i>Amount will be auto-debited from wallet monthly
                        </small>
                    </div>
                </div>

                <!-- Confirm button for Monthly type (shown when amount is entered) -->
                <div class="row g-3 align-items-start mt-3" id="acct-confirm-wrap-monthly" style="display:none;">
                    <div class="col-12">
                        <button type="button" class="btn btn-success btn-sm px-4 fw-semibold" id="acct-confirm-btn-monthly">
                            <i class="fe fe-check me-2"></i>Confirm & Save Monthly Package
                        </button>
                    </div>
                </div>

                <div class="row g-3 align-items-start mt-2" id="acct-package-selection-wrap" style="display:none;">
                    <!-- Left: package dropdown -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" style="font-size:.83rem">Select Package</label>
                        <select id="acct-pkg-select" class="form-select form-select-sm">
                            <option value="">— Choose Package —</option>
                            <option value="accountancy-prime">Accountancy Prime</option>
                            <option value="accountancy-premium">Accountancy Premium</option>
                        </select>
                    </div>

                    <!-- Right: rates table (revealed on selection) -->
                    <div class="col-md-6" id="acct-rates-wrap" style="display:none;">
                        <label class="form-label fw-semibold" style="font-size:.83rem">Rate Chart</label>
                        <table class="table table-bordered table-sm mb-0" style="font-size:.8rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>Turnover Slab</th>
                                    <th>Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($accountancy_packages)) :
                                    foreach ($accountancy_packages as $ap) : ?>
                                        <tr class="acct-pkg-row <?= generate_slug($ap['name']) ?>" style="display:none;">
                                            <td><?= htmlspecialchars($ap['remarks']) ?></td>
                                            <td><strong class="text-success">₹<?= number_format($ap['rate'], 0) ?></strong></td>
                                        </tr>
                                <?php endforeach;
                                endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Confirm button for Turnover type (shown when package is selected) -->
                <div class="mt-3" id="acct-confirm-wrap" style="display:none;">
                    <button type="button" class="btn btn-success btn-sm px-4 fw-semibold" id="acct-confirm-btn">
                        <i class="fe fe-check me-2"></i>Confirm Selection
                    </button>
                </div>
            </div>
        <?php endif; ?>

    </div><!-- /#acct-work-card -->

    <!-- Account Work JS (self-contained, no modal) -->
    <script>
        (function() {
            var typeSel = document.getElementById('acct-type-select');
            var startMonthWrap = document.getElementById('acct-start-month-wrap');
            var startMonthInput = document.getElementById('acct-start-month');
            var monthlyCalendarWrap = document.getElementById('acct-monthly-calendar-wrap');
            var monthlyCalendarInput = document.getElementById('acct-monthly-calendar');
            var monthlyAmountWrap = document.getElementById('acct-monthly-amount-wrap');
            var monthlyAmountInput = document.getElementById('acct-monthly-amount');
            var packageSelectionWrap = document.getElementById('acct-package-selection-wrap');
            var pkgSel = document.getElementById('acct-pkg-select');
            var ratesWrap = document.getElementById('acct-rates-wrap');
            var confirmWrap = document.getElementById('acct-confirm-wrap');
            var confirmBtn = document.getElementById('acct-confirm-btn');
            var confirmWrapMonthly = document.getElementById('acct-confirm-wrap-monthly');
            var confirmBtnMonthly = document.getElementById('acct-confirm-btn-monthly');

            // Type selection handler
            if (typeSel) {
                typeSel.addEventListener('change', function() {
                    var selectedType = this.value;
                    
                    // Reset all dependent fields
                    if (startMonthWrap) {
                        startMonthWrap.style.display = 'none';
                        if (startMonthInput) startMonthInput.value = '04';
                    }
                    if (monthlyCalendarWrap) {
                        monthlyCalendarWrap.style.display = 'none';
                        monthlyCalendarInput.value = '';
                    }
                    monthlyAmountWrap.style.display = 'none';
                    monthlyAmountInput.value = '';
                    packageSelectionWrap.style.display = 'none';
                    pkgSel.value = '';
                    ratesWrap.style.display = 'none';
                    confirmWrap.style.display = 'none';
                    confirmWrapMonthly.style.display = 'none';
                    document.querySelectorAll('.acct-pkg-row').forEach(function(r) {
                        r.style.display = 'none';
                    });

                    if (selectedType === 'Monthly') {
                        // Show start month, select month and monthly amount inputs
                        if (startMonthWrap) startMonthWrap.style.display = 'block';
                        if (monthlyCalendarWrap) monthlyCalendarWrap.style.display = 'block';
                        monthlyAmountWrap.style.display = 'block';
                        // Hide package selection for Monthly type
                        packageSelectionWrap.style.display = 'none';
                    } else if (selectedType === 'Turnover') {
                        // Show package selection directly (no monthly amount needed)
                        packageSelectionWrap.style.display = 'block';
                    }
                });
            }

            // Package dropdown → show rates table (only for Turnover type)
            if (pkgSel) {
                pkgSel.addEventListener('change', function() {
                    var val = this.value;
                    var selectedType = typeSel ? typeSel.value : '';
                    
                    // Only process package selection for Turnover type
                    if (selectedType !== 'Turnover') {
                        return;
                    }
                    
                    document.querySelectorAll('.acct-pkg-row').forEach(function(r) {
                        r.style.display = 'none';
                    });
                    
                    if (val) {
                        document.querySelectorAll('.acct-pkg-row.' + val).forEach(function(r) {
                            r.style.display = '';
                        });
                        ratesWrap.style.display = '';
                        confirmWrap.style.display = 'block';
                    } else {
                        ratesWrap.style.display = 'none';
                        confirmWrap.style.display = 'none';
                    }
                });
            }

            // Monthly amount input handler
            if (monthlyAmountInput) {
                monthlyAmountInput.addEventListener('input', function() {
                    var amount = parseFloat(this.value);
                    var selectedType = typeSel ? typeSel.value : '';
                    
                    // For Monthly type, only amount is required (no package selection needed)
                    if (selectedType === 'Monthly' && amount > 0) {
                        confirmWrapMonthly.style.display = 'block';
                    } else if (selectedType === 'Monthly' && (!amount || amount <= 0)) {
                        confirmWrapMonthly.style.display = 'none';
                    }
                });
            }

            // Confirm button handler function (shared for both Monthly and Turnover)
            function handleConfirmClick(btnElement, isMonthly) {
                var selectedType = typeSel ? typeSel.value : '';
                var pkg_id = '';
                var amount = '';
                
                if (!selectedType) {
                    alert('Please select a type (Turnover or Monthly)!');
                    return;
                }
                
                var start_month_val = '';
                var month_val = '';
                if (selectedType === 'Monthly') {
                    start_month_val = startMonthInput ? startMonthInput.value : '04';
                    month_val = monthlyCalendarInput ? monthlyCalendarInput.value : '';
                    if (!month_val) {
                        alert('Please select a month!');
                        return;
                    }
                    function getFyIdx(m) {
                        var im = parseInt(m, 10);
                        return (im >= 4) ? (im - 3) : (im + 9);
                    }
                    if (getFyIdx(start_month_val) > getFyIdx(month_val)) {
                        alert('Start Month cannot be after Select Month!');
                        return;
                    }
                    amount = monthlyAmountInput ? monthlyAmountInput.value : '';
                    if (!amount || parseFloat(amount) <= 0) {
                        alert('Please enter a valid monthly amount!');
                        return;
                    }
                    // For Monthly type, set a default package_id (can be empty or use default)
                    // Backend will handle Monthly type without package_id requirement
                    pkg_id = ''; // No package selection for Monthly type
                } else if (selectedType === 'Turnover') {
                    // For Turnover type, package selection is required
                    pkg_id = pkgSel ? pkgSel.value : '';
                    if (!pkg_id) {
                        alert('Please select a package!');
                        return;
                    }
                }

                btnElement.disabled = true;
                var originalHtml = btnElement.innerHTML;
                btnElement.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing…';

                var fd = new FormData();
                fd.append('id', 1);
                fd.append('type', selectedType);
                fd.append('amount', amount);
                if (start_month_val) {
                    fd.append('start_month', start_month_val);
                }
                if (month_val) {
                    fd.append('month', month_val);
                }
                // Only append package_id if it's not empty (for Turnover type)
                if (pkg_id) {
                    fd.append('package_id', pkg_id);
                }

                fetch('<?= base_url('services/buyservice/') ?>', {
                        method: 'POST',
                        body: fd
                    })
                    .then(function(r) {
                        return r.text();
                    })
                    .then(function(resp) {
                        if (resp && resp.trim() !== '') {
                            window.location = resp.trim();
                        } else {
                            window.location.reload();
                        }
                    })
                    .catch(function() {
                        alert('Something went wrong. Please try again.');
                        btnElement.disabled = false;
                        btnElement.innerHTML = originalHtml;
                    });
            }

            // Confirm button for Turnover type
            if (confirmBtn) {
                confirmBtn.addEventListener('click', function() {
                    handleConfirmClick(this, false);
                });
            }

            // Confirm button for Monthly type
            if (confirmBtnMonthly) {
                confirmBtnMonthly.addEventListener('click', function() {
                    handleConfirmClick(this, true);
                });
            }
        })();
    </script>



    <?php
    /* ════════════════════════════════════════════════════════
   SECTION 2 – Create / Add Package
   ════════════════════════════════════════════════════════ */
    ?>
    <div class="pkg-section-title">
        <i class="fe fe-plus-circle"></i>
        <?= !empty($service_packages) ? 'Create Another Package' : 'Create a Service Package' ?>
    </div>

    <div class="create-panel card mb-4">
        <?php if (empty($has_account_work_package)) : ?>
            <div class="alert alert-warning mx-3 mt-3 mb-0">
                <i class="fe fe-alert-triangle me-2"></i>
                Please activate <strong>Account Work</strong> package first. After that, you can create a Service Package.
            </div>
        <?php endif; ?>

        <!-- Panel header -->
        <div class="panel-header">
            <h5><i class="fe fe-package me-2"></i>Select Services</h5>
            <p>Tick the services you want to bundle. All services in one package must share the <strong>same billing type</strong>.</p>
        </div>

        <!-- Type lock banner (shown after first tick) -->
        <div id="type-lock-banner">
            <i class="fe fe-lock"></i>
            <span>Package type locked to &nbsp;<strong id="locked-type-label">–</strong>.
                Only <strong id="locked-type-label2">–</strong> services can be added to this package.</span>
            <span class="ms-auto text-muted" style="font-size:.78rem">Uncheck all to reset.</span>
        </div>

        <?= form_open('package/savepackage', ['id' => 'pkg-form']); ?>

        <!-- Service selector table -->
        <?php if (empty($all_services)) : ?>
            <div class="empty-pkg">
                <i class="fe fe-inbox"></i>
                <p>No services available at the moment.</p>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="svc-table">
                    <thead>
                        <tr>
                            <th style="width:52px" class="text-center">Pick</th>
                            <th>Service</th>
                            <th>Billing Type</th>
                            <th>Rate</th>
                            <th>For</th>
                            <th>Auto Debit</th>
                            <th>Option</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_services as $svc) :
                            $in_pkg       = in_array((string)$svc['id'], $packaged_service_ids ?? []);
                            $is_purchased = in_array((string)$svc['id'], $purchased_service_ids ?? []);
                            $is_locked    = $in_pkg || $is_purchased;
                            $svc_types = array_map('trim', explode(',', $svc['type']));
                            $primary_type = 'Yearly';
                            if (in_array('Once',      $svc_types)) $primary_type = 'Once';
                            if (in_array('Monthly',   $svc_types)) $primary_type = 'Monthly';
                            if (in_array('Quarterly', $svc_types)) $primary_type = 'Quarterly';
                            if (in_array('Yearly',    $svc_types)) $primary_type = 'Yearly';
                            
                            // Skip services with "Once" type - don't show them in package creation
                            if ($primary_type == 'Once') {
                                continue;
                            }
                            
                            $opts     = $services_with_options[$svc['id']]['options'] ?? [];
                            $row_cls  = $is_locked ? 'row-inpkg' : '';
                            $t_meta   = pkg_type_meta($primary_type);
                        ?>
                            <tr class="svc-row <?= $row_cls ?>"
                                data-svc-id="<?= $svc['id'] ?>"
                                data-primary-type="<?= $primary_type ?>"
                                data-rate="<?= $svc['rate'] ?>">

                                <td class="text-center">
                                    <?php if ($in_pkg) : ?>
                                        <span class="pkg-type-pill"
                                            style="background:#e8f4fd;color:#1a5fa8;border:1px solid #bee3f8"
                                            title="Already in a package">
                                            <i class="fe fe-layers me-1" style="font-size:.7rem"></i>In Pkg
                                        </span>
                                    <?php elseif ($is_purchased) : ?>
                                        <span class="pkg-type-pill"
                                            style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0"
                                            title="Already purchased individually from Services page">
                                            <i class="fe fe-check-circle me-1" style="font-size:.7rem"></i>Purchased
                                        </span>
                                    <?php else : ?>
                                        <div class="svc-check-wrap">
                                            <input type="checkbox"
                                                class="svc-check svc-checkbox"
                                                name="service_id[]"
                                                value="<?= $svc['id'] ?>"
                                                data-primary-type="<?= $primary_type ?>"
                                                data-rate="<?= $svc['rate'] ?>"
                                                data-svc-id="<?= $svc['id'] ?>"
                                                <?= empty($has_account_work_package) ? 'disabled' : '' ?>>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="fw-semibold <?= $is_locked ? 'text-muted' : 'text-dark' ?>" style="font-size:.875rem">
                                        <?= htmlspecialchars($svc['name']) ?>
                                        <?php if ($is_purchased) : ?>
                                            <span class="ms-1 text-muted" style="font-size:.75rem;font-weight:400"
                                                title="Purchased individually. Go to Services page to manage.">
                                                (individually purchased)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td><?= pkg_type_badge_inline($primary_type) ?></td>

                                <td class="rate-cell">
                                    <span class="svc-display-rate">₹<?= number_format($svc['rate'], 2) ?></span>
                                </td>

                                <td>
                                    <span class="badge bg-light text-secondary border" style="font-size:.75rem">
                                        <?= htmlspecialchars($svc['service_for']) ?>
                                    </span>
                                </td>

                                <td style="font-size:.82rem;color:#555">
                                    <?php if (!empty($svc['debit_date'])) : ?>
                                        <i class="fe fe-calendar me-1" style="color:#aaa"></i>
                                        <?= date('d M Y', strtotime($svc['debit_date'])) ?>
                                    <?php else : ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if (!empty($opts) && !$in_pkg) : ?>
                                        <select name="service_option[<?= $svc['id'] ?>]"
                                            class="opt-select svc-option-select"
                                            data-svc-id="<?= $svc['id'] ?>"
                                            disabled>
                                            <option value="">Select option…</option>
                                            <?php foreach ($opts as $opt) : ?>
                                                <option value="<?= $opt['id'] ?>" data-rate="<?= $opt['rate'] ?>">
                                                    <?= htmlspecialchars($opt['display_name']) ?>
                                                    – ₹<?= number_format($opt['rate'], 2) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif (!$in_pkg) : ?>
                                        <span class="text-muted" style="font-size:.78rem">—</span>
                                    <?php else : ?>
                                        <span class="text-muted" style="font-size:.78rem">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ── Summary / save bar (visible after first service is ticked) ── -->
            <div id="pkg-sticky-bar">
                <div class="bar-left">
                    <span class="bar-total">₹<span id="bar-total-amt">0.00</span></span>
                    <span class="bar-count" id="bar-count-txt">0 services selected</span>
                    <span class="bar-type d-none" id="bar-type-label"></span>
                    <span class="text-white-50" style="font-size:.75rem" id="bar-gst-note"></span>
                </div>
                <button type="submit" form="pkg-form" name="savepackage" class="btn btn-success px-4 fw-semibold" id="save-pkg-btn" <?= empty($has_account_work_package) ? 'disabled' : '' ?>>
                    <i class="fe fe-save me-2"></i>Save Package
                </button>
            </div>

        <?php endif; ?>

        <?= form_close(); ?>
    </div>

    <!-- ── Scripts ── -->
    <script>
        (function($) {
            'use strict';

            var lockedType = null;

            /* ── Recalculate total ─────────────────────────── */
            function recalcTotal() {
                var total = 0;
                $('.svc-checkbox:checked').each(function() {
                    var row = $(this).closest('tr');
                    var optSel = row.find('.svc-option-select');
                    if (optSel.length && optSel.val()) {
                        total += parseFloat(optSel.find('option:selected').data('rate')) || 0;
                    } else {
                        total += parseFloat($(this).data('rate')) || 0;
                    }
                });
                var fmt = total.toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                $('#bar-total-amt').text(fmt);
                return total;
            }

            /* ── Refresh full UI ───────────────────────────── */
            function refreshUI() {
                var checked = $('.svc-checkbox:checked');
                var count = checked.length;

                if (count === 0) {
                    lockedType = null;
                    $('#type-lock-banner').hide();
                    $('.svc-row').removeClass('row-selected row-disabled');
                    $('.svc-checkbox').prop('disabled', false);
                    $('#pkg-sticky-bar').hide();
                    $('#bar-type-label').addClass('d-none').text('');
                } else {
                    $('#type-lock-banner').css('display', 'flex');
                    $('#locked-type-label, #locked-type-label2').text(lockedType);
                    $('#bar-type-label').removeClass('d-none').text(lockedType + ' Package');
                    $('#pkg-sticky-bar').css('display', 'flex');
                    $('#bar-count-txt').text(count + ' service' + (count > 1 ? 's' : '') + ' selected');
                    $('#bar-gst-note').text('(+ 18% GST if applicable)');
                }

                /* dim non-matching rows */
                $('.svc-row:not(.row-inpkg)').each(function() {
                    var rowType = $(this).data('primary-type');
                    if (lockedType && rowType !== lockedType) {
                        $(this).addClass('row-disabled').removeClass('row-selected');
                        $(this).find('.svc-checkbox').prop('disabled', true).prop('checked', false);
                    } else {
                        $(this).removeClass('row-disabled');
                        $(this).find('.svc-checkbox').prop('disabled', false);
                    }
                });

                recalcTotal();
            }

            /* ── Checkbox change ───────────────────────────── */
            $('body').on('change', '.svc-checkbox', function() {
                var row = $(this).closest('tr');
                var rowType = $(this).data('primary-type');

                if ($(this).is(':checked')) {
                    if (!lockedType) lockedType = rowType;
                    row.addClass('row-selected');
                    row.find('.svc-option-select').prop('disabled', false);
                } else {
                    row.removeClass('row-selected');
                    row.find('.svc-option-select').prop('disabled', true).val('');
                    if ($('.svc-checkbox:checked').length === 0) lockedType = null;
                }
                refreshUI();
            });

            /* ── Option select change ──────────────────────── */
            $('body').on('change', '.svc-option-select', function() {
                var row = $(this).closest('tr');
                var rate = parseFloat($(this).find('option:selected').data('rate')) || 0;
                if ($(this).val()) {
                    row.find('.svc-display-rate').text('₹' + rate.toLocaleString('en-IN', {
                        minimumFractionDigits: 2
                    }));
                } else {
                    var base = parseFloat(row.data('rate')) || 0;
                    row.find('.svc-display-rate').text('₹' + base.toLocaleString('en-IN', {
                        minimumFractionDigits: 2
                    }));
                }
                recalcTotal();
            });

            /* ── Intercept Package Delete Form ──────────────────────── */
            $('body').on('submit', '.delete-pkg-form', function(e) {
                var form = this;
                if ($(form).data('confirmed')) {
                    return true;
                }
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Delete Package?',
                        text: 'Are you sure you want to delete this package? This action cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '<i class="fe fe-trash-2 me-1"></i> Yes, Delete',
                        cancelButtonText: 'Cancel',
                        customClass: {
                            popup: 'rounded-4 shadow-lg border-0',
                            confirmButton: 'btn btn-danger px-4 rounded-pill me-2',
                            cancelButton: 'btn btn-light px-4 rounded-pill border'
                        },
                        buttonsStyling: false
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            $(form).data('confirmed', true);
                            form.submit();
                        }
                    });
                } else {
                    if (confirm('Are you sure you want to delete this package?')) {
                        $(form).data('confirmed', true);
                        form.submit();
                    }
                }
            });

            /* ── Account Work Renewal button AJAX ──────────────────────── */
            $('body').on('click', '.renew-acct-work-btn', function() {
                var btn = $(this);
                var pkgId = btn.data('pkg-id');
                var amount = parseFloat(btn.data('amount')) || 0;
                var amtFmt = amount.toLocaleString('en-IN', {
                    minimumFractionDigits: 2
                });

                function executeAcctRenewal() {
                    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Processing…');

                    $.ajax({
                        type: 'POST',
                        url: '<?= base_url('services/renewpackage') ?>',
                        data: { package_id: pkgId },
                        dataType: 'json',
                        success: function(r) {
                            if (r.status) {
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire({
                                        title: 'Renewal Successful!',
                                        text: r.message || 'Renewal successful!',
                                        icon: 'success',
                                        confirmButtonText: 'OK',
                                        customClass: { confirmButton: 'btn btn-success px-4 rounded-pill' },
                                        buttonsStyling: false
                                    }).then(function() {
                                        location.reload();
                                    });
                                } else {
                                    if (typeof alertify !== 'undefined') alertify.success(r.message || 'Renewal successful!');
                                    setTimeout(function() { location.reload(); }, 1500);
                                }
                            } else {
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire({
                                        title: 'Renewal Failed',
                                        text: r.message || 'Renewal failed.',
                                        icon: 'error',
                                        confirmButtonText: 'OK',
                                        customClass: { confirmButton: 'btn btn-danger px-4 rounded-pill' },
                                        buttonsStyling: false
                                    }).then(function() {
                                        if (r.redirect) location.href = r.redirect;
                                        else btn.prop('disabled', false).html('<i class="fe fe-credit-card me-1"></i>Renew & Pay');
                                    });
                                } else {
                                    if (typeof alertify !== 'undefined') alertify.error(r.message || 'Renewal failed.');
                                    if (r.redirect) setTimeout(function() { location.href = r.redirect; }, 2000);
                                    btn.prop('disabled', false).html('<i class="fe fe-credit-card me-1"></i>Renew & Pay');
                                }
                            }
                        },
                        error: function() {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({ title: 'Error', text: 'An error occurred. Please try again.', icon: 'error', confirmButtonText: 'OK' });
                            } else if (typeof alertify !== 'undefined') {
                                alertify.error('An error occurred. Please try again.');
                            }
                            btn.prop('disabled', false).html('<i class="fe fe-credit-card me-1"></i>Renew & Pay');
                        }
                    });
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Confirm Renewal',
                        html: '<div class="text-center py-2"><p class="text-secondary mb-2">Renew Account Work package</p><h3 class="fw-bold text-danger mb-2">₹' + amtFmt + '</h3><p class="text-muted small mb-0">Amount will be deducted from your wallet balance.</p></div>',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: '<i class="fe fe-check-circle me-1"></i> Renew & Pay Now',
                        cancelButtonText: 'Cancel',
                        customClass: {
                            popup: 'rounded-4 shadow-lg border-0',
                            confirmButton: 'btn btn-danger btn-lg px-4 rounded-pill me-2',
                            cancelButton: 'btn btn-light btn-lg px-4 rounded-pill border'
                        },
                        buttonsStyling: false
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            executeAcctRenewal();
                        }
                    });
                } else {
                    if (confirm('Renew Account Work package for ₹' + amtFmt + '?\n\nThis amount will be deducted from your wallet.')) {
                        executeAcctRenewal();
                    }
                }
            });

            /* ── Pay Bill button AJAX ──────────────────────── */
            $('body').on('click', '.pay-bill-btn', function() {
                var $btn = $(this);
                var pkgId = $btn.data('pkg-id');
                var amount = parseFloat($btn.data('amount')) || 0;
                var amtFmt = amount.toLocaleString('en-IN', {
                    minimumFractionDigits: 2
                });

                function executeBillPay() {
                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Processing…');

                    $.ajax({
                        type: 'POST',
                        url: '<?= base_url('package/paybill') ?>',
                        data: { package_id: pkgId },
                        dataType: 'json',
                        success: function(r) {
                            if (r.status) {
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire({
                                        title: 'Payment Successful!',
                                        text: r.message || 'Payment successful!',
                                        icon: 'success',
                                        confirmButtonText: 'OK',
                                        customClass: { confirmButton: 'btn btn-success px-4 rounded-pill' },
                                        buttonsStyling: false
                                    }).then(function() {
                                        location.reload();
                                    });
                                } else {
                                    if (typeof alertify !== 'undefined') alertify.success(r.message || 'Payment successful!');
                                    setTimeout(function() { location.reload(); }, 1500);
                                }
                            } else {
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire({
                                        title: 'Payment Failed',
                                        text: r.message || 'Payment failed.',
                                        icon: 'error',
                                        confirmButtonText: 'OK',
                                        customClass: { confirmButton: 'btn btn-danger px-4 rounded-pill' },
                                        buttonsStyling: false
                                    }).then(function() {
                                        if (r.redirect) location.href = r.redirect;
                                        else $btn.prop('disabled', false).html('<i class="fe fe-credit-card me-1"></i>Pay Now');
                                    });
                                } else {
                                    if (typeof alertify !== 'undefined') alertify.error(r.message || 'Payment failed.');
                                    if (r.redirect) setTimeout(function() { location.href = r.redirect; }, 2000);
                                    $btn.prop('disabled', false).html('<i class="fe fe-credit-card me-1"></i>Pay Now');
                                }
                            }
                        },
                        error: function() {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({ title: 'Error', text: 'An error occurred. Please try again.', icon: 'error', confirmButtonText: 'OK' });
                            } else if (typeof alertify !== 'undefined') {
                                alertify.error('An error occurred. Please try again.');
                            }
                            $btn.prop('disabled', false).html('<i class="fe fe-credit-card me-1"></i>Pay Now');
                        }
                    });
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Confirm Payment',
                        html: '<div class="text-center py-2"><p class="text-secondary mb-2">Pay package bill</p><h3 class="fw-bold text-danger mb-2">₹' + amtFmt + '</h3><p class="text-muted small mb-0">Amount will be deducted from your wallet balance.</p></div>',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: '<i class="fe fe-check-circle me-1"></i> Pay Now',
                        cancelButtonText: 'Cancel',
                        customClass: {
                            popup: 'rounded-4 shadow-lg border-0',
                            confirmButton: 'btn btn-danger btn-lg px-4 rounded-pill me-2',
                            cancelButton: 'btn btn-light btn-lg px-4 rounded-pill border'
                        },
                        buttonsStyling: false
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            executeBillPay();
                        }
                    });
                } else {
                    if (confirm('Pay package bill of ₹' + amtFmt + '?\n\nThis amount will be deducted from your wallet.')) {
                        executeBillPay();
                    }
                }
            });


        }(jQuery));
    </script>
</div><!-- /.pkg-page -->