<?php
if (!function_exists('base_url')) {
   function base_url($uri)
   {
      return "https://apnotax.com/" . $uri;
   }
}
// Single active nav item from REQUEST_URI (PHP_SELF is always index.php in CI — never use it for HOME).
$navPath = '';
if (!empty($_SERVER['REQUEST_URI'])) {
   $navPath = strtok($_SERVER['REQUEST_URI'], '?');
}
$navActive = '';
if (preg_match('#/(?:login|register|enterotp|forgotpassword|resetpassword)\.php$#i', $navPath)) {
   $navActive = 'customer_login';
} elseif (preg_match('#(?:/index\.php)?/login/?$#i', $navPath) && !preg_match('#login\.php$#i', $navPath)) {
   $navActive = 'employee_login';
} elseif (preg_match('#about\.php$#i', $navPath)) {
   $navActive = 'about';
} elseif (preg_match('#calculator\.php$#i', $navPath)) {
   $navActive = 'calculator';
} elseif (preg_match('#contact\.php$#i', $navPath)) {
   $navActive = 'contact';
} elseif (preg_match('#(?:gst-accountancy|premium|prime)\.php$#i', $navPath)) {
   $navActive = 'accountancy';
} elseif ($navPath === '/' || preg_match('#/index\.php/?$#i', $navPath) || preg_match('#^/[^/]+/?$#', rtrim($navPath, '/'))) {
   $navActive = 'home';
}
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($basePath === '.' || $basePath === '' || $basePath === '/') {
   $basePath = '';
}
?>
<header>
   <div class="header-bg">
      <div class="container">
         <div class="row">
            <div class="col-lg-6 col-md-6">
               <ul class="left-top-menu">
                  <li>
                     <div class="top-icon">
                        <i class="fa fa-phone" aria-hidden="true"></i>
                        <span>+91 91539 92348</span>
                     </div>
                  </li>
                  <li>|</li>
                  <li>
                     <div class="top-icon">
                        <i class="fa fa-envelope" aria-hidden="true"></i>
                        <span>apnotax@gmail.com</span>
                     </div>
                  </li>
               </ul>
            </div>
            <div class="col-lg-6 col-md-6 d-none d-lg-block d-md-block">
               <div class="top-social-icon">
                  <ul class="top-social-media">
                     <li class="follow">Follow Us</li>
                     <li>
                        <a href="#">
                           <i class="fab fa-youtube" aria-hidden="true"></i>
                        </a>
                     </li>
                     <li><a href="#"><i class="fab fa-facebook-f" aria-hidden="true"></i></a></li>
                     <li><a href="#"><i class="fab fa-instagram" aria-hidden="true"></i></a></li>
                  </ul>
               </div>
            </div>
         </div>
      </div>
   </div>
</header>
<nav class="navbar navbar-expand-lg p-0 taxefiNavbar">
   <div class="container">
      <a class="navbar-brand" href="index.php"><img src="./images/logo.png" alt="ApnoTax Logo"></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
         <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
         <ul class="navbar-nav mx-auto mb-2 mb-lg-0 nav-taxefi">
            <li class="nav-item">
               <a class="nav-link <?php echo $navActive === 'home' ? 'active' : ''; ?>" <?php echo $navActive === 'home' ? 'aria-current="page"' : ''; ?> href="index.php">HOME</a>
            </li>
            <li class="nav-item">
               <a class="nav-link <?php echo $navActive === 'about' ? 'active' : ''; ?>" <?php echo $navActive === 'about' ? 'aria-current="page"' : ''; ?> href="about.php">ABOUT</a>
            </li>
            <li class="nav-item dropdown">
               <a class="nav-link dropdown-toggle <?php echo $navActive === 'accountancy' ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" <?php echo $navActive === 'accountancy' ? 'aria-current="page"' : ''; ?>>
                  ACCOUNTANCY
               </a>
               <ul class="dropdown-menu taxefi-dropdown">
                  <li>
                     <a class="dropdown-item taxefi-item" href="gst-accountancy.php">
                        Gst Accountancy
                     </a>
                  </li>
                  <li>
                     <a class="dropdown-item taxefi-item" href="#">
                        IT Accountancy &raquo;
                     </a>
                     <ul class="dropdown-menu dropdown-submenu">
                        <li>
                           <a class="dropdown-item taxefi-item" href="premium.php">Premium</a>
                        </li>
                        <li>
                           <a class="dropdown-item taxefi-item" href="prime.php">Prime</a>
                        </li>
                     </ul>
                  </li>
               </ul>
            </li>
            <li class="nav-item">
               <a class="nav-link <?php echo $navActive === 'calculator' ? 'active' : ''; ?>" <?php echo $navActive === 'calculator' ? 'aria-current="page"' : ''; ?> href="calculator.php">ACCOUNTING CALCULATOR</a>
            </li>
            <li class="nav-item">
               <a class="nav-link <?php echo $navActive === 'customer_login' ? 'active' : ''; ?>" <?php echo $navActive === 'customer_login' ? 'aria-current="page"' : ''; ?> href="login.php">CUSTOMER LOGIN</a>
            </li>
            <li class="nav-item">
               <a class="nav-link <?php echo $navActive === 'employee_login' ? 'active' : ''; ?>"
                  <?php echo $navActive === 'employee_login' ? 'aria-current="page"' : ''; ?>
                  href="<?= $basePath; ?>/login/">EMPLOYEE LOGIN</a>
            </li>
            <li class="nav-item">
               <a class="nav-link <?php echo $navActive === 'contact' ? 'active' : ''; ?>" <?php echo $navActive === 'contact' ? 'aria-current="page"' : ''; ?> href="contact.php">CONTACT US</a>
            </li>
         </ul>
         <div class="taxefi-btn d-flex">
            <a class="btn btn-dark download-app-btn" href="<?= base_url('download/apnotax-latest.apk'); ?>" target="_blank">DOWNLOAD APP</a>
         </div>
      </div>
   </div>
</nav>