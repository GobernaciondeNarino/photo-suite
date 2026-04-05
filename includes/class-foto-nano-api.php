<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * API Manager - Enruta las solicitudes al proveedor activo.
 */
class Foto_Nano_Api {

    private $provider;
    private $provider_id;

    /**
     * Proveedores disponibles y sus clases.
     */
    private static $providers = array(
        'replicate'   => 'Foto_Nano_Provider_Replicate',
        'huggingface' => 'Foto_Nano_Provider_HuggingFace',
        'openai'      => 'Foto_Nano_Provider_OpenAI',
        'google'      => 'Foto_Nano_Provider_Google',
        'anthropic'   => 'Foto_Nano_Provider_Anthropic',
    );

    /**
     * Informacion de los proveedores.
     */
    public static function get_providers_info() {
        return array(
            'replicate' => array(
                'name'        => 'Replicate',
                'description' => 'Face-swap con modelos como FaceFusion. Ideal para intercambio de rostros.',
                'url'         => 'https://replicate.com/account/api-tokens',
                'features'    => array( 'face-swap', 'img2img' ),
                'icon'        => 'dashicons-controls-repeat',
            ),
            'huggingface' => array(
                'name'        => 'Hugging Face',
                'description' => 'Miles de modelos de IA gratuitos. Generacion y edicion de imagenes.',
                'url'         => 'https://huggingface.co/settings/tokens',
                'features'    => array( 'img2img', 'text2img', 'face-swap' ),
                'icon'        => 'dashicons-smiley',
            ),
            'openai' => array(
                'name'        => 'OpenAI',
                'description' => 'GPT Image y DALL-E. Edicion avanzada de imagenes con IA.',
                'url'         => 'https://platform.openai.com/api-keys',
                'features'    => array( 'img2img', 'text2img', 'edit' ),
                'icon'        => 'dashicons-superhero-alt',
            ),
            'google' => array(
                'name'        => 'Google AI',
                'description' => 'Gemini con generacion de imagenes. Vision y creatividad multimodal.',
                'url'         => 'https://aistudio.google.com/apikey',
                'features'    => array( 'img2img', 'text2img', 'vision' ),
                'icon'        => 'dashicons-google',
            ),
            'anthropic' => array(
                'name'        => 'Claude (Anthropic)',
                'description' => 'Claude con vision y generacion de imagenes. Analisis inteligente.',
                'url'         => 'https://console.anthropic.com/settings/keys',
                'features'    => array( 'vision', 'img2img' ),
                'icon'        => 'dashicons-visibility',
            ),
        );
    }

    /**
     * Constructor - inicializa el proveedor activo.
     *
     * @param string $provider_id ID del proveedor a usar.
     * @param string $api_key API key del proveedor.
     */
    public function __construct( $provider_id = null, $api_key = null ) {
        $settings = get_option( 'foto_nano_settings', array() );

        if ( ! $provider_id ) {
            $provider_id = $settings['active_provider'] ?? 'replicate';
        }

        if ( ! $api_key ) {
            $encrypted_key = $settings['api_keys'][ $provider_id ] ?? '';
            $api_key = Foto_Nano_Security::decrypt( $encrypted_key );
        }

        $this->provider_id = $provider_id;

        if ( isset( self::$providers[ $provider_id ] ) ) {
            $class = self::$providers[ $provider_id ];
            $this->provider = new $class( $api_key );
        } else {
            $this->provider = new Foto_Nano_Provider_Replicate( $api_key );
        }
    }

    /**
     * Crear prediccion en el proveedor activo.
     */
    public function create_prediction( $source_face_path, $target_image_path, $params = array() ) {
        $settings = get_option( 'foto_nano_settings', array() );
        $params['model'] = $params['model'] ?? ( $settings['provider_models'][ $this->provider_id ] ?? '' );

        return $this->provider->create_prediction( $source_face_path, $target_image_path, $params );
    }

    /**
     * Obtener estado de prediccion.
     */
    public function get_prediction( $prediction_id ) {
        return $this->provider->get_prediction( $prediction_id );
    }

    /**
     * Verificar si el proveedor es sincrono.
     */
    public function is_synchronous() {
        return $this->provider->is_synchronous();
    }

    /**
     * Validar API key del proveedor actual.
     */
    public function validate_api_key() {
        return $this->provider->validate_api_key();
    }

    /**
     * Obtener ID del proveedor activo.
     */
    public function get_provider_id() {
        return $this->provider_id;
    }

    /**
     * Obtener nombre del proveedor activo.
     */
    public function get_provider_name() {
        return $this->provider->get_name();
    }

    /**
     * Obtener lista de proveedores con API key configurada.
     */
    public static function get_configured_providers() {
        $settings = get_option( 'foto_nano_settings', array() );
        $api_keys = $settings['api_keys'] ?? array();
        $configured = array();

        foreach ( $api_keys as $id => $encrypted_key ) {
            $key = Foto_Nano_Security::decrypt( $encrypted_key );
            if ( ! empty( $key ) ) {
                $configured[] = $id;
            }
        }

        return $configured;
    }
}
