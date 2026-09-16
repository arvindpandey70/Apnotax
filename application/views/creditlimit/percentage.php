<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Credit Limit Percentage</h3>
            </div>
            <div class="card-body">
                <?php if ($this->session->flashdata('msg')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $this->session->flashdata('msg'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($this->session->flashdata('err_msg')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $this->session->flashdata('err_msg'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card border p-4">
                            <h4 class="card-title mb-3" id="form-title">Set Percentage</h4>
                            <?= form_open('creditlimit/addpercentage', ['id' => 'percentage-form']); ?>
                                <input type="hidden" name="id" id="percent_id" value="">
                                <div class="form-group mb-3">
                                    <label for="percent" class="form-label">Percentage (%) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="percent" id="percent" placeholder="e.g. 2, 3, 4, 5" required>
                                    <small class="text-muted">Enter percentage value (ex: 2, 3, 4, 5)</small>
                                </div>
                                <div class="form-group mt-3">
                                    <button type="submit" class="btn btn-primary" id="save-btn">Save Percentage</button>
                                    <button type="button" class="btn btn-secondary cancel-edit-btn d-none" id="cancel-btn">Cancel</button>
                                </div>
                            <?= form_close(); ?>
                        </div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <div class="table-responsive">
                            <table class="table table-bordered text-nowrap" id="percentage-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Percentage (%)</th>
                                        <th>Status / Valid Upto</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($percentages)): ?>
                                        <?php $i = 0; foreach ($percentages as $row): $i++; ?>
                                            <tr>
                                                <td><?= $i; ?></td>
                                                <td><strong><?= htmlspecialchars($row['percent']); ?> %</strong></td>
                                                <td>
                                                    <?php if ($row['status'] == 1): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Valid Upto <?= date('d-m-Y H:i', strtotime($row['updated_on'])); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary edit-percent-btn" data-id="<?= $row['id']; ?>" title="Edit">
                                                        <i class="fa fa-edit"></i> Edit
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No percentage record found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#percentage-table').on('click', '.edit-percent-btn', function() {
        var id = $(this).data('id');
        $.ajax({
            url: "<?= base_url('creditlimit/getpercentage') ?>",
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if(res) {
                    $('#percent_id').val(res.id);
                    $('#percent').val(res.percent);
                    $('#form-title').text('Edit Percentage');
                    $('#save-btn').text('Update Percentage').removeClass('btn-primary').addClass('btn-success');
                    $('#cancel-btn').removeClass('d-none');
                    $('html, body').animate({ scrollTop: $('#percentage-form').offset().top - 100 }, 300);
                }
            }
        });
    });

    $('#cancel-btn').on('click', function() {
        $('#percent_id').val('');
        $('#percent').val('');
        $('#form-title').text('Set Percentage');
        $('#save-btn').text('Save Percentage').removeClass('btn-success').addClass('btn-primary');
        $('#cancel-btn').addClass('d-none');
    });
});
</script>
