<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php settings_errors( 'foto_nano' ); ?>

<?php $providers_info = Foto_Nano_Api::get_providers_info(); ?>
<?php $active_provider = $settings['active_provider'] ?? 'replicate'; ?>
<?php $api_keys = $settings['api_keys'] ?? array(); ?>
<?php $provider_models = $settings['provider_models'] ?? array(); ?>

<div class="foto-nano-section">
    <h2><span class="dashicons dashicons-cloud" style="margin-right:6px;"></span> Proveedores de IA</h2>
    <p class="description" style="margin-bottom:15px;">Configura las API keys de los proveedores que deseas usar. Puedes tener multiples proveedores configurados y cambiar entre ellos.</p>

    <div class="foto-nano-provider-selector">
        <label for="active_provider"><strong>Proveedor Activo:</strong></label>
        <select id="active_provider" name="active_provider" class="foto-nano-provider-select">
            <?php foreach ( $providers_info as $pid => $pinfo ) : ?>
                <option value="<?php echo esc_attr( $pid ); ?>" <?php selected( $active_provider, $pid ); ?>>
                    <?php echo esc_html( $pinfo['name'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="foto-nano-providers-grid">
        <?php foreach ( $providers_info as $pid => $pinfo ) :
            $encrypted_key = $api_keys[ $pid ] ?? '';
            $decrypted_key = ! empty( $encrypted_key ) ? Foto_Nano_Security::decrypt( $encrypted_key ) : '';
            $has_key = ! empty( $decrypted_key );
            $is_active = ( $active_provider === $pid );
            $model_value = $provider_models[ $pid ] ?? '';
        ?>
            <div class="foto-nano-provider-card <?php echo $is_active ? 'active' : ''; ?> <?php echo $has_key ? 'configured' : ''; ?>" data-provider="<?php echo esc_attr( $pid ); ?>">
                <div class="foto-nano-provider-header">
                    <span class="dashicons <?php echo esc_attr( $pinfo['icon'] ); ?>"></span>
                    <strong><?php echo esc_html( $pinfo['name'] ); ?></strong>
                    <?php if ( $is_active ) : ?>
                        <span class="foto-nano-badge-active">ACTIVO</span>
                    <?php endif; ?>
                    <?php if ( $has_key ) : ?>
                        <span class="foto-nano-badge-configured">CONFIGURADO</span>
                    <?php endif; ?>
                </div>
                <p class="foto-nano-provider-desc"><?php echo esc_html( $pinfo['description'] ); ?></p>

                <div class="foto-nano-provider-fields">
                    <div class="foto-nano-field-group">
                        <label>API Key:</label>
                        <div class="foto-nano-key-wrapper">
                            <input type="password"
                                   name="api_keys[<?php echo esc_attr( $pid ); ?>]"
                                   value="<?php echo esc_attr( $decrypted_key ); ?>"
                                   class="regular-text foto-nano-api-key-input"
                                   autocomplete="new-password"
                                   placeholder="Ingresa tu API Key">
                            <button type="button" class="button foto-nano-toggle-key" title="Mostrar/Ocultar">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                        <?php if ( $has_key ) : ?>
                            <span class="foto-nano-key-status configured">Key configurada: <?php echo esc_html( Foto_Nano_Security::mask_api_key( $decrypted_key ) ); ?></span>
                        <?php endif; ?>
                        <a href="<?php echo esc_url( $pinfo['url'] ); ?>" target="_blank" class="foto-nano-get-key">Obtener API Key &rarr;</a>
                    </div>

                    <div class="foto-nano-field-group">
                        <label>Modelo (opcional):</label>
                        <input type="text"
                               name="provider_models[<?php echo esc_attr( $pid ); ?>]"
                               value="<?php echo esc_attr( $model_value ); ?>"
                               class="regular-text"
                               placeholder="<?php echo esc_attr( $this->get_default_model( $pid ) ); ?>">
                    </div>

                    <div class="foto-nano-provider-features">
                        <?php foreach ( $pinfo['features'] as $feature ) : ?>
                            <span class="foto-nano-feature-tag"><?php echo esc_html( $feature ); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="foto-nano-section">
    <h2><span class="dashicons dashicons-format-image" style="margin-right:6px;"></span> Formatos de Imagen</h2>
    <table class="form-table">
        <tr>
            <th>Formatos Habilitados</th>
            <td>
                <?php
                $formatos = $settings['formatos_habilitados'] ?? array( '1:1' );
                $all_formats = array(
                    '1:1'  => '1:1 (Cuadrado)',
                    '9:16' => '9:16 (Vertical/Stories)',
                    '16:9' => '16:9 (Horizontal/Paisaje)',
                    '4:3'  => '4:3 (Clasico)',
                    '3:4'  => '3:4 (Retrato)',
                );
                foreach ( $all_formats as $val => $label ) :
                    $key = 'formato_' . str_replace( ':', '_', $val );
                    $checked = in_array( $val, $formatos ) ? 'checked' : '';
                ?>
                    <label style="display:block;margin-bottom:5px;">
                        <input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php echo $checked; ?>>
                        <?php echo esc_html( $label ); ?>
                    </label>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr>
            <th><label for="formato_defecto">Formato por Defecto</label></th>
            <td>
                <select id="formato_defecto" name="formato_defecto">
                    <?php foreach ( $all_formats as $val => $label ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $settings['formato_defecto'] ?? '1:1', $val ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>
</div>

<div class="foto-nano-section">
    <h2><span class="dashicons dashicons-email" style="margin-right:6px;"></span> Configuracion de Email</h2>
    <table class="form-table">
        <tr>
            <th><label for="email_remitente">Email Remitente</label></th>
            <td>
                <input type="email" id="email_remitente" name="email_remitente"
                       value="<?php echo esc_attr( $settings['email_remitente'] ?? get_option( 'admin_email' ) ); ?>"
                       class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="email_asunto">Asunto del Correo</label></th>
            <td>
                <input type="text" id="email_asunto" name="email_asunto"
                       value="<?php echo esc_attr( $settings['email_asunto'] ?? '' ); ?>"
                       class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="email_plantilla">Plantilla HTML</label></th>
            <td>
                <textarea id="email_plantilla" name="email_plantilla" rows="6" class="large-text"><?php echo esc_textarea( $settings['email_plantilla'] ?? '' ); ?></textarea>
                <p class="description">HTML del cuerpo del correo. La imagen se adjunta automaticamente.</p>
            </td>
        </tr>
    </table>
</div>

<div class="foto-nano-section">
    <h2><span class="dashicons dashicons-shield" style="margin-right:6px;"></span> Seguridad y Limites</h2>
    <table class="form-table">
        <tr>
            <th><label for="limite_generaciones">Generaciones por sesion</label></th>
            <td>
                <input type="number" id="limite_generaciones" name="limite_generaciones"
                       value="<?php echo esc_attr( $settings['limite_generaciones'] ?? 10 ); ?>"
                       min="1" max="100" class="small-text">
            </td>
        </tr>
        <tr>
            <th><label for="rate_limit_per_hour">Rate limit por hora (por IP)</label></th>
            <td>
                <input type="number" id="rate_limit_per_hour" name="rate_limit_per_hour"
                       value="<?php echo esc_attr( $settings['rate_limit_per_hour'] ?? 20 ); ?>"
                       min="1" max="500" class="small-text">
                <p class="description">Maximo de solicitudes de generacion por IP por hora.</p>
            </td>
        </tr>
        <tr>
            <th><label for="max_upload_size">Tamano maximo de foto (MB)</label></th>
            <td>
                <input type="number" id="max_upload_size" name="max_upload_size"
                       value="<?php echo esc_attr( $settings['max_upload_size'] ?? 10 ); ?>"
                       min="1" max="50" class="small-text">
            </td>
        </tr>
        <tr>
            <th>Limpieza automatica</th>
            <td>
                <label>
                    <input type="checkbox" name="auto_cleanup" value="1" <?php checked( $settings['auto_cleanup'] ?? 1 ); ?>>
                    Limpiar archivos temporales automaticamente (cada hora)
                </label>
            </td>
        </tr>
    </table>
</div>

<div class="foto-nano-section">
    <h2><span class="dashicons dashicons-shortcode" style="margin-right:6px;"></span> Shortcode</h2>
    <p>Usa el shortcode <code>[foto_nano]</code> en cualquier pagina o entrada para mostrar la aplicacion.</p>
    <p>Tambien puedes especificar un proveedor: <code>[foto_nano provider="openai"]</code></p>
</div>

<?php
// Helper local para modelos por defecto
if ( ! method_exists( $this, 'get_default_model' ) ) {
    function foto_nano_get_default_model( $pid ) {
        $defaults = array(
            'replicate'   => 'lucataco/facefusion',
            'huggingface' => 'stabilityai/stable-diffusion-xl-base-1.0',
            'openai'      => 'gpt-image-1',
            'google'      => 'gemini-2.0-flash-exp',
            'anthropic'   => 'claude-sonnet-4-20250514',
        );
        return $defaults[ $pid ] ?? '';
    }
}
?>
