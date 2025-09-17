jQuery(document).ready(function($){
  $('#sevenc-mwb-test-btn').on('click', function(){
    var number = $('#sevenc-mwb-test-number').val();
    if(!number) { alert('Ingresa un número con código de país'); return; }

    $('#sevenc-mwb-test-result').text('Enviando...');

    $.post(sevenc_mwb.ajaxurl, {
      action: 'sevenc_mwb_send_test',
      number: number,
      _ajax_nonce: sevenc_mwb.nonce
    })
    .done(function(resp){
      console.log("Respuesta completa:", resp); // <--- debug en consola
      if (resp.success) {
        $('#sevenc-mwb-test-result').text(
          JSON.stringify(resp.data, null, 2)
        );
      } else {
        $('#sevenc-mwb-test-result').text(
          "Error: " + (resp.data?.message || resp.message)
        );
      }
    })
    .fail(function(xhr, status, err){
      console.error("AJAX fail:", status, err, xhr.responseText);
      $('#sevenc-mwb-test-result').text("AJAX error: " + err);
    });
  });
});
