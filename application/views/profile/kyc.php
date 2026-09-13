<?php
$button = '';
$status_badge = '';
// Hide submit button while KYC is pending admin approval.
if (empty($kyc) || !isset($kyc['status']) || (int)$kyc['status'] !== 0) {
    $button = '<input type="submit" class="btn btn-sm btn-success" name="updatekyc" value="Submit KYC for Approval">&nbsp;';
}
if (!empty($kyc) && isset($kyc['status'])) {
    if ((int)$kyc['status'] === 1) {
        $status_badge = '<span class="badge bg-success">Approved</span>';
    } elseif ((int)$kyc['status'] === 0) {
        $status_badge = '<span class="badge bg-warning text-dark">Pending Admin Approval</span>';
    } else {
        $status_badge = '<span class="badge bg-danger">Rejected</span>';
    }
}
?>
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <?= form_open_multipart('profile/updatekyc/'); ?>
                                                <?php if (!empty($status_badge)) { ?>
                                                    <div class="mb-2"><?= $status_badge; ?></div>
                                                <?php } ?>
                                                <?php if (!empty($firm_id)) : ?>
                                                    <input type="hidden" name="firm_id" value="<?= $firm_id; ?>">
                                                <?php endif; ?>
                                                <div class="form-group">
                                                    <?php 
                                                        // Aadhar is now optional - remove required attribute
                                                        $attributes=array("Placeholder"=>"Aadhar (Optional)",'pattern'=>'[0-9]{12}$','title'=>"Enter Valid 12-digit Aadhar No");
                                                        echo create_form_input("text","aadhar","Aadhar (Optional)",false,$kyc['aadhar']??'',$attributes); 
                                                    ?>
                                                </div>
                                                <div class="form-group">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                             <?php 
                                                                $attributes=array("id"=>"aadhar_image","onChange"=>"getPhoto(this,'aadhar_image')","accept"=>"image/*");
                                                                echo create_form_input("file","aadhar_image","Upload Aadhar Card Front (Optional):",false,'',$attributes); 
                                                                $aadhar_image="";
                                                                if(!empty($kyc['aadhar_image'])){
                                                                    $aadhar_image="src='".str_replace('//assets/','/assets/',$kyc['aadhar_image'])."'";
                                                                }
                                                            ?>
                                                            <?php if(!empty($kyc['aadhar_image'])){ ?>
                                                                <div class="mt-2">
                                                                    <a href="<?= base_url('profile/download_kyc_document/aadhar_image') ?>" class="btn btn-sm btn-success">
                                                                        <i class="fa fa-download"></i> Download Aadhar Front
                                                                    </a>
                                                                </div>
                                                            <?php } ?>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <img <?php echo $aadhar_image; ?> id="aadhar_imagepreview" style="height:150px; width:250px;" >
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <?php 
                                                                // Aadhar back image is now optional
                                                                $attributes=array("id"=>"aadhar_back","onChange"=>"getPhoto(this,'aadhar_back')","accept"=>"image/*");
                                                                echo create_form_input("file","aadhar_back","Upload Aadhar Card Back (Optional):",false,'',$attributes); 
                                                                $aadhar_back="";
                                                                if(!empty($kyc['aadhar_back'])){
                                                                    $aadhar_back="src='".str_replace('//assets/','/assets/',$kyc['aadhar_back'])."'";
                                                                }
                                                            ?>
                                                            <?php if(!empty($kyc['aadhar_back'])){ ?>
                                                                <div class="mt-2">
                                                                    <a href="<?= base_url('profile/download_kyc_document/aadhar_back') ?>" class="btn btn-sm btn-success">
                                                                        <i class="fa fa-download"></i> Download Aadhar Back
                                                                    </a>
                                                                </div>
                                                            <?php } ?>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <img <?php echo $aadhar_back; ?> id="aadhar_backpreview" style="height:150px; width:250px;" >
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <?php 
                                                        $attributes=array("Placeholder"=>"PAN (Optional)",'pattern'=>'^[A-Z]{5}\d{4}[A-Z]$','title'=>"Enter Valid PAN");
                                                        echo create_form_input("text","pan","PAN (Optional)",false,$kyc['pan']??'',$attributes); 
                                                    ?>
                                                </div>
                                                <div class="form-group">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <?php 
                                                                $attributes=array("id"=>"pan_image","onChange"=>"getPhoto(this,'pan_image')","accept"=>"image/*");
                                                                echo create_form_input("file","pan_image","Upload PAN Card (Optional):",false,'',$attributes); 
                                                                $pan_image="";
                                                                if(!empty($kyc['pan_image'])){
                                                                    $pan_image="src='".str_replace('//assets/','/assets/',$kyc['pan_image'])."'";
                                                                }
                                                            ?>
                                                            <?php if(!empty($kyc['pan_image'])){ ?>
                                                                <div class="mt-2">
                                                                    <a href="<?= base_url('profile/download_kyc_document/pan_image') ?>" class="btn btn-sm btn-success">
                                                                        <i class="fa fa-download"></i> Download PAN Card
                                                                    </a>
                                                                </div>
                                                            <?php } ?>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <img <?php echo $pan_image; ?> id="pan_imagepreview" style="height:150px; width:250px;" >
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row mb-4">
                                                    <div class="col-md-12">
                                                        <?= $button; ?>
                                                    </div>
                                                </div>
                                            <?= form_close(); ?>
                                        </div>
                                    </div>
                                </div>

                    </div>
            <script>
                $(document).ready(function(e) {
                    $('body').on('change','#parent_id',function(){
                        var parent_id=$(this).val();
                        var area_id=$('#area_id').data('value');
                        var state=$(this).find('option:selected').text();
                        $('#state').val(state);
                        $.ajax({
                            type:"post",
                            url:"<?= base_url('masterkey/getdistricts/'); ?>",
                            data:{parent_id:parent_id,area_id:area_id},
                            success:function(data){
                                $('#area_id').replaceWith(data);
                                if($('#area_id').val()=='')
                                    $('#district').val('');
                                //setarea_id();
                            }
                        });
                    });
                    $('form').on('change','#area_id',function(){
                        var district=$(this).find('option:selected').text();
                        $('#district').val(district);
                    });
                    $('form').on('change','#same',function(){
                        if($(this).is(':checked')){
                            $('#shipping_address').val($('#address').val());
                        }
                    });
                    $('form').on('keyup','#opening_balance',function(){
                        var balance=Number($(this).val());
                        if(balance>0){
                            $('.radio-options').removeClass('d-none');
                            $('#opening_date').attr('required',true);
                        }
                        else{
                            $('.radio-options').addClass('d-none');
                            $('#opening_date').removeAttr('required');
                        }
                    });
                });
                function getPhoto(input,field){
                    var id="#"+field;
                    var preview="#"+field+"preview";
                    $(preview).replaceWith('<img id="'+field+'preview" style="height:150px; width:250px;" >');
                    if (input.files && input.files[0]) {
                        var filename=input.files[0].name;
                        var re = /(?:\.([^.]+))?$/;
                        var ext = re.exec(filename)[1]; 
                        ext=ext.toLowerCase();
                        if(ext=='jpg' || ext=='jpeg' || ext=='png'){
                            var size=input.files[0].size;
                            if(size<=10485760 && size>=10240){
                                var reader = new FileReader();

                                reader.onload = function (e) {
                                    $(preview).attr('src',e.target.result);
                                }
                                reader.readAsDataURL(input.files[0]);
                            }
                            else if(size>=10485760){
                                alert("Image size is greater than 10MB");	
                                document.getElementById(field).value= null;
                            }
                            else if(size<=10240){
                                alert("Image size is less than 10KB");	
                                document.getElementById(field).value= null; 
                            }
                        }
                        else{
                            alert("Select 'jpeg' or 'jpg' or 'png' image file!!");	
                            document.getElementById(field).value= null;
                        }
                    }
                }
            </script>
            </div>