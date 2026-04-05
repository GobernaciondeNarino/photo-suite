<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Foto_Nano_Email {

    /**
     * Enviar imagen por correo electronico.
     */
    public function send( $to, $image_path ) {
        $settings = get_option( 'foto_nano_settings', array() );

        $subject = $settings['email_asunto'] ?? 'Tu foto personalizada - Foto-Nano';
        $from = $settings['email_remitente'] ?? get_option( 'admin_email' );
        $body_template = $settings['email_plantilla'] ?? '<h2>Aqui esta tu foto personalizada</h2><p>Gracias por usar Foto-Nano.</p>';

        $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
        $body .= '<div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;text-align:center;">';
        $body .= $body_template;
        $body .= '<p style="color:#888;font-size:12px;margin-top:30px;">Generado con Foto-Nano</p>';
        $body .= '</div></body></html>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Foto-Nano <' . $from . '>',
        );

        $attachments = array( $image_path );

        return wp_mail( $to, $subject, $body, $headers, $attachments );
    }
}
