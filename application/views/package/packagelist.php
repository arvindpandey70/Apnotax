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
<style>
    .pkg-page {
        width: 100%;
        margin: 0 auto;
    }
    .pkg-card-wrapper {
        min-height: 70vh;
    }
    .pkg-section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1rem;
        font-weight: 600;
        color: #495057;
        margin: 1.5rem 0 1rem 0;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e8ecef;
    }
    .table-pkg th {
        background-color: #f8f9fa;
        color: #495057;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        border-bottom-width: 2px;
    }
    .table-pkg td {
        font-size: 0.9rem;
        vertical-align: middle;
    }
    .pkg-type-pill {
        display: inline-block;
        padding: 3px 8px;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 12px;
        text-align: center;
    }
    .state-pill {
        display: inline-block;
        padding: 3px 8px;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 12px;
    }
    .state-active {
        background-color: #d4edda;
        color: #155724;
    }
    .state-overdue {
        background-color: #f8d7da;
        color: #721c24;
    }
</style>

<div class="pkg-page mt-4">

    <?php if (!empty($service_packages)) : ?>
        <div class="pkg-section-title">
            <i class="fe fe-layers"></i> Your Packages
        </div>
        
        <div class="card shadow-sm border-0 pkg-card-wrapper">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-pkg mb-0">
                        <thead>
                            <tr>
                                <th>S.No.</th>
                                <th>Services</th>
                                <th>Financial Year</th>
                                <th>Type</th>
                                <th>Purchased</th>
                                <th>Expiry</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sno = 1;
                            foreach ($service_packages as $pkg) :
                                /* resolve services */
                                $pkg_ids = !empty($pkg['service_ids'])
                                    ? array_filter(array_map('trim', explode(',', $pkg['service_ids']))) : [];
                                $pkg_services = [];
                                $service_opts = !empty($pkg['service_option_ids']) ? json_decode($pkg['service_option_ids'], true) : [];
                                foreach ($all_services as $_s) {
                                    if (in_array((string)$_s['id'], $pkg_ids)) {
                                        $price = isset($_s['rate']) ? (float)$_s['rate'] : 0.0;
                                        if (!empty($service_opts[$_s['id']]) && isset($services_with_options[$_s['id']]['options'])) {
                                            $opt_id = $service_opts[$_s['id']];
                                            foreach ($services_with_options[$_s['id']]['options'] as $o) {
                                                if ($o['id'] == $opt_id && isset($o['rate']) && $o['rate'] > 0) {
                                                    $price = (float)$o['rate'];
                                                    break;
                                                }
                                            }
                                        }
                                        $pkg_services[] = [
                                            'name' => $_s['name'],
                                            'price' => $price
                                        ];
                                    }
                                }
                                $pkg_type   = !empty($pkg['package_type']) ? $pkg['package_type'] : 'Yearly';
                                $meta       = pkg_type_meta($pkg_type);
                                $state      = payment_state($pkg);
                                $is_expired = $state['cls'] === 'state-overdue';

                                if (!empty($pkg_services)): 
                                    foreach ($pkg_services as $svc):
                            ?>
                                <tr>
                                    <td class="text-muted fw-bold">
                                        <?= $sno++ ?>
                                    </td>
                                    <td>
                                        <div class="mb-1"><span class="badge bg-light text-dark border" style="font-weight: 500;"><?= htmlspecialchars($svc['name']) ?></span></div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($pkg['year'] ?? '') ?>
                                    </td>
                                    <td>
                                        <?= pkg_type_badge_inline($pkg_type) ?>
                                    </td>
                                    <td>
                                        <?= !empty($pkg['purchase_date']) ? date('d M Y', strtotime($pkg['purchase_date'])) : '—' ?>
                                    </td>
                                    <td class="<?= $is_expired ? 'text-danger fw-bold' : '' ?>">
                                        <?= !empty($pkg['expiry_date']) ? date('d M Y', strtotime($pkg['expiry_date'])) : '—' ?>
                                    </td>
                                    <td class="fw-bold" style="color:<?= $meta['color'] ?>">
                                        ₹<?= number_format($svc['price'] ?? 0, 0) ?>
                                    </td>
                                    <td>
                                        <span class="state-pill <?= $state['cls'] ?>">
                                            <i class="<?= $state['icon'] ?>"></i>
                                            <?= $state['label'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($is_expired) : ?>
                                            <button class="btn btn-danger btn-sm pay-bill-btn"
                                                data-pkg-id="<?= $pkg['id'] ?>"
                                                data-amount="<?= $pkg['bill_amount'] ?>">
                                                Pay Now
                                            </button>
                                        <?php else : ?>
                                                <form method="post" action="<?= base_url('package/requestdelete') ?>"
                                                    class="delete-pkg-form"
                                                    style="display:inline">
                                                    <input type="hidden" name="package_id" value="<?= $pkg['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        <i class="fe fe-trash-2"></i> Delete
                                                    </button>
                                                </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td class="text-muted fw-bold">
                                        <?= $sno++ ?>
                                    </td>
                                    <td>
                                        <span class="text-muted">—</span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($pkg['year'] ?? '') ?>
                                    </td>
                                    <td>
                                        <?= pkg_type_badge_inline($pkg_type) ?>
                                    </td>
                                    <td>
                                        <?= !empty($pkg['purchase_date']) ? date('d M Y', strtotime($pkg['purchase_date'])) : '—' ?>
                                    </td>
                                    <td class="<?= $is_expired ? 'text-danger fw-bold' : '' ?>">
                                        <?= !empty($pkg['expiry_date']) ? date('d M Y', strtotime($pkg['expiry_date'])) : '—' ?>
                                    </td>
                                    <td class="fw-bold" style="color:<?= $meta['color'] ?>">
                                        ₹<?= number_format($pkg['bill_amount'] ?? 0, 0) ?>
                                    </td>
                                    <td>
                                        <span class="state-pill <?= $state['cls'] ?>">
                                            <i class="<?= $state['icon'] ?>"></i>
                                            <?= $state['label'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($is_expired) : ?>
                                            <button class="btn btn-danger btn-sm pay-bill-btn"
                                                data-pkg-id="<?= $pkg['id'] ?>"
                                                data-amount="<?= $pkg['bill_amount'] ?>">
                                                Pay Now
                                            </button>
                                        <?php else : ?>
                                                <form method="post" action="<?= base_url('package/requestdelete') ?>"
                                                    class="delete-pkg-form"
                                                    style="display:inline">
                                                    <input type="hidden" name="package_id" value="<?= $pkg['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        <i class="fe fe-trash-2"></i> Delete
                                                    </button>
                                                </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else : ?>
        <div class="empty-state text-center p-5 bg-white rounded border shadow-sm mt-4">
            <i class="fe fe-inbox text-muted mb-3" style="font-size: 3rem;"></i>
            <h5 class="text-dark fw-bold">No Packages Found</h5>
            <p class="text-muted">You haven't purchased any service packages yet.</p>
            <a href="<?= base_url('package') ?>" class="btn btn-primary mt-2">
                <i class="fe fe-plus-circle me-1"></i>Create a Package
            </a>
        </div>
    <?php endif; ?>

</div><!-- /.pkg-page -->

<script>
    (function($) {
        'use strict';

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
                    text: 'Are you sure you want to delete this package?',
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

        /* ── Pay Bill button AJAX ──────────────────────── */
        $('body').on('click', '.pay-bill-btn', function() {
            var $btn = $(this);
            var pkgId = $btn.data('pkg-id');
            var amount = parseFloat($btn.data('amount')) || 0;
            var amtFmt = amount.toLocaleString('en-IN', {
                minimumFractionDigits: 2
            });

            function executePayment() {
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
                                    else $btn.prop('disabled', false).html('Pay Now');
                                });
                            } else {
                                if (typeof alertify !== 'undefined') alertify.error(r.message || 'Payment failed.');
                                if (r.redirect) setTimeout(function() { location.href = r.redirect; }, 2000);
                                $btn.prop('disabled', false).html('Pay Now');
                            }
                        }
                    },
                    error: function() {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ title: 'Error', text: 'An error occurred. Please try again.', icon: 'error', confirmButtonText: 'OK' });
                        } else if (typeof alertify !== 'undefined') {
                            alertify.error('An error occurred. Please try again.');
                        }
                        $btn.prop('disabled', false).html('Pay Now');
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
                        executePayment();
                    }
                });
            } else {
                if (confirm('Pay package bill of ₹' + amtFmt + '?\n\nThis amount will be deducted from your wallet.')) {
                    executePayment();
                }
            }
        });
    })(jQuery);
</script>