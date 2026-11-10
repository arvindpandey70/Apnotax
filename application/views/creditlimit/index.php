<style>
/* Executive Financial Ledger UI Styling */
.credit-banner-card {
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 60%, #1d4ed8 100%);
    border-radius: 14px;
    box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.35);
    border: none;
    color: #ffffff;
}
.credit-banner-card .title-text {
    font-size: 24px;
    font-weight: 800;
    letter-spacing: 0.6px;
    color: #ffffff;
    text-shadow: 0 2px 4px rgba(0,0,0,0.15);
}
.kpi-tile {
    border-radius: 10px;
    background: #ffffff;
    border: 2px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.06);
    transition: all 0.25s ease;
    padding: 10px 8px;
}
.kpi-tile:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px -2px rgba(0,0,0,0.1);
    border-color: #3b82f6;
}
.kpi-tile .kpi-label {
    font-size: 10px;
    text-transform: uppercase;
    font-weight: 800;
    letter-spacing: 0.5px;
    color: #64748b;
}
.kpi-tile .kpi-value {
    font-size: 15px;
    font-weight: 800;
    line-height: 1.2;
    margin-top: 4px;
}
.statement-card {
    border-radius: 12px;
    border: 2px solid #cbd5e1;
    box-shadow: 0 6px 18px -3px rgba(0,0,0,0.07);
    overflow: hidden;
    background: #ffffff;
}
.statement-card .card-header {
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 2px solid #cbd5e1;
    padding: 12px 16px;
}
.statement-title {
    font-size: 15px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: 0.2px;
}
.ledger-table {
    width: 100%;
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0;
}
.ledger-table thead th {
    background: #0f172a !important;
    color: #ffffff !important;
    font-size: 11.5px !important;
    font-weight: 800 !important;
    letter-spacing: 0.3px;
    padding: 8px 6px !important;
    border: none !important;
    border-right: 1px solid #334155 !important;
    text-align: right;
    vertical-align: middle;
    text-transform: uppercase;
}
.ledger-table thead th:first-child {
    text-align: center;
}
.ledger-table thead th:last-child {
    border-right: none !important;
}
.ledger-table tbody td {
    padding: 7px 6px !important;
    font-size: 11.5px !important;
    font-weight: 700;
    color: #1e293b;
    border-top: 1px solid #e2e8f0 !important;
    border-right: 1px solid #e2e8f0 !important;
    border-bottom: none !important;
    vertical-align: middle;
}
.ledger-table tbody td:last-child {
    border-right: none !important;
}
.ledger-table tbody tr:hover {
    background-color: #f8fafc !important;
}
.ledger-table tfoot th {
    background: #1e293b !important;
    color: #ffffff !important;
    font-size: 11.5px !important;
    font-weight: 800 !important;
    padding: 9px 6px !important;
    border-top: 2px solid #0f172a !important;
    vertical-align: middle;
}
.row-active-activity {
    background-color: #f0fdf4 !important;
}
/* ========================================================
   MODAL & POP-UP THEME OVERRIDES (CRISP & HIGH-CONTRAST)
   ======================================================== */
#monthViewModal .modal-content {
    background-color: #ffffff !important;
    color: #0f172a !important;
    border-radius: 14px !important;
    border: none !important;
    box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.35) !important;
    overflow: hidden !important;
}

#monthViewModal .modal-header {
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%) !important;
    color: #ffffff !important;
    padding: 14px 20px !important;
    border-bottom: 3px solid #1d4ed8 !important;
}

#monthViewModal .modal-title {
    color: #ffffff !important;
    font-size: 18px !important;
    font-weight: 800 !important;
    letter-spacing: 0.4px !important;
}

#monthViewModal .btn-close {
    filter: brightness(0) invert(1) !important;
    opacity: 0.9 !important;
}
#monthViewModal .btn-close:hover {
    opacity: 1 !important;
}

#monthViewModal .modal-body {
    background-color: #f8fafc !important;
    color: #0f172a !important;
    padding: 18px !important;
}

/* Modal Ribbon Summary Card */
.modal-ribbon-card {
    background: #ffffff !important;
    border: 2px solid #e2e8f0 !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 10px rgba(0,0,0,0.04) !important;
}
.modal-ribbon-card .ribbon-box {
    padding: 8px 6px;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}
