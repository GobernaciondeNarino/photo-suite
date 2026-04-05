<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php settings_errors( 'foto_nano' ); ?>

<div class="foto-nano-section">
    <h2>Fondos Escenicos</h2>
    <p class="description">Configura los fondos disponibles para generar imagenes. Organiza por categorias: Paisajes, Templos o Sitios Antiguos.</p>

    <div id="foto-nano-fondos-list">
        <?php
        $fondos = $settings['fondos'] ?? array();
        if ( ! empty( $fondos ) ) :
            foreach ( $fondos as $i => $fondo ) :
        ?>
            <div class="foto-nano-item-card" data-index="<?php echo $i; ?>">
                <div class="foto-nano-item-header">
                    <strong><?php echo esc_html( $fondo['nombre'] ); ?></strong>
                    <span class="foto-nano-badge"><?php echo esc_html( ucfirst( $fondo['categoria'] ?? 'paisaje' ) ); ?></span>
                    <button type="button" class="button button-link-delete foto-nano-remove-item">Eliminar</button>
                </div>
                <table class="form-table">
                    <tr>
                        <th><label>Nombre</label></th>
                        <td><input type="text" name="fondo_nombre[]" value="<?php echo esc_attr( $fondo['nombre'] ); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label>Categoria</label></th>
                        <td>
                            <select name="fondo_categoria[]">
                                <option value="paisaje" <?php selected( $fondo['categoria'] ?? '', 'paisaje' ); ?>>Paisaje</option>
                                <option value="templo" <?php selected( $fondo['categoria'] ?? '', 'templo' ); ?>>Templo</option>
                                <option value="sitio-antiguo" <?php selected( $fondo['categoria'] ?? '', 'sitio-antiguo' ); ?>>Sitio Antiguo</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Imagen</label></th>
                        <td>
                            <input type="hidden" name="fondo_imagen[]" value="<?php echo esc_attr( $fondo['imagen'] ); ?>" class="foto-nano-image-input">
                            <div class="foto-nano-image-preview">
                                <?php if ( $fondo['imagen'] ) : ?>
                                    <img src="<?php echo esc_url( $fondo['imagen'] ); ?>" style="max-width:200px;">
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button foto-nano-upload-btn">Seleccionar Imagen</button>
                        </td>
                    </tr>
                </table>
            </div>
        <?php
            endforeach;
        endif;
        ?>
    </div>

    <button type="button" id="foto-nano-add-fondo" class="button button-secondary" style="margin-top:15px;">
        + Agregar Fondo
    </button>
</div>

<template id="foto-nano-fondo-template">
    <div class="foto-nano-item-card">
        <div class="foto-nano-item-header">
            <strong>Nuevo Fondo</strong>
            <button type="button" class="button button-link-delete foto-nano-remove-item">Eliminar</button>
        </div>
        <table class="form-table">
            <tr>
                <th><label>Nombre</label></th>
                <td><input type="text" name="fondo_nombre[]" value="" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label>Categoria</label></th>
                <td>
                    <select name="fondo_categoria[]">
                        <option value="paisaje">Paisaje</option>
                        <option value="templo">Templo</option>
                        <option value="sitio-antiguo">Sitio Antiguo</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>Imagen</label></th>
                <td>
                    <input type="hidden" name="fondo_imagen[]" value="" class="foto-nano-image-input">
                    <div class="foto-nano-image-preview"></div>
                    <button type="button" class="button foto-nano-upload-btn">Seleccionar Imagen</button>
                </td>
            </tr>
        </table>
    </div>
</template>
