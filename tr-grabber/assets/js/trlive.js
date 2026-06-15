jQuery(document).ready(function($) {
    if ( $( ".taxonomy-seasons #tag-name,.taxonomy-episodes #tag-name" ).length > 0 ) { $('#tag-name').val('@@@@@@@'); }
    if ( $( ".tr-grabber-type" ).length > 0 ) {
        var trgrabbertype = $('.tr-grabber-type').val();
        $('.search-form').append('<input type="hidden" name="tr_post_type" value="'+trgrabbertype+'">');
    }

    // select ajax
    
    var typingTimer;
    var doneTypingInterval = 500;
    var $input = $('.trselect_search_inp');

    $input.on('keyup', function (e) {
        if(e.keyCode != 13) {
            var type = $(this).data('type');
            $('.tr-grabber-suggest-content .dashicons').removeClass('dashicons-search').addClass('dashicons-update');
            clearTimeout(typingTimer);
            typingTimer = setTimeout(trdoneTyping(this), doneTypingInterval);
        }
    });

    $input.on('keydown', function (e) {
        if(e.keyCode != 13){
            clearTimeout(typingTimer);
        }
    });

    function trdoneTyping (var1) {
        
        var type = $(var1).data('type');
        var value = $('.trselect_search_inp').val();
        var count = value.length;

        if(count<3) {
            $('.trselectcnt').hide();
            $('.tr-grabber-suggest-content .dashicons').addClass('dashicons-search').removeClass('dashicons-update');
        }

        if(count>=3) {
            
            $.ajax({
              method: "POST",
              url: trgrabberlive.url,
              data: { nonce: trgrabberlive.nonce, action: 'trgrabberlive', value: value }
            }).done(function( html ) {
                $('.tr-grabber-suggest-content .dashicons').addClass('dashicons-search').removeClass('dashicons-update');
                $('.trselectcnt').show().html(html);
            });

        }

    }
    
    $(document.body).on('click', '.trselect li' ,function() {
        var title = $(this).data('val');
        var id = $(this).data('value');
        
        $('.trselect_search_inp').val(title);     
           
        $('input[name="serie_id"]').val(id);
        $('input[name="tag-name"]').val(title);

        trcloselive();
    });
    
    window.onload = function () {
        tinymce.get('content').on('keyup',function(e){
            $('#overview').val(this.getContent());
        });
        tinymce.get('content').on('change',function(e){
            $('#overview').val(this.getContent());
        });
    }
    
    // valid form season
    
    $(document.body).on('click', '.tr-grabber-tax-valid-form' ,function() {
        
        $('html, body').animate({
            scrollTop: $("#addtag").offset().top
        }, 2000);
        
        $('.grabber_error').remove();
                      
        $.ajax({
          method: "POST",
          url: trgrabberlive.url,
          data: $("#addtag").serialize()+"&action=trgrabberlive",
        }).done(function( html ) {
                        
            if( html == '' ) {
                                
                $('#submit').click();
                
                $('#addtag input[type="text"],#addtag input[type="number"],#addtag input[type="date"],#addtag textarea,#tr-grabber-media-content').val('');
                $('#image').html('');
                $('.trgrabber-media-tax-delete').hide();
                tinyMCE.activeEditor.setContent('');
                
            }else{
                
                var myArray = JSON.parse(html);

                if( myArray.serie_id ) {
                    $('input[name="serie_id"]').before('<p class="error grabber_error msj_serieid">'+myArray.serie_id+'</p>');
                }

                if( myArray.season_number ) {
                    $('input[name="season_number"]').before('<p class="error grabber_error msj_serieseason">'+myArray.season_number+'</p>');
                }

                if( myArray.name ) {
                    $('input[name="tag-name"]').before('<p class="error grabber_error">'+myArray.name+'</p>');
                }
                
                if( myArray.tagname ) {
                    $('input[name="tag-name"]').before('<p class="error grabber_error">'+myArray.tagname+'</p>');
                }
                
            }
            
        });
        
    });
    
    // valid form episodes
    
    $(document.body).on('click', '.tr-grabber-tax-valid-form-episode' ,function() {
                
        $('html, body').animate({
            scrollTop: $("#addtag").offset().top
        }, 2000);
        
        $('.grabber_error').remove();
                      
        $.ajax({
          method: "POST",
          url: trgrabberlive.url,
          data: $("#addtag").serialize()+"&action=trgrabberlive&type=3",
        }).done(function( html ) {
                                    
            if( html == '' ) {
                                
                $('#submit').click();
                
                $('#addtag input[type="text"],#addtag input[type="number"],#addtag input[type="date"],#addtag textarea,#tr-grabber-media-content').val('');
                $('#image').html('');
                $('.trgrabber-media-tax-delete').hide();
                tinyMCE.activeEditor.setContent('');
                $('select[name="season_number"]').html('');
                
            }else{
                
                var myArray = JSON.parse(html);

                if( myArray.serie_id ) {
                    $('input[name="serie_id"]').before('<p class="error grabber_error msj_serieid">'+myArray.serie_id+'</p>');
                }
                
                if( myArray.name ) {
                    $('input[name="tag-name"]').before('<p class="error grabber_error">'+myArray.name+'</p>');
                }
                
                if( myArray.tagname ) {
                    $('input[name="tag-name"]').before('<p class="error grabber_error">'+myArray.tagname+'</p>');
                }
                
                if( myArray.season_number ) {
                    $('select[name="season_number"]').before('<p class="error grabber_error msj_serieseason">'+myArray.season_number+'</p>');
                }
                
                if( myArray.episode ) {
                    $('input[name="episode"]').before('<p class="error grabber_error msj_seriepisode">'+myArray.episode+'</p>');
                }
                
            }
            
        });
        
    });
    
    // change select seasons
    
    $( "#serie_id_grabber" ).keyup(function() {
        
        $('select[name="season_number"]').html('<option value="">'+trgrabberlive.loading+'</option>');
        
        var value = $(this).val();
        
        $.ajax({
          method: "POST",
          url: trgrabberlive.url,
          data: { nonce: trgrabberlive.nonce, action: 'trgrabberlive', value: value, type: 4 }
        }).done(function( html ) {
            $('select[name="season_number"]').html(html);
        });
        
    });
    
    $(document.body).on('click', '.trselectseasons .trselect li' ,function() {
        
        $('select[name="season_number"]').html('<option value="">'+trgrabberlive.loading+'</option>');
        
        var value = $('#serie_id_grabber').val();
        
        $.ajax({
          method: "POST",
          url: trgrabberlive.url,
          data: { nonce: trgrabberlive.nonce, action: 'trgrabberlive', value: value, type: 4 }
        }).done(function( html ) {
            $('select[name="season_number"]').html(html);
        });
        
    });
    
    function trcloselive(){
        $('.trselectcnt').html('').hide();
        $('.trselect_search_inp').val('');
    }
    
    $(document).on('click', function(e){
        if ($(e.target).closest('.term-episode-wrap').length === 0) {
            trcloselive();
        }
    });
    
});