.modal-ribbon-card .ribbon-lbl {
    font-size: 10px !important;
    font-weight: 800 !important;
    text-transform: uppercase !important;
    color: #475569 !important;
    letter-spacing: 0.4px;
}
.modal-ribbon-card .ribbon-val {
    font-size: 13.5px !important;
    font-weight: 800 !important;
    margin-top: 2px;
}

/* Modal Navigation Tabs (Never invisible, high contrast) */
#monthModalTabs {
    border-bottom: 2px solid #cbd5e1 !important;
    gap: 8px !important;
}
#monthModalTabs .nav-item {
    margin-bottom: -2px !important;
}
#monthModalTabs .nav-link {
    background-color: #e2e8f0 !important;
    color: #1e293b !important; /* ALWAYS CRISP & VISIBLE */
    font-size: 12px !important;
    font-weight: 800 !important;
    border: 2px solid #cbd5e1 !important;
    border-bottom: none !important;
    border-radius: 8px 8px 0 0 !important;
    padding: 7px 14px !important;
    transition: all 0.2s ease !important;
    opacity: 1 !important;
}
#monthModalTabs .nav-link:hover {
    background-color: #cbd5e1 !important;
    color: #0f172a !important;
}
#monthModalTabs .nav-link.active {
    background-color: #2563eb !important;
    color: #ffffff !important;
    border-color: #2563eb #2563eb #ffffff !important;
    box-shadow: 0 -3px 10px rgba(37,99,235,0.2) !important;
}
#monthModalTabs .nav-link.active i {
    color: #ffffff !important;
}

/* Modal Tables - Anti-Invisibility Rules */
#monthViewModal table {
    background-color: #ffffff !important;
    border-collapse: separate !important;
    border-spacing: 0 !important;
    width: 100% !important;
}

#monthViewModal thead th {
    background-color: #0f172a !important;
    color: #ffffff !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    padding: 7px 5px !important;
    border: 1px solid #334155 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.3px !important;
}

#monthViewModal tbody td {
    background-color: #ffffff !important;
    color: #0f172a !important; /* 100% VISIBLE WITHOUT HOVER */
    font-size: 11px !important;
    font-weight: 700 !important;
    padding: 6px 5px !important;
    border: 1px solid #e2e8f0 !important;
    vertical-align: middle !important;
    opacity: 1 !important;
}

/* Alternating Rows */
#monthViewModal tbody tr:nth-child(even) td {
    background-color: #f8fafc !important;
}

/* Active Highlight Row */
#monthViewModal tbody tr.table-warning td {
    background-color: #fef9c3 !important;
    color: #854d0e !important;
    font-weight: 800 !important;
}

/* Today Row */
#monthViewModal tbody tr.table-primary td {
    background-color: #dbeafe !important;
    color: #1e3a8a !important;
    font-weight: 800 !important;
}

/* Row Hover - Smooth Highlight */
#monthViewModal tbody tr:hover td {
    background-color: #e0f2fe !important;
    color: #0f172a !important;
}

/* Text Colors Guaranteed Visible */
#monthViewModal .text-danger {
    color: #dc2626 !important;
}
#monthViewModal .text-success {
    color: #059669 !important;
}
#monthViewModal .text-warning {
    color: #d97706 !important;
}
#monthViewModal .text-info {
    color: #0284c7 !important;
}
#monthViewModal .text-muted {
    color: #64748b !important;
}
#monthViewModal .dash-cell {
    color: #94a3b8 !important;
    font-weight: 700 !important;
}

/* Thick & Ergonomic Horizontal Scrollbars on BOTH tables */
.custom-scroll-container,
.table-responsive {
    overflow-x: auto !important;
    scrollbar-width: auto !important;
    scrollbar-color: #2563eb #cbd5e1 !important;
}

.custom-scroll-container::-webkit-scrollbar,
.table-responsive::-webkit-scrollbar {
    height: 18px !important;
    width: 18px !important;
}

.custom-scroll-container::-webkit-scrollbar-track,
.table-responsive::-webkit-scrollbar-track {
    background: #e2e8f0 !important;
    border: 2px solid #cbd5e1 !important;
    border-radius: 9px !important;
}

