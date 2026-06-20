<?php
// Helper Integrasi API Fonnte untuk Pengiriman OTP WhatsApp

/**
 * Format nomor WhatsApp ke format internasional Indonesia (628xxxxxxxxxx)
 * 
 * @param string $phone
 * @return string
 */
function formatWhatsAppNumber($phone) {
    // Hapus semua karakter yang bukan angka
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Jika diawali dengan '0', ubah menjadi '62'
    if (strpos($phone, '0') === 0) {
        $phone = '62' . substr($phone, 1);
    }
    // Jika diawali dengan '8', ubah menjadi '628'
    elseif (strpos($phone, '8') === 0) {
        $phone = '62' . $phone;
    }
    
    return $phone;
}

/**
 * Mengirimkan pesan OTP via WhatsApp menggunakan API Fonnte
 * 
 * @param string $nomor_wa Nomor WhatsApp tujuan (format 628xxxxxxxxxx)
 * @param string $otp Kode OTP 6 digit
 * @return array
 */
function kirimOTPWhatsApp($nomor_wa, $otp) {
    // Muat konfigurasi token Fonnte
    $config_path = __DIR__ . '/fonnte.php';
    if (!file_exists($config_path)) {
        return [
            'success' => false,
            'message' => 'Berkas konfigurasi Fonnte tidak ditemukan.'
        ];
    }
    
    $config = require $config_path;
    $token = isset($config['token']) ? trim($config['token']) : '';
    
    if (empty($token) || $token === 'YOUR_FONNTE_TOKEN_HERE') {
        return [
            'success' => false,
            'message' => 'Token API Fonnte belum dikonfigurasi di config/fonnte.php.'
        ];
    }
    
    // Pesan OTP
    $message = "Kode OTP Olin's Cake Anda adalah: *{$otp}*.\n\nKode ini hanya berlaku selama 10 menit. Jangan sebarkan kode ini kepada siapapun demi keamanan akun Anda.";
    
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'target' => $nomor_wa,
            'message' => $message,
            'delay' => '2',
        ),
        CURLOPT_HTTPHEADER => array(
            "Authorization: " . $token
        ),
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        return [
            'success' => false,
            'message' => 'Kesalahan cURL: ' . $err
        ];
    }
    
    $result = json_decode($response, true);
    
    // Cek respons dari Fonnte
    if (isset($result['status']) && $result['status'] === true) {
        return [
            'success' => true,
            'message' => 'OTP berhasil dikirim ke nomor WhatsApp.'
        ];
    } else {
        $detail_err = isset($result['reason']) ? $result['reason'] : (isset($result['message']) ? $result['message'] : 'Gagal mengirim OTP.');
        return [
            'success' => false,
            'message' => 'Gagal mengirim WhatsApp: ' . $detail_err
        ];
    }
}

/**
 * Mengirimkan pesan WhatsApp umum menggunakan API Fonnte
 * 
 * @param string $nomor_wa Nomor WhatsApp tujuan
 * @param string $message Isi pesan WhatsApp
 * @return array
 */
function kirimPesanWhatsApp($nomor_wa, $message) {
    // Normalisasi format nomor WA
    $nomor_wa = formatWhatsAppNumber($nomor_wa);
    
    // Muat konfigurasi token Fonnte
    $config_path = __DIR__ . '/fonnte.php';
    if (!file_exists($config_path)) {
        return [
            'success' => false,
            'message' => 'Berkas konfigurasi Fonnte tidak ditemukan.'
        ];
    }
    
    $config = require $config_path;
    $token = isset($config['token']) ? trim($config['token']) : '';
    
    if (empty($token) || $token === 'YOUR_FONNTE_TOKEN_HERE') {
        return [
            'success' => false,
            'message' => 'Token API Fonnte belum dikonfigurasi di config/fonnte.php.'
        ];
    }
    
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'target' => $nomor_wa,
            'message' => $message,
            'delay' => '2',
        ),
        CURLOPT_HTTPHEADER => array(
            "Authorization: " . $token
        ),
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        return [
            'success' => false,
            'message' => 'Kesalahan cURL: ' . $err
        ];
    }
    
    $result = json_decode($response, true);
    
    // Cek respons dari Fonnte
    if (isset($result['status']) && $result['status'] === true) {
        return [
            'success' => true,
            'message' => 'Pesan WhatsApp berhasil dikirim.'
        ];
    } else {
        $detail_err = isset($result['reason']) ? $result['reason'] : (isset($result['message']) ? $result['message'] : 'Gagal mengirim pesan.');
        return [
            'success' => false,
            'message' => 'Gagal mengirim WhatsApp: ' . $detail_err
        ];
    }
}
?>
