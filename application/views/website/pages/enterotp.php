<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter OTP | ApnoTax</title>
    <link rel="icon" href="./images/logo.png">
    <?php include "./temp/inc.php" ?>
    <style>
        .login-btn{
            line-height: 1.5;
            color: #fff;
            width: 100%;
            height: 40px;
            display: -webkit-box;
            display: -webkit-flex;
            display: -moz-box;
            display: -ms-flexbox;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0 25px;
            border-radius: 5px;
            -webkit-appearance: button;
            color: #fff !important;
            background: #ff5e3a !important;
            border-color: #ff5e3a !important;
        }
    </style>
</head>

<body>
    <?php include "./temp/navbar.php" ?>
    <section>
        <div class="common-background">
            <div class="container">
                <div class="about-page">
                    <h2>Enter OTP</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Enter OTP</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </section>
    <section>
        <div class="container">
            <div class="content-section">
                <div class="contact-form">
                    <div class="row">
                        <div class="col-12 col-md-4"></div>
                        <div class="col-12 col-md-4">
                            <div class="title">
                                <h2>Enter OTP</h2>
                            </div>
                            <?php 
                                $sessionMobile = $this->session->mobile;
                                $sessionEmail = $this->session->email;
                                $devOtp = $this->session->dev_otp;
                                $successMsg = $this->session->flashdata('msg') ?: $this->session->flashdata('success_msg');
                                $errMsg = $this->session->flashdata('err_msg') ?: $this->session->flashdata('logerr');
                                $isLocalDev = in_array($_SERVER['HTTP_HOST'] ?? '', array('localhost','127.0.0.1','::1'));
                            ?>

                            <?php if(!empty($devOtp)): ?>
                                <script>
                                    console.log('OTP: <?= htmlspecialchars($devOtp, ENT_QUOTES, 'UTF-8'); ?>');
                                </script>
                            <?php endif; ?>

                            <?php if(!empty($successMsg)): ?>
                                <div class="alert alert-success text-center py-2 mb-3" role="alert">
                                    <i class="fa fa-check-circle me-1"></i> <?= htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php endif; ?>

                            <?php if(!empty($errMsg)): ?>
                                <div class="alert alert-danger text-center py-2 mb-3" role="alert">
                                    <i class="fa fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php endif; ?>

                            <?php if(!empty($sessionMobile) || !empty($sessionEmail)): ?>
                                <div class="alert alert-info text-center py-2 mb-3 small">
                                    Please enter the 6-digit OTP sent to:
                                    <br>
                                    <?php if(!empty($sessionMobile)): ?>
                                        <strong>Mobile:</strong> <?= htmlspecialchars(substr($sessionMobile, 0, 3) . '****' . substr($sessionMobile, -3), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                    <?php if(!empty($sessionMobile) && !empty($sessionEmail)) echo " | "; ?>
                                    <?php if(!empty($sessionEmail)): ?>
                                        <strong>Email:</strong> <?= htmlspecialchars($sessionEmail, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning text-center py-2 mb-3 small">
                                    No active OTP session found. Please <a href="register.php" class="alert-link">Register</a> or request <a href="forgotpassword.php" class="alert-link">Forgot Password</a>.
                                </div>
                            <?php endif; ?>

                            <form action="login/verifyotp/" method="post" class="login100-form validate-form">
                                <div class="form-floating mb-3">
                                    <input type="text" name="otp" id="otp" placeholder="OTP" class="input100 border-start-0 form-control ms-0" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autofocus />
                                    <label for="otp">Enter 6-Digit OTP</label>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <input type="hidden" name="role" value="customer">
                                        <button type="submit" name="verifyotp" class="login-btn">Verify OTP</button>
                                    </div>
                                </div>
                            </form>

                            <?php if(!empty($sessionMobile)): ?>
                                <div class="text-center mt-3">
                                    <span class="text-muted small">Didn't receive the OTP?</span>
                                    <a href="login/resendotp" class="ms-1 fw-bold text-decoration-none" style="color: #ff5e3a;">Resend OTP</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
    <?php include "./temp/footer.php" ?>
    <?php include "./temp/vendor.php" ?>
</body>

</html>