.custom-scroll-container::-webkit-scrollbar-thumb,
.table-responsive::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, #3b82f6 0%, #1d4ed8 100%) !important;
    border-radius: 9px !important;
    border: 3px solid #e2e8f0 !important;
    box-shadow: 0 1px 4px rgba(0,0,0,0.2) inset !important;
    cursor: grab;
}

.custom-scroll-container::-webkit-scrollbar-thumb:hover,
.table-responsive::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(180deg, #2563eb 0%, #1e40af 100%) !important;
}

.custom-scroll-container::-webkit-scrollbar-thumb:active,
.table-responsive::-webkit-scrollbar-thumb:active {
    background: #1e3a8a !important;
    cursor: grabbing;
}
</style>

<!-- Top Banner Card -->
<div class="card credit-banner-card mb-4">
    <div class="card-body p-4">
        <div class="row align-items-center g-3">
            <div class="col-lg-6">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-white bg-opacity-20 rounded-circle text-white shadow-sm">
                        <i class="fa fa-university fs-26"></i>
                    </div>
                    <div>
                        <span class="badge bg-warning text-dark px-3 py-1 fw-bolder mb-1" style="font-size: 11px; letter-spacing: 0.5px;">
                            <i class="fa fa-shield me-1"></i> CREDIT LIMIT & PANEL INTEREST LEDGER
                        </span>
                        <h2 class="title-text mb-0">MONTH-WISE CREDIT STATEMENT</h2>
                        <div class="text-white text-opacity-90 fw-semibold small mt-1">
                            Live continuous carry-forward ledger with purchase-wise simple panel interest
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="d-flex flex-wrap justify-content-lg-end align-items-center gap-2">
                    <div class="px-3 py-2 bg-white bg-opacity-10 rounded-3 border border-white border-opacity-25 text-center">
                        <div class="small text-uppercase fw-bold text-white-50" style="font-size: 10px;">CREDIT LIMIT</div>
                        <div class="fw-bolder fs-17 text-white">₹ <?= number_format($ledger['credit_limit'], 2) ?></div>
                    </div>
                    <div class="px-3 py-2 bg-white bg-opacity-10 rounded-3 border border-white border-opacity-25 text-center">
                        <div class="small text-uppercase fw-bold text-white-50" style="font-size: 10px;">PANEL INTEREST</div>
                        <div class="fw-bolder fs-17 text-warning"><?= number_format($ledger['interest_rate'], 2) ?>% / 30 Days</div>
                    </div>
                    <div class="px-3 py-2 bg-white bg-opacity-10 rounded-3 border border-white border-opacity-25 text-center">
                        <div class="small text-uppercase fw-bold text-white-50" style="font-size: 10px;">FINANCIAL YEAR</div>
                        <div class="fw-bolder fs-17 text-white">FY <?= htmlspecialchars($ledger['fy_label']) ?></div>
                    </div>
                    <a href="<?= base_url('creditlimit/export_csv?fy=' . $fy) ?>" class="btn btn-light fw-bolder text-primary px-3 py-2 shadow-sm">
                        <i class="fa fa-file-excel-o me-1 text-success"></i> Export CSV
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 6 KPI Summary Tiles -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-4 col-xl-2">
        <div class="kpi-tile text-center" style="border-top: 4px solid #2563eb;">
            <div class="kpi-label"><i class="fa fa-credit-card me-1 text-primary"></i> Total Limit</div>
            <div class="kpi-value text-primary">₹ <?= number_format($ledger['credit_limit'], 2) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4 col-xl-2">
        <div class="kpi-tile text-center" style="border-top: 4px solid #ea580c;">
            <div class="kpi-label"><i class="fa fa-shopping-bag me-1 text-warning"></i> Used Credit</div>
            <div class="kpi-value text-warning">₹ <?= number_format($ledger['used_credit'], 2) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4 col-xl-2">
        <div class="kpi-tile text-center" style="border-top: 4px solid #059669;">
            <div class="kpi-label"><i class="fa fa-check-circle me-1 text-success"></i> Available Limit</div>
            <div class="kpi-value text-success">₹ <?= number_format($ledger['available_credit'], 2) ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4 col-xl-2">
        <div class="kpi-tile text-center" style="border-top: 4px solid #6366f1;">
            <div class="kpi-label"><i class="fa fa-hourglass-half me-1" style="color:#6366f1;"></i> Principal Due</div>
            <div class="kpi-value <?= ($ledger['outstanding_principal'] > 0) ? 'text-danger' : 'text-dark' ?>">
                <?= ($ledger['outstanding_principal'] > 0) ? '- ₹ ' . number_format($ledger['outstanding_principal'], 2) : '₹ 0.00' ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4 col-xl-2">
        <div class="kpi-tile text-center" style="border-top: 4px solid #d97706;">
            <div class="kpi-label"><i class="fa fa-bolt me-1 text-warning"></i> Accrued Interest</div>
            <div class="kpi-value text-warning">
                <?= ($ledger['accrued_interest'] > 0) ? '- ₹ ' . number_format($ledger['accrued_interest'], 2) : '₹ 0.00' ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4 col-xl-2">
        <div class="kpi-tile text-center" style="border-top: 4px solid <?= ($ledger['total_outstanding'] > 0) ? '#dc2626' : '#16a34a' ?>;">
            <div class="kpi-label"><i class="fa fa-balance-scale me-1"></i> Total Outstanding</div>
            <div class="kpi-value <?= ($ledger['total_outstanding'] > 0) ? 'text-danger' : 'text-success' ?>">
                <?= ($ledger['total_outstanding'] > 0) ? '- ₹ ' . number_format($ledger['total_outstanding'], 2) : '₹ 0.00' ?>
            </div>
        </div>
    </div>
