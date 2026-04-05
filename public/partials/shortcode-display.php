<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="foto-nano-app" class="foto-nano-container">

    <!-- PANTALLA 1: Captura de Foto -->
    <div class="foto-nano-screen foto-nano-screen--active" data-screen="capture">
        <div class="foto-nano-header">
            <h2>Foto-Nano</h2>
            <p>Toma una foto para crear tu imagen personalizada con IA</p>
            <?php
            $settings = get_option( 'foto_nano_settings', array() );
            $provider = $settings['active_provider'] ?? 'replicate';
            $providers_info = Foto_Nano_Api::get_providers_info();
            $provider_name = $providers_info[ $provider ]['name'] ?? 'IA';
            ?>
            <div class="foto-nano-provider-badge">Potenciado por <?php echo esc_html( $provider_name ); ?></div>
        </div>

        <div class="foto-nano-camera-wrapper">
            <video id="foto-nano-video" autoplay playsinline></video>
            <canvas id="foto-nano-canvas" style="display:none;"></canvas>
            <div id="foto-nano-preview-img" class="foto-nano-preview-img" style="display:none;">
                <img id="foto-nano-captured" src="" alt="Foto capturada">
            </div>
        </div>

        <div class="foto-nano-camera-controls">
            <button type="button" id="foto-nano-btn-capture" class="foto-nano-btn foto-nano-btn--primary foto-nano-btn--round">
                <span class="foto-nano-icon-camera"></span>
            </button>
            <button type="button" id="foto-nano-btn-retake" class="foto-nano-btn foto-nano-btn--secondary" style="display:none;">
                Retomar Foto
            </button>
            <button type="button" id="foto-nano-btn-next-mode" class="foto-nano-btn foto-nano-btn--primary" style="display:none;">
                Continuar
            </button>
        </div>
    </div>

    <!-- PANTALLA 2: Seleccion de Modo -->
    <div class="foto-nano-screen" data-screen="mode">
        <div class="foto-nano-header">
            <h2>Elige tu estilo</h2>
            <p>Selecciona como quieres transformar tu imagen</p>
        </div>

        <div class="foto-nano-modes">
            <div class="foto-nano-mode-card" data-mode="mascota">
                <div class="foto-nano-mode-icon">&#128062;</div>
                <h3>Con Mascota</h3>
                <p>Tu foto junto a una adorable mascota</p>
            </div>

            <div class="foto-nano-mode-card" data-mode="fondo">
                <div class="foto-nano-mode-icon">&#127956;</div>
                <h3>Fondo Escenico</h3>
                <p>Tu foto en paisajes, templos o sitios historicos</p>
            </div>

            <div class="foto-nano-mode-card" data-mode="postal">
                <div class="foto-nano-mode-icon">&#9993;</div>
                <h3>Postal Personalizada</h3>
                <p>Una postal unica con tu nombre y marco</p>
            </div>
        </div>

        <button type="button" class="foto-nano-btn foto-nano-btn--back foto-nano-btn-back" data-back="capture">
            &larr; Volver a la camara
        </button>
    </div>

    <!-- PANTALLA 3: Opciones del modo -->
    <div class="foto-nano-screen" data-screen="options">
        <div class="foto-nano-header">
            <h2 id="foto-nano-options-title">Personaliza tu imagen</h2>
        </div>

        <!-- Selector de mascota -->
        <div id="foto-nano-opt-mascota" class="foto-nano-option-group" style="display:none;">
            <label>Selecciona una mascota:</label>
            <div class="foto-nano-grid" id="foto-nano-mascota-grid"></div>
        </div>

        <!-- Selector de fondo -->
        <div id="foto-nano-opt-fondo" class="foto-nano-option-group" style="display:none;">
            <label>Selecciona un fondo:</label>
            <div class="foto-nano-category-tabs" id="foto-nano-fondo-tabs"></div>
            <div class="foto-nano-grid" id="foto-nano-fondo-grid"></div>
        </div>

        <!-- Selector de marco (postal) -->
        <div id="foto-nano-opt-marco" class="foto-nano-option-group" style="display:none;">
            <label>Selecciona un marco:</label>
            <div class="foto-nano-grid" id="foto-nano-marco-grid"></div>
        </div>

        <!-- Nombre (postal) -->
        <div id="foto-nano-opt-nombre" class="foto-nano-option-group" style="display:none;">
            <label for="foto-nano-nombre">Tu nombre:</label>
            <input type="text" id="foto-nano-nombre" class="foto-nano-input" placeholder="Escribe tu nombre" maxlength="40" autocomplete="off">
        </div>

        <!-- Texto postal -->
        <div id="foto-nano-opt-texto" class="foto-nano-option-group" style="display:none;">
            <label for="foto-nano-texto-postal">Texto de la postal:</label>
            <input type="text" id="foto-nano-texto-postal" class="foto-nano-input" maxlength="80" autocomplete="off">
        </div>

        <!-- Selector de formato -->
        <div class="foto-nano-option-group">
            <label>Formato de imagen:</label>
            <div class="foto-nano-format-selector" id="foto-nano-format-selector"></div>
        </div>

        <div class="foto-nano-actions">
            <button type="button" class="foto-nano-btn foto-nano-btn--back foto-nano-btn-back" data-back="mode">
                &larr; Volver
            </button>
            <button type="button" id="foto-nano-btn-generate" class="foto-nano-btn foto-nano-btn--primary foto-nano-btn--large">
                Generar Imagen
            </button>
        </div>
    </div>

    <!-- PANTALLA 4: Generando -->
    <div class="foto-nano-screen" data-screen="generating">
        <div class="foto-nano-generating">
            <div class="foto-nano-spinner"></div>
            <h2>Generando tu imagen...</h2>
            <p id="foto-nano-generating-status">La IA esta trabajando en tu foto.</p>
            <div class="foto-nano-provider-badge">Procesando con <?php echo esc_html( $provider_name ); ?></div>
            <div class="foto-nano-progress">
                <div class="foto-nano-progress-bar" id="foto-nano-progress-bar"></div>
            </div>
        </div>
    </div>

    <!-- PANTALLA 5: Resultado -->
    <div class="foto-nano-screen" data-screen="result">
        <div class="foto-nano-header">
            <h2>Tu imagen esta lista</h2>
        </div>

        <div class="foto-nano-result-image">
            <img id="foto-nano-result-img" src="" alt="Imagen generada">
        </div>

        <div class="foto-nano-email-section">
            <label for="foto-nano-email">Enviar a tu correo electronico:</label>
            <div class="foto-nano-email-row">
                <input type="email" id="foto-nano-email" class="foto-nano-input" placeholder="tucorreo@ejemplo.com" autocomplete="email">
                <button type="button" id="foto-nano-btn-send-email" class="foto-nano-btn foto-nano-btn--primary">
                    Enviar
                </button>
            </div>
            <div id="foto-nano-email-status" class="foto-nano-email-status"></div>
        </div>

        <div class="foto-nano-actions">
            <a id="foto-nano-btn-download" class="foto-nano-btn foto-nano-btn--secondary" href="#" download="foto-nano.jpg">
                Descargar
            </a>
            <button type="button" id="foto-nano-btn-new" class="foto-nano-btn foto-nano-btn--primary">
                Nueva Foto
            </button>
        </div>
    </div>

    <!-- Error global -->
    <div id="foto-nano-error" class="foto-nano-error" style="display:none;">
        <p id="foto-nano-error-msg"></p>
        <button type="button" class="foto-nano-btn foto-nano-btn--secondary" onclick="document.getElementById('foto-nano-error').style.display='none'">
            Cerrar
        </button>
    </div>
</div>
