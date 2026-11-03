<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Calculator | ApnoTax</title>
    <link rel="icon" href="./images/logo.png">
    <?php include "./temp/inc.php" ?>
    <style>
        :root {
            --brand-orange: #F3782B;
            --brand-orange-dark: #D47203;
            --brand-navy: #002252;
            --brand-blue: #0088CC;
            --brand-dark: #111C22;
            --brand-light: #F8F9FA;
            --card-shadow: 0 10px 30px rgba(0, 34, 82, 0.08);
            --card-shadow-hover: 0 18px 40px rgba(0, 34, 82, 0.14);
        }

        .calc-hero {
            background: linear-gradient(135deg, #00183b 0%, #002252 60%, #00337a 100%);
            color: #fff;
            padding: 60px 0 80px;
            position: relative;
            overflow: hidden;
        }

        .calc-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(243, 120, 43, 0.25) 0%, rgba(243, 120, 43, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .calc-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(243, 120, 43, 0.2);
            border: 1px solid rgba(243, 120, 43, 0.5);
            color: #ff9b57;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .calc-hero-title {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 15px;
            line-height: 1.2;
        }

        .calc-hero-title span {
            color: var(--brand-orange);
        }

        .calc-hero-subtitle {
            font-size: 1.15rem;
            color: #d0d7e5;
            max-width: 650px;
            line-height: 1.6;
        }

        .calc-container {
            margin-top: -50px;
            position: relative;
            z-index: 10;
            margin-bottom: 80px;
        }

        .calc-main-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(0, 34, 82, 0.08);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        /* Package Selector Cards */
        .plan-select-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 25px;
        }

        @media (max-width: 768px) {
            .plan-select-grid {
                grid-template-columns: 1fr;
            }
            .calc-hero-title {
                font-size: 2rem;
            }
        }

        .plan-card-option {
            border: 2px solid #e5e9f2;
            border-radius: 14px;
            padding: 18px 20px;
            cursor: pointer;
            transition: all 0.25s ease;
            position: relative;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .plan-card-option:hover {
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }

        .plan-card-option.active {
            border-color: var(--brand-orange);
            background: #fff8f3;
            box-shadow: 0 4px 18px rgba(243, 120, 43, 0.15);
        }

        .plan-card-option .radio-indicator {
            width: 22px;
            height: 22px;
            border: 2px solid #cbd5e1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .plan-card-option.active .radio-indicator {
            border-color: var(--brand-orange);
            background: var(--brand-orange);
        }

        .plan-card-option .radio-indicator::after {
            content: '';
            width: 8px;
            height: 8px;
            background: #fff;
            border-radius: 50%;
            opacity: 0;
            transform: scale(0);
            transition: all 0.2s ease;
        }

        .plan-card-option.active .radio-indicator::after {
            opacity: 1;
            transform: scale(1);
        }

        .plan-title-wrap h5 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--brand-navy);
        }

        .plan-title-wrap small {
            color: #64748b;
            font-size: 0.85rem;
        }

        .plan-tag {
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 8px;
        }

        .plan-tag-prime {
            background: #e0f2fe;
            color: #0369a1;
        }

        .plan-tag-premium {
            background: #fef3c7;
            color: #b45309;
        }

        /* Turnover input */
        .turnover-input-wrapper {
            position: relative;
            margin-bottom: 10px;
        }

        .turnover-currency-symbol {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--brand-orange);
        }

        .turnover-input {
            width: 100%;
            height: 65px;
            padding-left: 55px;
            padding-right: 120px;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--brand-navy);
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            transition: all 0.25s ease;
            background: #f8fafc;
        }

        .turnover-input:focus {
            outline: none;
            border-color: var(--brand-orange);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(243, 120, 43, 0.12);
        }

        .turnover-suffix {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.95rem;
            font-weight: 600;
            pointer-events: none;
        }

        /* Words format badge */
        .turnover-in-words {
            font-size: 0.95rem;
            color: #475569;
            margin-top: 6px;
            font-weight: 600;
            min-height: 24px;
        }

        .turnover-in-words span {
            color: var(--brand-orange-dark);
            font-weight: 700;
        }

        /* Quick chip buttons */
        .quick-chips-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
            margin-bottom: 25px;
        }

        .quick-chip {
            border: 1px solid #e2e8f0;
            background: #f1f5f9;
            color: #334155;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .quick-chip:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
            color: var(--brand-navy);
        }

        .quick-chip.active {
            background: var(--brand-navy);
            color: #fff;
            border-color: var(--brand-navy);
        }

        /* Results Display Cards */
        .result-box-hero {
            background: linear-gradient(135deg, #002252 0%, #001533 100%);
            border-radius: 16px;
            padding: 30px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 34, 82, 0.25);
            margin-bottom: 25px;
        }

        .result-box-hero::after {
            content: '';
            position: absolute;
            right: -30px;
            bottom: -30px;
            width: 160px;
            height: 160px;
            background: radial-gradient(circle, rgba(243, 120, 43, 0.3) 0%, rgba(243, 120, 43, 0) 70%);
            border-radius: 50%;
        }

        .result-primary-value {
            font-size: 2.8rem;
            font-weight: 800;
            color: #ff9b57;
            line-height: 1.1;
            margin-top: 8px;
            margin-bottom: 6px;
        }

        /* Monthly Reports Table (Replica of reports/accountancy) */
        .reports-preview-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            margin-top: 30px;
        }

        .reports-preview-header {
            background: #f8fafc;
            padding: 16px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .reports-preview-header h5 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--brand-navy);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .reports-table-badge {
            background: #e2e8f0;
            color: #334155;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            font-family: monospace;
        }

        /* Month Selector Toolbar */
        .calc-month-toolbar {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .calc-duration-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .duration-pill {
            background: #fff;
            border: 1.5px solid #cbd5e1;
            color: #334155;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .duration-pill:hover {
            border-color: var(--brand-orange);
            color: var(--brand-orange-dark);
            background: #fffaf5;
        }

        .duration-pill.active {
            background: var(--brand-orange);
            border-color: var(--brand-orange);
            color: #fff;
            box-shadow: 0 2px 8px rgba(243, 120, 43, 0.35);
        }

        .table-accounting {
            margin-bottom: 0;
        }

        .table-accounting th {
            background: #002252;
            color: #fff;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 12px 18px;
            border-color: #00183b;
        }

        .table-accounting td {
            padding: 12px 18px;
            font-size: 0.92rem;
            vertical-align: middle;
            border-color: #eef2f6;
        }

        .table-accounting tbody tr:hover {
            background-color: #fffaf5;
        }

        .table-accounting tfoot tr {
            background-color: #f1f5f9;
            font-weight: 700;
        }

        .table-accounting tfoot th {
            background-color: #00183b;
            color: #fff;
            padding: 14px 18px;
            font-size: 0.95rem;
        }

        .cell-right {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        /* Slabs Reference Table */
        .slabs-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }

        .slabs-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        .slabs-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.92rem;
        }

        .slabs-table tr.active-slab-row {
            background-color: #fff3ea !important;
            font-weight: 700;
        }

        .slabs-table tr.active-slab-row td {
            color: var(--brand-orange-dark);
            border-bottom: 1px solid #ffd6b8;
        }

        .current-badge {
            background: var(--brand-orange);
            color: #fff;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-left: 6px;
        }
    </style>
