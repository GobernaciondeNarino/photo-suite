<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Foto_Nano_Provider_Google extends Foto_Nano_Provider_Base {

    protected $provider_id = 'google';
    protected $provider_name = 'Google AI';
    private $base_url = 'https://generativelanguage.googleapis.com/v1beta';

    public function create_prediction( $source_face_path, $target_image_path, $params = array() ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', 'API Key de Google AI no configurada.' );
        }

        $model = $params['model'] ?? 'gemini-2.0-flash-exp';

        $source_base64 = $this->image_to_base64( $source_face_path );
        $target_base64 = $this->image_to_base64( $target_image_path );

        if ( ! $source_base64 || ! $target_base64 ) {
            return new WP_Error( 'image_error', 'Error al procesar las imagenes.' );
        }

        $source_mime = $this->get_mime_type( $source_face_path );
        $target_mime = $this->get_mime_type( $target_image_path );

        $prompt = $params['prompt'] ?? 'Take the person\'s face from the first image and seamlessly place it into the second image, maintaining natural lighting and proportions. Return only the final merged image.';

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array( 'text' => $prompt ),
                        array(
                            'inline_data' => array(
                                'mime_type' => $source_mime,
                                'data'      => $source_base64,
                            ),
                        ),
                        array(
                            'inline_data' => array(
                                'mime_type' => $target_mime,
                                'data'      => $target_base64,
                            ),
                        ),
                    ),
                ),
            ),
            'generationConfig' => array(
                'responseModalities' => array( 'image', 'text' ),
                'responseMimeType'   => 'image/png',
            ),
        );

        $url = $this->base_url . '/models/' . $model . ':generateContent?key=' . $this->api_key;

        $response = $this->make_request( $url, array(
            'method'  => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => 120,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $error_msg = $data['error']['message'] ?? 'Error desconocido de Google AI.';
            return new WP_Error( 'api_error', 'Google AI: ' . $error_msg );
        }

        // Buscar imagen en la respuesta
        $image_data = $this->extract_image_from_response( $data );

        if ( ! $image_data ) {
            return new WP_Error( 'api_error', 'Google AI no genero una imagen.' );
        }

        $saved = $this->save_generated_image( $image_data );
        if ( ! $saved ) {
            return new WP_Error( 'save_error', 'Error al guardar la imagen generada.' );
        }

        return array(
            'prediction_id' => $saved['filename'],
            'status'        => 'succeeded',
            'provider'      => $this->provider_id,
            'output'        => $saved['url'],
            'image_file'    => $saved['filename'],
            'synchronous'   => true,
        );
    }

    public function get_prediction( $prediction_id ) {
        return array(
            'status' => 'succeeded',
            'output' => null,
            'error'  => null,
        );
    }

    public function is_synchronous() {
        return true;
    }

    public function validate_api_key() {
        $url = $this->base_url . '/models?key=' . $this->api_key;
        $response = $this->make_request( $url, array( 'timeout' => 10 ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return wp_remote_retrieve_response_code( $response ) === 200;
    }

    private function extract_image_from_response( $data ) {
        if ( empty( $data['candidates'] ) ) {
            return null;
        }

        foreach ( $data['candidates'] as $candidate ) {
            if ( empty( $candidate['content']['parts'] ) ) {
                continue;
            }
            foreach ( $candidate['content']['parts'] as $part ) {
                if ( isset( $part['inline_data']['data'] ) ) {
                    return base64_decode( $part['inline_data']['data'] );
                }
            }
        }

        return null;
    }

    private function get_mime_type( $path ) {
        $info = getimagesize( $path );
        return $info ? $info['mime'] : 'image/jpeg';
    }

    private function save_generated_image( $image_data ) {
        $upload_dir = wp_upload_dir();
        $filename = 'foto_nano_google_' . uniqid() . '.png';
        $filepath = $upload_dir['basedir'] . '/foto-nano/generated/' . $filename;

        if ( file_put_contents( $filepath, $image_data ) === false ) {
            return false;
        }

        return array(
            'filename' => $filename,
            'path'     => $filepath,
            'url'      => $upload_dir['baseurl'] . '/foto-nano/generated/' . $filename,
        );
    }
}
