(function($) {
    'use strict';

    $(document).ready(function() {

        // Media uploader para imagenes
        $(document).on('click', '.foto-nano-upload-btn', function(e) {
            e.preventDefault();
            var button = $(this);
            var inputField = button.siblings('.foto-nano-image-input');
            var previewDiv = button.siblings('.foto-nano-image-preview');

            var frame = wp.media({
                title: fotoNanoAdmin.selectImage,
                button: { text: fotoNanoAdmin.useImage },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                inputField.val(attachment.url);
                previewDiv.html('<img src="' + attachment.url + '" style="max-width:150px;">');
            });

            frame.open();
        });

        // Eliminar item
        $(document).on('click', '.foto-nano-remove-item', function(e) {
            e.preventDefault();
            $(this).closest('.foto-nano-item-card').slideUp(300, function() {
                $(this).remove();
            });
        });

        // Agregar mascota
        $('#foto-nano-add-mascota').on('click', function() {
            var template = document.getElementById('foto-nano-mascota-template');
            if (template) {
                var clone = template.content.cloneNode(true);
                $('#foto-nano-mascotas-list').append(clone);
            }
        });

        // Agregar fondo
        $('#foto-nano-add-fondo').on('click', function() {
            var template = document.getElementById('foto-nano-fondo-template');
            if (template) {
                var clone = template.content.cloneNode(true);
                $('#foto-nano-fondos-list').append(clone);
            }
        });

        // Agregar marco
        $('#foto-nano-add-marco').on('click', function() {
            var template = document.getElementById('foto-nano-marco-template');
            if (template) {
                var clone = template.content.cloneNode(true);
                $('#foto-nano-marcos-list').append(clone);
            }
        });

        // Range slider valor
        $(document).on('input', '.foto-nano-range', function() {
            $(this).siblings('.foto-nano-range-value').text($(this).val() + '%');
        });

        // Toggle API key visibility
        $(document).on('click', '.foto-nano-toggle-key', function() {
            var input = $(this).siblings('.foto-nano-api-key-input');
            var icon = $(this).find('.dashicons');
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                input.attr('type', 'password');
                icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });

        // Highlight active provider card when selector changes
        $('#active_provider').on('change', function() {
            var selected = $(this).val();
            $('.foto-nano-provider-card').removeClass('active');
            $('.foto-nano-badge-active').remove();
            var card = $('.foto-nano-provider-card[data-provider="' + selected + '"]');
            card.addClass('active');
            card.find('.foto-nano-provider-header strong').after('<span class="foto-nano-badge-active">ACTIVO</span>');
        });
    });

})(jQuery);