</head>

<body>
    <?php include "./temp/navbar.php" ?>

    <!-- Hero Header -->
    <section class="calc-hero">
        <div class="container text-center">
            <div class="calc-hero-badge">
                <i class="fas fa-calculator"></i> Transparent Pricing
            </div>
            <h1 class="calc-hero-title">Accounting Fee <span>Calculator</span></h1>
            <p class="calc-hero-subtitle mx-auto">
                Turnover daliye aur turant dekhiye apna exact GST Accountancy Fee. Ye wahi calculation engine hai jo aapke internal reports system me kaam karta hai.
            </p>
        </div>
    </section>

    <!-- Main Calculator Content -->
    <div class="container calc-container">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">
                <div class="calc-main-card p-4 p-md-5">

                    <!-- Step 1: Package Selection -->
                    <label class="form-label fw-bold text-dark mb-2" style="font-size: 1.05rem;">
                        <i class="fas fa-layer-group text-primary me-2"></i> 1. Select Package
                    </label>
                    <div class="plan-select-grid">
                        <div class="plan-card-option active" id="opt-prime" onclick="selectPlan('prime')">
                            <div class="plan-title-wrap">
                                <h5>Accounting Prime <span class="plan-tag plan-tag-prime">Popular</span></h5>
                                <small>Ideal for small to medium businesses</small>
                            </div>
                            <div class="radio-indicator"></div>
                        </div>

                        <div class="plan-card-option" id="opt-premium" onclick="selectPlan('premium')">
                            <div class="plan-title-wrap">
                                <h5>Accounting Premium <span class="plan-tag plan-tag-premium">Comprehensive</span></h5>
                                <small>Full service CA compliance & advisory</small>
                            </div>
                            <div class="radio-indicator"></div>
                        </div>
                    </div>

                    <!-- Step 2: Turnover Input -->
                    <label class="form-label fw-bold text-dark mb-2" style="font-size: 1.05rem;">
                        <i class="fas fa-rupee-sign text-primary me-2"></i> 2. Enter Annual Business Turnover
                    </label>
                    <div class="turnover-input-wrapper">
                        <span class="turnover-currency-symbol">₹</span>
                        <input type="number" id="turnoverInput" class="turnover-input" value="5000000" min="0" step="50000" placeholder="e.g. 5000000" oninput="calculate()">
                        <span class="turnover-suffix">Turnover</span>
                    </div>

                    <div class="turnover-in-words" id="turnoverInWords">
                        Entered Turnover: <span id="wordsText">50 Lakh Rupees</span>
                    </div>

                    <!-- Quick Chips -->
                    <div class="quick-chips-wrap">
                        <span class="text-muted small align-self-center me-2 fw-semibold">Quick Slabs:</span>
                        <button type="button" class="quick-chip" onclick="setTurnover(1000000)">10 Lac</button>
                        <button type="button" class="quick-chip" onclick="setTurnover(2500000)">25 Lac</button>
                        <button type="button" class="quick-chip active" onclick="setTurnover(5000000)">50 Lac</button>
                        <button type="button" class="quick-chip" onclick="setTurnover(7500000)">75 Lac</button>
                        <button type="button" class="quick-chip" onclick="setTurnover(10000000)">1 Crore</button>
                        <button type="button" class="quick-chip" onclick="setTurnover(20000000)">2 Crore</button>
                        <button type="button" class="quick-chip" onclick="setTurnover(50000000)">5 Crore</button>
                    </div>

                    <!-- Primary Result Card -->
                    <div class="result-box-hero">
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <span class="badge bg-warning text-dark px-3 py-2 fw-bold text-uppercase mb-2" id="resPlanBadge">Accounting Prime</span>
                                <div class="text-white-50 small fw-bold text-uppercase letter-spacing-1" id="resFeeLabel">Calculated Annual Accounts Fee</div>
                                <div class="result-primary-value" id="resAnnualFee">₹ 20,000</div>
                                <div class="text-white-50 small">
                                    Applicable Slab: <strong class="text-white" id="resSlabName">&lt; 50 Lac Slab</strong>
                                </div>
                            </div>
                            <div class="col-md-5 mt-4 mt-md-0 border-start-md border-white-25 ps-md-4">
                                <div class="p-3 rounded" style="background: rgba(255, 255, 255, 0.08);">
                                    <div class="text-white-50 small fw-bold text-uppercase">Monthly Accounts Fee</div>
                                    <div class="h2 text-white fw-bold mb-1" id="resMonthlyFee">₹ 1,666.67</div>
                                    <div class="text-white-50 small" id="resMonthlyNote">Per month billed in 12 equal parts</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Breakdown Table Preview (Exact replica of reports/accountancy) -->
                    <div class="reports-preview-card">
                        <div class="reports-preview-header">
                            <div>
                                <h5>
                                    <i class="fas fa-table text-primary"></i>
                                    Monthly Breakdown Preview (Accounting Mode)
                                </h5>
                                <small class="text-muted">Exact 3-column format as generated in your <span class="reports-table-badge">reports/</span> client ledger</small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary px-3 py-2 fw-semibold" id="tablePackageBadge">Package: Accountancy Prime</span>
                            </div>
                        </div>

                        <!-- Month Duration Control Toolbar -->
                        <div class="calc-month-toolbar">
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <span class="fw-bold text-dark small text-uppercase me-1">
                                    <i class="fas fa-calendar-alt text-primary me-1"></i> Choose Duration:
                                </span>
                                <div class="calc-duration-pills" id="durationPillGroup">
                                    <button type="button" class="duration-pill" onclick="setMonths(1)">1 Month</button>
                                    <button type="button" class="duration-pill" onclick="setMonths(3)">3 Months</button>
                                    <button type="button" class="duration-pill" onclick="setMonths(6)">6 Months</button>
                                    <button type="button" class="duration-pill" onclick="setMonths(9)">9 Months</button>
                                    <button type="button" class="duration-pill active" onclick="setMonths(12)">12 Months (Full FY)</button>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <div class="d-flex align-items-center gap-1">
                                    <label for="startMonthSelect" class="small fw-semibold text-muted mb-0">From:</label>
                                    <select class="form-select form-select-sm" id="startMonthSelect" onchange="calculate()" style="width: auto; min-width: 110px;">
                                        <option value="0" selected>April</option>
                                        <option value="1">May</option>
                                        <option value="2">June</option>
                                        <option value="3">July</option>
                                        <option value="4">August</option>
                                        <option value="5">September</option>
                                        <option value="6">October</option>
                                        <option value="7">November</option>
                                        <option value="8">December</option>
                                        <option value="9">January</option>
                                        <option value="10">February</option>
                                        <option value="11">March</option>
                                    </select>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <label for="monthsCountSelect" class="small fw-semibold text-muted mb-0">To / Now:</label>
                                    <select class="form-select form-select-sm" id="monthsCountSelect" onchange="onMonthsDropdownChange(this.value)" style="width: auto; min-width: 140px;">
                                        <option value="1">April (1 Mo)</option>
                                        <option value="2">May (2 Mos)</option>
                                        <option value="3">June (3 Mos)</option>
                                        <option value="4">July (4 Mos)</option>
                                        <option value="5">August (5 Mos)</option>
                                        <option value="6">September (6 Mos)</option>
                                        <option value="7">October (7 Mos)</option>
                                        <option value="8">November (8 Mos)</option>
                                        <option value="9">December (9 Mos)</option>
                                        <option value="10">January (10 Mos)</option>
                                        <option value="11">February (11 Mos)</option>
                                        <option value="12" selected>March (12 Mos / Full FY)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-accounting" id="monthlyBreakdownTable">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th class="cell-right">GTO (Gross Turnover)</th>
                                        <th class="cell-right">Accounts Fee</th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody">
                                    <!-- Rendered dynamically by JS -->
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th id="tableTotalMonthsLabel">Total (12 Months)</th>
                                        <th class="cell-right" id="tableTotalGTO">₹ 50,00,000.00</th>
                                        <th class="cell-right" id="tableTotalFee">₹ 20,000.00</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Reference Slabs Table -->
                    <div class="mt-5">
                        <h5 class="fw-bold text-dark mb-3">
                            <i class="fas fa-list-check text-primary me-2"></i> Official Fee Slabs Reference
                        </h5>
                        <div class="table-responsive border rounded-3">
                            <table class="slabs-table">
                                <thead>
                                    <tr>
                                        <th>Turnover Slab</th>
                                        <th>Accounting Prime</th>
                                        <th>Accounting Premium</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr id="slab-row-25">
                                        <td>Up to ₹ 25 Lac (&lt; 25L)</td>
                                        <td><strong>₹ 12,000</strong> <small class="text-muted">(₹1,000/mo)</small></td>
                                        <td><strong>₹ 15,000</strong> <small class="text-muted">(₹1,250/mo)</small></td>
                                        <td><span class="badge bg-light text-dark border">Base Slab</span></td>
                                    </tr>
                                    <tr id="slab-row-50" class="active-slab-row">
                                        <td>₹ 25 Lac - ₹ 50 Lac (&lt; 50L)</td>
                                        <td><strong>₹ 20,000</strong> <small class="text-muted">(₹1,666.67/mo)</small></td>
                                        <td><strong>₹ 24,000</strong> <small class="text-muted">(₹2,000/mo)</small></td>
                                        <td><span class="current-badge" id="badge-50">Active</span></td>
                                    </tr>
                                    <tr id="slab-row-75">
                                        <td>₹ 50 Lac - ₹ 75 Lac (&lt; 75L)</td>
                                        <td><strong>₹ 25,000</strong> <small class="text-muted">(₹2,083.33/mo)</small></td>
                                        <td><strong>₹ 30,000</strong> <small class="text-muted">(₹2,500/mo)</small></td>
                                        <td><span class="badge bg-light text-dark border">Standard</span></td>
                                    </tr>
                                    <tr id="slab-row-100">
                                        <td>₹ 75 Lac - ₹ 100 Lac (&lt; 100L)</td>
                                        <td><strong>₹ 30,000</strong> <small class="text-muted">(₹2,500/mo)</small></td>
                                        <td><strong>₹ 36,000</strong> <small class="text-muted">(₹3,000/mo)</small></td>
                                        <td><span class="badge bg-light text-dark border">Growth</span></td>
                                    </tr>
                                    <tr id="slab-row-above">
                                        <td>Above ₹ 1 Crore (&gt; 100L)</td>
                                        <td><strong>Base ₹30,000 + ₹10,000</strong> / 100L</td>
                                        <td><strong>Base ₹36,000 + ₹15,000</strong> / 100L</td>
                                        <td><span class="badge bg-light text-dark border">Pro-rata</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include "./temp/footer.php" ?>
    <?php include "./temp/vendor.php" ?>

    <script>
        // Package rates defined in ApnoTax database tf_packages
        const PACKAGES_CONFIG = {
            prime: {
                id: 1,
                name: 'Accountancy Prime',
                display: 'Accounting Prime',
                slabs: [
                    { max: 2500000, rate: 12000, key: '25', label: '< 25 Lac' },
                    { max: 5000000, rate: 20000, key: '50', label: '< 50 Lac' },
                    { max: 7500000, rate: 25000, key: '75', label: '< 75 Lac' },
                    { max: 10000000, rate: 30000, key: '100', label: '< 100 Lac' }
                ],
                highestSlabTurnover: 10000000,
                highestSlabRate: 30000,
                perUnitUnit: 10000000,
                perUnitRate: 10000
            },
            premium: {
                id: 2,
                name: 'Accountancy Premium',
                display: 'Accounting Premium',
                slabs: [
                    { max: 2500000, rate: 15000, key: '25', label: '< 25 Lac' },
                    { max: 5000000, rate: 24000, key: '50', label: '< 50 Lac' },
                    { max: 7500000, rate: 30000, key: '75', label: '< 75 Lac' },
                    { max: 10000000, rate: 36000, key: '100', label: '< 100 Lac' }
                ],
                highestSlabTurnover: 10000000,
                highestSlabRate: 36000,
                perUnitUnit: 10000000,
                perUnitRate: 15000
            }
        };

        const MONTH_NAMES = [
            'April', 'May', 'June', 'July', 'August', 'September',
            'October', 'November', 'December', 'January', 'February', 'March'
        ];

        let currentPlanKey = 'prime';
        let selectedMonthsCount = 12;

        function updateEndMonthOptions(startIdx) {
            const selectEl = document.getElementById('monthsCountSelect');
            if (!selectEl) return;
            let html = '';
            for (let i = 1; i <= 12; i++) {
                const endMonthName = MONTH_NAMES[(startIdx + i - 1) % 12];
                const label = (i === 12)
                    ? `${endMonthName} (12 Mos / Full FY)`
                    : `${endMonthName} (${i} Mo${i > 1 ? 's' : ''})`;
                const selected = (i === selectedMonthsCount) ? 'selected' : '';
                html += `<option value="${i}" ${selected}>${label}</option>`;
            }
            selectEl.innerHTML = html;
        }

        function setMonths(n) {
            selectedMonthsCount = parseInt(n) || 12;
            const selectEl = document.getElementById('monthsCountSelect');
            if (selectEl) {
                selectEl.value = selectedMonthsCount;
            }
            // Update active state on duration pills
            document.querySelectorAll('#durationPillGroup .duration-pill').forEach(btn => {
                const clickText = btn.getAttribute('onclick') || '';
                if (clickText.indexOf('(' + selectedMonthsCount + ')') !== -1) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            calculate();
        }

        function onMonthsDropdownChange(val) {
            setMonths(val);
        }

        function selectPlan(planKey) {
            currentPlanKey = planKey;
            document.getElementById('opt-prime').classList.toggle('active', planKey === 'prime');
            document.getElementById('opt-premium').classList.toggle('active', planKey === 'premium');
            calculate();
        }

        function setTurnover(amount) {
            document.getElementById('turnoverInput').value = amount;
            // Update active state on quick chips
            document.querySelectorAll('.quick-chip').forEach(btn => {
                const clickText = btn.getAttribute('onclick') || '';
                if (clickText.indexOf('(' + amount + ')') !== -1) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            calculate();
        }

        // Convert number to Indian words
        function numberToIndianWords(num) {
            if (!num || num <= 0) return '0 Rupees';
            num = Math.round(num);
            if (num >= 10000000) {
                const cr = (num / 10000000).toFixed(2).replace(/\.00$/, '');
                return cr + ' Crore Rupees';
            } else if (num >= 100000) {
                const lac = (num / 100000).toFixed(2).replace(/\.00$/, '');
                return lac + ' Lakh Rupees';
            } else if (num >= 1000) {
                const th = (num / 1000).toFixed(2).replace(/\.00$/, '');
                return th + ' Thousand Rupees';
            }
            return num.toLocaleString('en-IN') + ' Rupees';
        }

        function formatINR(amount) {
            return '₹ ' + Number(amount || 0).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Calculation core - matches Reports.php::calculate_accountancy_fee
        function computeFee(planKey, turnover) {
            const config = PACKAGES_CONFIG[planKey];
            if (!turnover || turnover <= 0) {
                return {
                    fee: 0,
                    monthlyFee: 0,
                    slabLabel: 'No Turnover',
                    slabKey: '0',
                    isProRata: false
                };
            }

            let fee = 0;
            let slabLabel = '';
            let slabKey = '';
            let isProRata = false;

            if (turnover > config.highestSlabTurnover) {
                const extra = turnover - config.highestSlabTurnover;
                fee = config.highestSlabRate + (extra / config.perUnitUnit) * config.perUnitRate;
                slabLabel = '> 100 Lac (Pro-rata: +' + formatINR(config.perUnitRate) + ' / 100L)';
                slabKey = 'above';
                isProRata = true;
            } else {
                for (let s of config.slabs) {
                    if (turnover <= s.max) {
                        fee = s.rate;
                        slabLabel = s.label + ' Slab';
                        slabKey = s.key;
                        break;
                    }
                }
                if (!fee) {
                    const last = config.slabs[config.slabs.length - 1];
                    fee = last.rate;
                    slabLabel = last.label + ' Slab';
                    slabKey = last.key;
                }
            }

            fee = Math.round(fee * 100) / 100;
            const monthlyFee = Math.round((fee / 12) * 100) / 100;

            return {
                fee: fee,
                monthlyFee: monthlyFee,
                slabLabel: slabLabel,
                slabKey: slabKey,
                isProRata: isProRata
            };
        }

        function calculate() {
            const rawTurnover = parseFloat(document.getElementById('turnoverInput').value) || 0;
            const turnover = Math.max(0, rawTurnover);

            // Duration and start month
            const monthsCount = selectedMonthsCount;
            const startMonthSelect = document.getElementById('startMonthSelect');
            const startIdx = startMonthSelect ? (parseInt(startMonthSelect.value) || 0) : 0;
            updateEndMonthOptions(startIdx);

            // Words
            document.getElementById('wordsText').innerText = numberToIndianWords(turnover);

            // Current plan fee
            const currentPlan = PACKAGES_CONFIG[currentPlanKey];
            const result = computeFee(currentPlanKey, turnover);

            // Accounts Fee based on turnover slab (Full slab fee always applies)
            const totalAccountsFee = result.fee;
            const monthlyFee = monthsCount > 0 ? (totalAccountsFee / monthsCount) : 0;

            // Update Primary Cards
            document.getElementById('resPlanBadge').innerText = currentPlan.display;
            document.getElementById('resAnnualFee').innerText = formatINR(totalAccountsFee);
            document.getElementById('resFeeLabel').innerText = (monthsCount === 12) ? 'Total Annual Accounts Fee' : `Total Accounts Fee (${monthsCount} Months)`;
            document.getElementById('resMonthlyFee').innerText = formatINR(monthlyFee);
            document.getElementById('resMonthlyNote').innerText = (monthsCount === 12)
                ? 'Per month billed in 12 equal parts'
                : `₹ ${formatINR(totalAccountsFee).replace('₹ ', '')} divided into ${monthsCount} month(s)`;
            document.getElementById('resSlabName').innerText = result.slabLabel;

            // Table header badge
            document.getElementById('tablePackageBadge').innerText = 'Package: ' + currentPlan.name;

            // Render monthly table (matching reports/accountancy: $fees / $activeMonthsCount)
            const monthlyGTO = turnover > 0 ? (turnover / monthsCount) : 0;
            let tbodyHtml = '';

            for (let i = 0; i < monthsCount; i++) {
                const monthName = MONTH_NAMES[(startIdx + i) % 12];
                tbodyHtml += `
                    <tr>
                        <td class="fw-semibold">${monthName}</td>
                        <td class="cell-right">${formatINR(monthlyGTO)}</td>
                        <td class="cell-right fw-semibold text-primary">${formatINR(monthlyFee)}</td>
                    </tr>
                `;
            }
            document.getElementById('tableBody').innerHTML = tbodyHtml;

            // Footer total
            const footerMonthsLabel = document.getElementById('tableTotalMonthsLabel');
            if (footerMonthsLabel) {
                footerMonthsLabel.innerText = `Total (${monthsCount} Month${monthsCount > 1 ? 's' : ''})`;
            }
            document.getElementById('tableTotalGTO').innerText = formatINR(turnover);
            document.getElementById('tableTotalFee').innerText = formatINR(totalAccountsFee);

            // Highlight active row in Reference Table
            const rows = ['25', '50', '75', '100', 'above'];
            rows.forEach(k => {
                const rowEl = document.getElementById('slab-row-' + k);
                if (rowEl) {
                    if (k === result.slabKey) {
                        rowEl.classList.add('active-slab-row');
                        let badge = rowEl.querySelector('.current-badge, .badge');
                        if (badge) {
                            badge.className = 'current-badge';
                            badge.innerText = 'Active';
                        }
                    } else {
                        rowEl.classList.remove('active-slab-row');
                        let badge = rowEl.querySelector('.current-badge, .badge');
                        if (badge) {
                            badge.className = 'badge bg-light text-dark border';
                            badge.innerText = (k === '25' ? 'Base' : (k === 'above' ? 'Pro-rata' : 'Standard'));
                        }
                    }
                }
            });
        }

        // Run on page load
        document.addEventListener('DOMContentLoaded', function () {
            calculate();
        });
    </script>
</body>

</html>
