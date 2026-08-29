<?php
$button='';
?>
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Upload Old Data - <?= $customer['name']; ?></h3>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-4">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <?php
                                                    $name=!empty($customer['name'])?$customer['name']:'';
                                                    $attributes=array("id"=>"name","Placeholder"=>"Customer Name","autocomplete"=>"off","readonly"=>"true");
                                                    echo create_form_input("text","name","Customer Name",true,$name,$attributes); 
                                                ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <?php
                                                    $mobile=!empty($customer['mobile'])?$customer['mobile']:'';
                                                    $attributes=array("id"=>"mobile","Placeholder"=>"Mobile","autocomplete"=>"off","readonly"=>"true");
                                                    echo create_form_input("text","mobile","Mobile",true,$mobile,$attributes); 
                                                ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <?php
                                                    $email=!empty($customer['email'])?$customer['email']:'';
                                                    $attributes=array("id"=>"email","Placeholder"=>"Email","autocomplete"=>"off","readonly"=>"true");
                                                    echo create_form_input("email","email","Email",false,$email,$attributes); 
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <h4 class="mb-3">Upload New Old Data</h4>
                                    <?= form_open_multipart('customers/saveolddata'); ?>
                                    <input type="hidden" name="customer_id" value="<?= md5($customer['id']); ?>">
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Service <span class="text-danger">*</span></label>
                                                <?php
                                                    $options=array(''=>'Select Service');
                                                    if(!empty($services)){
                                                        foreach($services as $service){
                                                            $options[$service['id']]=$service['name'];
                                                        }
                                                    }
                                                    $attributes=array("id"=>"service_id","class"=>"form-control","required"=>"required");
                                                    echo form_dropdown('service_id',$options,'',$attributes);
                                                ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <?php
                                                    $year=!empty($year)?$year:'';
                                                    $attributes=array("id"=>"year","Placeholder"=>"e.g. 2023-24 or 2024","autocomplete"=>"off");
                                                    echo create_form_input("text","year","Year (Optional)",false,$year,$attributes); 
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <?php
                                                    $description='';
                                                    $attributes=array("id"=>"description","Placeholder"=>"Description/Notes (Optional)","rows"=>"3");
                                                    echo create_form_input("textarea","description","Description",false,$description,$attributes); 
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>File <span class="text-danger">*</span></label>
                                                <?php 
                                                    $attributes=array("id"=>"file","accept"=>"image/*|application/pdf|application/msword|application/vnd.openxmlformats-officedocument.wordprocessingml.document|application/vnd.ms-excel|application/vnd.openxmlformats-officedocument.spreadsheetml.sheet|application/zip|application/x-rar-compressed","required"=>"required");
                                                    echo create_form_input("file","file","Select File",true,'',$attributes); 
                                                ?>
                                                <small class="text-muted">Allowed types: Images, PDF, Word, Excel, ZIP, RAR</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-sm btn-success" name="saveolddata">
                                                <i class="fa fa-upload"></i> Upload Old Data
                                            </button>
                                            <a href="<?= base_url('customers/'); ?>" class="btn btn-sm btn-danger">Cancel</a>
                                        </div>
                                    </div>
                                    <?= form_close(); ?>
                                    
                                    <hr>
                                    
                                    <h4 class="mb-3">Previously Uploaded Old Data</h4>
                                    <?php if(!empty($old_data)){ ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Service</th>
                                                    <th>File Name</th>
                                                    <th>Year</th>
                                                    <th>Description</th>
                                                    <th>Uploaded On</th>
                                                    <th>Uploaded By</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($old_data as $data){ ?>
                                                <tr>
                                                    <td><?= !empty($data['service_name'])?$data['service_name']:'-'; ?></td>
                                                    <td><?= !empty($data['file_name'])?$data['file_name']:'-'; ?></td>
                                                    <td><?= !empty($data['year'])?$data['year']:'-'; ?></td>
                                                    <td><?= !empty($data['description'])?$data['description']:'-'; ?></td>
                                                    <td><?= !empty($data['added_on'])?date('d-m-Y H:i',strtotime($data['added_on'])):'-'; ?></td>
                                                    <td><?= !empty($data['uploaded_by_name'])?$data['uploaded_by_name']:'-'; ?></td>
                                                    <td>
                                                        <a href="<?= base_url('customers/downloadolddata/'.md5($data['id'])); ?>" class="btn btn-sm btn-info" title="Download">
                                                            <i class="fa fa-download"></i>
                                                        </a>
                                                        <a href="<?= base_url('customers/deleteolddata/'.md5($data['id'])); ?>" 
                                                           class="btn btn-sm btn-danger" 
                                                           onclick="return confirm('Are you sure you want to delete this data?');" 
                                                           title="Delete">
                                                            <i class="fa fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php } else { ?>
                                    <div class="alert alert-info">
                                        <i class="fa fa-info-circle"></i> No old data uploaded yet for this customer.
                                    </div>
                                    <?php } ?>
                                    
                                </div>
                            </div>

