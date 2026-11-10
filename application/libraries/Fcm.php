<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Firebase Cloud Messaging — HTTP v1 (service account) with legacy fallback.
 */
class Fcm
{
    /** @var CI_Controller */
    protected $CI;

    /** @var bool */
    protected $use_http_v1 = true;

    /** @var bool */
    protected $enable_legacy_fallback = false;

    /** @var string */
    protected $server_key = '';

    /** @var string */
    protected $project_id = '';

    /** @var string */
    protected $service_account_path = '';

    /** @var string */
    protected $android_channel_id = 'default';

    /** @var string */
    protected $scope = 'https://www.googleapis.com/auth/firebase.messaging';

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->config->load('fcm', true);
        $this->use_http_v1 = (bool) $this->CI->config->item('fcm_use_http_v1', 'fcm');
        $this->enable_legacy_fallback = (bool) $this->CI->config->item('fcm_enable_legacy_fallback', 'fcm');
        $this->server_key = (string) $this->CI->config->item('fcm_server_key', 'fcm');
        $this->project_id = (string) $this->CI->config->item('fcm_project_id', 'fcm');
        $this->service_account_path = (string) $this->CI->config->item('fcm_service_account_path', 'fcm');
        $this->android_channel_id = (string) $this->CI->config->item('fcm_android_channel_id', 'fcm');
        $this->scope = (string) $this->CI->config->item('fcm_scope', 'fcm');
    }

    /**
     * Send to all active device tokens for a user.
     *
     * @param int         $user_id
     * @param string      $title
     * @param string      $body
     * @param array       $data  All values coerced to strings (FCM requirement).
     * @return bool       True if at least one request was sent without transport error
     */
    public function send_to_user($user_id, $title, $body, $data = array())
    {
        $user_id = (int) $user_id;
        if ($user_id < 1) {
            return false;
        }

        $this->CI->db->select('regid');
        $this->CI->db->where('user_id', $user_id);
        $this->CI->db->where('status', 1);
        $this->CI->db->where('regid !=', '');
        $rows = $this->CI->db->get('tokens')->result_array();
        if (empty($rows)) {
            return false;
        }

        $tokens = array();
        foreach ($rows as $row) {
            if (!empty($row['regid'])) {
                $tokens[] = $row['regid'];
            }
        }
        $tokens = array_values(array_unique($tokens));
        if (empty($tokens)) {
            return false;
        }

        $title = $this->_truncate($title, 200);
        $body  = $this->_truncate($body, 2000);

        $data_strings = array(
            'title' => $title,
            'body'  => $this->_truncate($body, 1000),
        );
        foreach ($data as $k => $v) {
            $data_strings[(string) $k] = (string) $v;
        }

        if ($this->use_http_v1) {
            $ok = $this->_send_to_user_http_v1($tokens, $title, $body, $data_strings);
            if ($ok === true) {
                return true;
            }

            // Legacy fallback explicitly disabled (by default).
            if ($this->enable_legacy_fallback !== true) {
                return false;
            }
        }

        // Legacy fallback
        if ($this->server_key === '') {
            return false;
        }

        return $this->_send_to_user_legacy($tokens, $title, $body, $data_strings);
    }

    private function _send_to_user_http_v1($tokens, $title, $body, $data_strings)
    {
        $serviceAccount = $this->_load_service_account();
        if (empty($serviceAccount)) {
            return false;
        }

        $projectId = !empty($this->project_id) ? $this->project_id : (string)($serviceAccount['project_id'] ?? '');
        if ($projectId === '') {
            return false;
        }

        $accessToken = $this->_get_http_v1_access_token($serviceAccount);
        if (empty($accessToken)) {
            return false;
        }

        $ok = false;
        foreach ($tokens as $token) {
            $payload = array(
                'message' => array(
                    'token' => $token,
                    'notification' => array(
                        'title' => $title,
                        'body'  => $body,
                    ),
                    'data' => $data_strings,
                    'android' => array(
                        'priority' => 'HIGH',
                        'notification' => array(
                            'channel_id' => $this->android_channel_id,
                        ),
                    ),
                ),
            );

            $endpoint = 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send';
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, array(
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => array(
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                ),
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT        => 5,
            ));

            $resp = curl_exec($ch);
            $err  = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($err) {
                log_message('error', 'FCM v1 curl error: ' . $err);
            } elseif ($code >= 200 && $code < 300) {
                $ok = true;
            } else {
                log_message('error', 'FCM v1 HTTP ' . $code . ' response: ' . substr((string) $resp, 0, 500));
            }
        }

        return $ok;
    }

    private function _send_to_user_legacy($tokens, $title, $body, $data_strings)
    {
        $ok = false;
        foreach (array_chunk($tokens, 1000) as $chunk) {
            $payload = array(
                'registration_ids' => $chunk,
                'notification'     => array(
                    'title' => $title,
                    'body'  => $body,
                    'sound' => 'default',
                ),
                'data' => $data_strings,
            );

            $ch = curl_init('https://fcm.googleapis.com/fcm/send');
            curl_setopt_array($ch, array(
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => array(
                    'Authorization: key=' . $this->server_key,
                    'Content-Type: application/json',
                ),
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT        => 5,
            ));
            $resp = curl_exec($ch);
            $err  = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($err) {
                log_message('error', 'FCM legacy curl error: ' . $err);
            } elseif ($code >= 200 && $code < 300) {
                $ok = true;
            } else {
                log_message('error', 'FCM legacy HTTP ' . $code . ' response: ' . substr((string) $resp, 0, 500));
            }
        }

        return $ok;
    }

    private function _load_service_account()
    {
        if (!is_file($this->service_account_path)) {
            log_message('error', 'FCM service account file not found: ' . $this->service_account_path);
            return array();
        }

        $raw = file_get_contents($this->service_account_path);
        if ($raw === false) {
            log_message('error', 'FCM service account file read failed: ' . $this->service_account_path);
            return array();
        }

        $json = json_decode($raw, true);
        if (!is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            log_message('error', 'FCM service account JSON invalid: ' . $this->service_account_path);
            return array();
        }

        return $json;
    }

    private function _get_http_v1_access_token($serviceAccount)
    {
        // Prefer official Google client library for service-account assertion flow.
        if (class_exists('\\Google\\Client')) {
            try {
                $client = new \Google\Client();
                $client->setAuthConfig($serviceAccount);
                $client->setScopes(array($this->scope));
                $tokenData = $client->fetchAccessTokenWithAssertion();
                if (is_array($tokenData) && !empty($tokenData['access_token'])) {
                    return (string) $tokenData['access_token'];
                }
                log_message('error', 'FCM v1 token missing in Google client response: ' . substr(json_encode($tokenData), 0, 300));
            } catch (\Throwable $e) {
                log_message('error', 'FCM v1 Google client token error: ' . $e->getMessage());
            }
        }

        // Fallback: manual JWT assertion flow.
        $clientEmail = (string) ($serviceAccount['client_email'] ?? '');
        $privateKey  = (string) ($serviceAccount['private_key'] ?? '');
        if ($clientEmail === '' || $privateKey === '') {
            return '';
        }

        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $now = time();
        $aud = 'https://oauth2.googleapis.com/token';

        $claims = array(
            'iss' => $clientEmail,
            'scope' => $this->scope,
            'aud' => $aud,
            'iat' => $now,
            'exp' => $now + 3600,
        );

        $jwt = $this->_create_jwt_rs256($claims, $privateKey);
        if ($jwt === '') {
            return '';
        }

        $postBody = 'grant_type=urn%3Aietf%3Aparams%3Aoauth%3Agrant-type%3Ajwt-bearer&assertion=' . rawurlencode($jwt);
        $ch = curl_init($tokenUrl);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/x-www-form-urlencoded'),
            CURLOPT_POSTFIELDS     => $postBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ));

        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            log_message('error', 'FCM v1 token curl error: ' . $err);
            return '';
        }
        if ($code < 200 || $code >= 300) {
            log_message('error', 'FCM v1 token HTTP ' . $code . ' response: ' . substr((string) $resp, 0, 300));
            return '';
        }

        $data = json_decode((string) $resp, true);
        if (!is_array($data) || empty($data['access_token'])) {
            log_message('error', 'FCM v1 token response missing access_token');
            return '';
        }
        return (string) $data['access_token'];
    }

    private function _create_jwt_rs256($claims, $privateKey)
    {
        $header = array('alg' => 'RS256', 'typ' => 'JWT');

        $encodedHeader = $this->_base64url_encode(json_encode($header));
        $encodedClaims = $this->_base64url_encode(json_encode($claims));
        $toSign = $encodedHeader . '.' . $encodedClaims;

        $signature = '';
        $ok = openssl_sign($toSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            log_message('error', 'FCM v1 JWT signing failed (openssl_sign).');
            return '';
        }

        return $toSign . '.' . $this->_base64url_encode($signature);
    }

    private function _base64url_encode($data)
    {
        if (!is_string($data)) {
            $data = (string) $data;
        }
        $b64 = base64_encode($data);
        $b64 = str_replace(array('+', '/', '='), array('-', '_', ''), $b64);
        return $b64;
    }

    private function _truncate($str, $max)
    {
        if (!is_string($str)) {
            $str = (string) $str;
        }
        if (function_exists('mb_substr')) {
            return mb_substr($str, 0, $max, 'UTF-8');
        }
        return substr($str, 0, $max);
    }
}
