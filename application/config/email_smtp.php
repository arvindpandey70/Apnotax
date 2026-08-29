<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * SMTP for sendemail() helper. Used when getenv('SMTP_HOST') is empty
 * (typical on shared hosting where .env is not loaded into PHP).
 *
 * Fill in smtp_* and mail_from_* on the live server (or set SMTP_* in the web server env).
 * For Gmail: App Password required (Google Account → Security → 2-Step → App passwords).
 */
$config['smtp_host'] = 'smtp.gmail.com';
$config['smtp_port'] = '587';
$config['smtp_user'] = 'apnotax@gmail.com';
$config['smtp_pass'] = 'spcidtkyxyxvucvq';
$config['smtp_crypto'] = 'tls';
$config['smtp_timeout'] = '30';
$config['mail_from_email'] = 'apnotax@gmail.com';
$config['mail_from_name'] = 'ApnoTax';
$config['mail_protocol'] = 'smtp';

// Optional overrides on server only (add to .gitignore if it contains secrets)
if (is_readable(APPPATH . 'config/email_smtp_local.php')) {
	include APPPATH . 'config/email_smtp_local.php';
}