</div>

<!-- Month-Wise Credit Ledger Table Card -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card statement-card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary p-2 text-white rounded-circle">
                        <i class="fa fa-calendar-check-o fs-16"></i>
                    </span>
                    <div>
                        <h4 class="statement-title mb-0">MONTH-WISE CREDIT LEDGER (APRIL TO MARCH)</h4>
                        <span class="text-muted small fw-bold">Continuous carry-forward from April to March with purchase-wise panel interest</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <form method="get" action="<?= base_url('creditlimit') ?>" class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0 fw-bold text-dark small">Select FY:</label>
                        <select name="fy" class="form-select form-select-sm fw-bold border-primary" onchange="this.form.submit()" style="min-width: 150px; font-size: 13.5px;">
                            <option value="2025-2026" <?= ($fy == '2025-2026') ? 'selected' : '' ?>>FY 2025 - 2026</option>
                            <option value="2026-2027" <?= ($fy == '2026-2027') ? 'selected' : '' ?>>FY 2026 - 2027</option>
                            <option value="2027-2028" <?= ($fy == '2027-2028') ? 'selected' : '' ?>>FY 2027 - 2028</option>
                        </select>
                    </form>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive custom-scroll-container pb-2">
                    <table class="table ledger-table text-nowrap align-middle" style="min-width: 1040px;" id="credit-ledger-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 110px;">Month</th>
                                <th class="text-end">Opening Outstanding</th>
                                <th class="text-end">Package / Service Purchase</th>
                                <th class="text-end">Receipt</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Payment / Wallet Recovery</th>
                                <th class="text-end">Balance</th>
                                <th class="text-end">Panel Interest / Late Fee</th>
                                <th class="text-end" style="width: 135px;">Closing Outstanding</th>
                                <th class="text-center" style="width: 75px;">View</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($ledger['rows'])): ?>
                                <?php foreach ($ledger['rows'] as $r): ?>
                                    <?php 
                                        $has_activity = ($r['purchase_amount'] > 0 || $r['receipt_amount'] > 0 || $r['wallet_recovery'] > 0 || $r['panel_interest'] > 0);
                                    ?>
                                    <tr class="<?= $has_activity ? 'row-active-activity' : '' ?>">
                                        <td class="text-center fw-bold text-dark" style="font-size: 12px;">
                                            <?= htmlspecialchars($r['month_label']) ?>
                                            <?php if ($r['is_current']): ?>
                                                <span class="badge bg-primary ms-1" style="font-size:9px;">Current</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end <?= ($r['opening_outstanding'] > 0) ? 'text-danger fw-bold' : '' ?>" style="color: #334155;">
                                            <?= ($r['opening_outstanding'] > 0) ? '- ₹ ' . number_format($r['opening_outstanding'], 2) : '₹ 0.00' ?>
                                        </td>
                                        <td class="text-end <?= ($r['purchase_amount'] > 0) ? 'text-danger fw-bolder' : 'text-muted' ?>">
                                            <?= ($r['purchase_amount'] > 0) ? '₹ ' . number_format($r['purchase_amount'], 2) : '-' ?>
                                        </td>
                                        <td class="text-end <?= ($r['receipt_amount'] > 0) ? 'text-info fw-bolder' : 'text-muted' ?>">
                                            <?= ($r['receipt_amount'] > 0) ? '₹ ' . number_format($r['receipt_amount'], 2) : '-' ?>
                                        </td>
                                        <td class="text-end fw-bold <?= ($r['total'] > 0) ? 'text-danger' : '' ?>" style="color: #1e293b;">
                                            <?= ($r['total'] > 0) ? '- ₹ ' . number_format($r['total'], 2) : '₹ 0.00' ?>
                                        </td>
                                        <td class="text-end <?= ($r['wallet_recovery'] > 0) ? 'text-success fw-bolder' : 'text-muted' ?>">
                                            <?= ($r['wallet_recovery'] > 0) ? '+ ₹ ' . number_format($r['wallet_recovery'], 2) : '-' ?>
                                        </td>
                                        <td class="text-end <?= ($r['balance'] > 0) ? 'text-danger fw-bolder' : 'text-muted' ?>">
                                            <?= ($r['balance'] > 0) ? '- ₹ ' . number_format($r['balance'], 2) : '-' ?>
                                        </td>
                                        <td class="text-end <?= ($r['panel_interest'] > 0) ? 'text-warning fw-bold' : 'text-muted' ?>">
                                            <?= ($r['panel_interest'] > 0) ? '- ₹ ' . number_format($r['panel_interest'], 2) : '-' ?>
                                        </td>
                                        <td class="text-end <?= ($r['closing_outstanding'] > 0) ? 'text-danger fw-bolder' : 'text-dark' ?>" style="font-size: 12px;">
                                            <?= ($r['closing_outstanding'] > 0) ? '- ₹ ' . number_format($r['closing_outstanding'], 2) : '₹ 0.00' ?>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold btn-view-month py-1 px-2" style="font-size: 11px;"
                                                    data-ym="<?= $r['ym'] ?>" data-label="<?= htmlspecialchars($r['month_label']) ?>">
                                                <i class="fa fa-eye me-1"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted fw-bold">No ledger records available for this financial year.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="text-center">TOTAL</th>
                                <th class="text-end">-</th>
                                <th class="text-end text-danger">₹ <?= number_format($ledger['summary']['total_purchases'], 2) ?></th>
                                <th class="text-end text-info">₹ <?= number_format($ledger['summary']['total_receipts'], 2) ?></th>
                                <th class="text-end">-</th>
                                <th class="text-end text-success">₹ <?= number_format($ledger['summary']['total_recoveries'], 2) ?></th>
                                <th class="text-end">-</th>
                                <th class="text-end text-warning">₹ <?= number_format($ledger['summary']['total_interest'], 2) ?></th>
                                <th class="text-end text-danger">₹ <?= number_format($ledger['summary']['closing_outstanding'], 2) ?></th>
                                <th class="text-center">-</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Month View Modal (Day-wise Breakdown & Purchases) -->
<div class="modal fade" id="monthViewModal" tabindex="-1" aria-labelledby="monthViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header d-flex justify-content-between align-items-center">
                <h5 class="modal-title mb-0 d-flex align-items-center" id="monthViewModalLabel">
                    <i class="fa fa-calendar-check-o me-2 text-warning fs-18"></i>
                    <span>STATEMENT BREAKDOWN :</span>
                    <span id="modalMonthTitle" class="badge bg-warning text-dark fw-bolder fs-13 ms-2 px-3 py-1">Month</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Loading Spinner -->
                <div id="modalLoadingSpinner" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="fw-bold mt-2 text-muted">Loading day-wise ledger statement...</div>
                </div>

                <!-- Error Alert -->
                <div id="modalEmptyMessage" class="alert alert-danger d-none text-center fw-bold">
                    <i class="fa fa-exclamation-circle me-1"></i> Failed to load statement.
                </div>

                <!-- Modal Content Wrapper -->
                <div id="modalContentWrap" class="d-none">
                    <!-- Month Summary Strip -->
                    <div class="modal-ribbon-card p-3 mb-3">
                        <div class="row g-2 text-center align-items-center">
                            <div class="col-sm-2 col-4">
                                <div class="ribbon-box" style="border-top: 3px solid #dc2626;">
                                    <div class="ribbon-lbl">Opening</div>
                                    <div class="ribbon-val text-danger" id="ribbonOpening">₹ 0.00</div>
                                </div>
                            </div>
                            <div class="col-sm-2 col-4">
                                <div class="ribbon-box" style="border-top: 3px solid #ea580c;">
                                    <div class="ribbon-lbl">Purchases</div>
                                    <div class="ribbon-val text-danger" id="ribbonPurchases">₹ 0.00</div>
                                </div>
                            </div>
                            <div class="col-sm-2 col-4">
                                <div class="ribbon-box" style="border-top: 3px solid #059669;">
                                    <div class="ribbon-lbl">Recoveries</div>
                                    <div class="ribbon-val text-success" id="ribbonRecoveries">₹ 0.00</div>
                                </div>
                            </div>
                            <div class="col-sm-2 col-4">
                                <div class="ribbon-box" style="border-top: 3px solid #dc2626;">
                                    <div class="ribbon-lbl">Balance</div>
                                    <div class="ribbon-val text-danger" id="ribbonBalance">₹ 0.00</div>
                                </div>
                            </div>
                            <div class="col-sm-2 col-4">
                                <div class="ribbon-box" style="border-top: 3px solid #d97706;">
                                    <div class="ribbon-lbl">Panel Interest</div>
                                    <div class="ribbon-val text-warning" id="ribbonInterest">₹ 0.00</div>
                                </div>
                            </div>
                            <div class="col-sm-2 col-4">
                                <div class="ribbon-box" style="border-top: 3px solid #b91c1c;">
                                    <div class="ribbon-lbl">Closing Total</div>
                                    <div class="ribbon-val text-danger fs-15" id="ribbonClosing">₹ 0.00</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Tabs -->
                    <ul class="nav nav-tabs nav-fill mb-3 fw-bold" id="monthModalTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-2" id="daywise-tab" data-bs-toggle="tab" data-bs-target="#daywise-pane" type="button" role="tab" aria-selected="true">
                                <i class="fa fa-calendar-check-o me-1 text-primary"></i> Day-wise Statement (All Days)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2" id="purchases-tab" data-bs-toggle="tab" data-bs-target="#purchases-pane" type="button" role="tab" aria-selected="false">
                                <i class="fa fa-shopping-bag me-1 text-success"></i> Purchases & Services <span class="badge bg-secondary ms-1" id="purchaseCountBadge">0</span>
                            </button>
                        </li>
                    </ul>

                    <!-- Tabs Content -->
                    <div class="tab-content" id="monthModalTabsContent">
                        <!-- Pane 1: Day-wise Ledger Table -->
                        <div class="tab-pane fade show active" id="daywise-pane" role="tabpanel">
                            <div class="table-responsive custom-scroll-container pb-2" style="max-height: 500px; overflow-x: auto !important; overflow-y: auto !important;">
                                <table class="table table-bordered table-striped table-hover align-middle mb-0 text-center text-nowrap" style="min-width: 980px; font-size: 11px;">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th style="width: 110px;">Date</th>
                                            <th class="text-end">Opening Outstanding</th>
                                            <th class="text-end">Payment Against Services</th>
                                            <th class="text-end">Total</th>
                                            <th class="text-end">Receipt from Wallet Payment</th>
                                            <th class="text-end">Balance</th>
                                            <th class="text-end">Panel Interest</th>
                                            <th class="text-end">Closing Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="daywiseTableBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pane 2: Purchases Breakdown Table -->
                        <div class="tab-pane fade" id="purchases-pane" role="tabpanel">
                            <div class="table-responsive custom-scroll-container pb-2" style="max-height: 500px; overflow-x: auto !important; overflow-y: auto !important;">
                                <table class="table table-bordered table-hover align-middle mb-0 text-nowrap" style="min-width: 1100px; font-size: 11px;">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th>Purchase Date</th>
                                            <th>Package / Service Name</th>
                                            <th class="text-end">Amount</th>
                                            <th class="text-end">Wallet Paid</th>
                                            <th class="text-end">Credit Used</th>
                                            <th>Start Date</th>
                                            <th class="text-center">Unpaid Days</th>
                                            <th class="text-center">Rate</th>
                                            <th class="text-end">Month Interest</th>
                                            <th class="text-end">Total Interest</th>
                                            <th>Recovery Date</th>
                                            <th class="text-end">Recovered</th>
                                            <th class="text-end">Remaining</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="purchasesTableBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                <div class="small text-muted">
                    <i class="fa fa-info-circle text-primary me-1"></i> Continuous daily calculation based on eligible active unpaid principal & dynamic interest rate.
                </div>
                <button type="button" class="btn btn-sm btn-secondary fw-bold px-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('monthViewModal');
    const modal = new bootstrap.Modal(modalEl);

    document.querySelectorAll('.btn-view-month').forEach(button => {
        button.addEventListener('click', function () {
            const ym = this.getAttribute('data-ym');
            const label = this.getAttribute('data-label');

            document.getElementById('modalMonthTitle').textContent = label;
            document.getElementById('modalLoadingSpinner').classList.remove('d-none');
            document.getElementById('modalContentWrap').classList.add('d-none');
            document.getElementById('modalEmptyMessage').classList.add('d-none');
            document.getElementById('daywiseTableBody').innerHTML = '';
            document.getElementById('purchasesTableBody').innerHTML = '';

            // Reset to Daywise tab as default
            const dayTabTrigger = new bootstrap.Tab(document.getElementById('daywise-tab'));
            dayTabTrigger.show();

            modal.show();

            fetch('<?= base_url('creditlimit/month_details?ym=') ?>' + ym)
                .then(response => response.json())
                .then(res => {
                    document.getElementById('modalLoadingSpinner').classList.add('d-none');
                    if (res && res.status) {
                        // 1. Populate Ribbon
                        if (res.month_summary) {
                            const op = parseFloat(res.month_summary.opening || 0);
                            const pur = parseFloat(res.month_summary.purchases || 0);
                            const rec = parseFloat(res.month_summary.recoveries || 0);
                            const bal = parseFloat(res.month_summary.balance || 0);
                            const intVal = parseFloat(res.month_summary.interest || 0);
                            const cl = parseFloat(res.month_summary.closing || 0);

                            document.getElementById('ribbonOpening').textContent = (op > 0 ? '- ' : '') + '₹ ' + op.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            document.getElementById('ribbonPurchases').textContent = '₹ ' + pur.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            document.getElementById('ribbonRecoveries').textContent = (rec > 0 ? '+ ' : '') + '₹ ' + rec.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            document.getElementById('ribbonBalance').textContent = (bal > 0 ? '- ' : '') + '₹ ' + bal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            document.getElementById('ribbonInterest').textContent = (intVal > 0 ? '- ' : '') + '₹ ' + intVal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            document.getElementById('ribbonClosing').textContent = (cl > 0 ? '- ' : '') + '₹ ' + cl.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }

                        // 2. Populate Day-wise Rows
                        if (res.days && res.days.length > 0) {
                            let dayHtml = '';
                            res.days.forEach(d => {
                                let rowClass = '';
                                if (d.is_today) {
                                    rowClass = 'table-primary fw-bold';
                                } else if (d.is_future) {
                                    rowClass = '';
                                } else if (d.has_activity) {
                                    rowClass = 'table-warning fw-semibold';
                                }

                                dayHtml += `
                                    <tr class="${rowClass}">
                                        <td class="fw-bold text-dark">
                                            ${d.date_formatted || d.date}
                                            ${d.is_today ? '<span class="badge bg-primary ms-1" style="font-size:9px;">Today</span>' : ''}
                                        </td>
                                        <td class="text-end ${d.opening > 0 ? 'text-danger fw-bold' : 'text-dark'}">
                                            ${d.opening > 0 ? '- ₹ ' + parseFloat(d.opening).toFixed(2) : '₹ 0.00'}
                                        </td>
                                        <td class="text-end">
                                            ${d.purchase > 0 ? '<span class="text-danger fw-bold">₹ ' + parseFloat(d.purchase).toFixed(2) + '</span>' : '<span class="dash-cell">-</span>'}
                                        </td>
                                        <td class="text-end fw-bold ${d.total > 0 ? 'text-danger' : 'text-dark'}">
                                            ${d.total > 0 ? '- ₹ ' + parseFloat(d.total).toFixed(2) : '₹ 0.00'}
                                        </td>
                                        <td class="text-end">
                                            ${d.recovery > 0 ? '<span class="text-success fw-bold">+ ₹ ' + parseFloat(d.recovery).toFixed(2) + '</span>' : '<span class="dash-cell">-</span>'}
                                        </td>
                                        <td class="text-end">
                                            ${d.balance > 0 ? '<span class="text-danger fw-bold">- ₹ ' + parseFloat(d.balance).toFixed(2) + '</span>' : '<span class="dash-cell">-</span>'}
                                        </td>
                                        <td class="text-end">
                                            ${d.panel_interest > 0 ? '<span class="text-warning fw-bold">- ₹ ' + parseFloat(d.panel_interest).toFixed(2) + '</span>' : '<span class="dash-cell">-</span>'}
                                        </td>
                                        <td class="text-end fw-bold ${d.closing > 0 ? 'text-danger' : 'text-dark'}">
                                            ${d.closing > 0 ? '- ₹ ' + parseFloat(d.closing).toFixed(2) : '₹ 0.00'}
                                        </td>
                                    </tr>
                                `;
                            });
                            document.getElementById('daywiseTableBody').innerHTML = dayHtml;
                        }

                        // 3. Populate Purchases
                        const pCount = (res.purchases && res.purchases.length) ? res.purchases.length : 0;
                        document.getElementById('purchaseCountBadge').textContent = pCount;

                        if (pCount > 0) {
                            let pHtml = '';
                            res.purchases.forEach(item => {
                                let badgeClass = 'bg-danger';
                                if (item.recovery_status === 'Fully Recovered') {
                                    badgeClass = 'bg-success';
                                } else if (item.recovery_status === 'Partially Recovered') {
                                    badgeClass = 'bg-warning text-dark';
                                }

                                pHtml += `
                                    <tr>
                                        <td class="fw-bold text-dark">${item.purchase_date}</td>
                                        <td class="fw-bold text-primary">${item.service_name}</td>
                                        <td class="text-end fw-bold text-dark">₹ ${parseFloat(item.purchase_amount).toFixed(2)}</td>
                                        <td class="text-end ${item.wallet_deduction > 0 ? 'text-success fw-bold' : 'dash-cell'}">${item.wallet_deduction > 0 ? '₹ ' + parseFloat(item.wallet_deduction).toFixed(2) : '-'}</td>
                                        <td class="text-end text-danger fw-bold">₹ ${parseFloat(item.credit_used).toFixed(2)}</td>
                                        <td class="text-dark fw-semibold">${item.interest_start_date}</td>
                                        <td class="text-center fw-bold text-dark">${item.eligible_unpaid_days} <small class="text-muted">(${item.total_eligible_days} tot)</small></td>
                                        <td class="text-center fw-bold text-dark">${item.interest_rate_percent}%</td>
                                        <td class="text-end text-warning fw-bold">₹ ${parseFloat(item.month_accrued_interest).toFixed(2)}</td>
                                        <td class="text-end text-warning fw-bold">₹ ${parseFloat(item.total_accrued_interest).toFixed(2)}</td>
                                        <td class="text-dark fw-semibold">${item.recovery_date || '—'}</td>
                                        <td class="text-end text-success fw-bold">₹ ${parseFloat(item.recovered_principal).toFixed(2)}</td>
                                        <td class="text-end text-danger fw-bold">₹ ${parseFloat(item.remaining_principal).toFixed(2)}</td>
                                        <td class="text-center"><span class="badge ${badgeClass} px-2 py-1">${item.recovery_status}</span></td>
                                    </tr>
                                `;
                            });
                            document.getElementById('purchasesTableBody').innerHTML = pHtml;
                        } else {
                            document.getElementById('purchasesTableBody').innerHTML = `
                                <tr>
                                    <td colspan="14" class="text-center py-4 text-muted fw-bold">No packages or services purchased during this month.</td>
                                </tr>
                            `;
                        }

                        document.getElementById('modalContentWrap').classList.remove('d-none');
                    } else {
                        document.getElementById('modalEmptyMessage').classList.remove('d-none');
                    }
                })
                .catch(err => {
                    document.getElementById('modalLoadingSpinner').classList.add('d-none');
                    document.getElementById('modalEmptyMessage').textContent = 'Error loading ledger details.';
                    document.getElementById('modalEmptyMessage').classList.remove('d-none');
                });
        });
    });
});
</script>
