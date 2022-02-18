$(document).ready(function () {

    $('#formservices').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: $(this).attr('action')+'&action=bulk',
            type: 'POST',
            data: new FormData(this),
            contentType: false,
            processData: false,
            beforeSend: function () {
                $('#btnprocess').attr('disabled','disabled');
            },
            success: function(data) {
                
            },
            complete: function(response) {
                let data = response.responseJSON;
                if(data.status == 'true') {
                    $('.remaining').css({'display':'block'});
                    $('.alert-success').show()
                    $('.alert-success').html(data.message)
                    $('.total').html(data.total)
                    $('.totalremain').html(data.total)

                    var percentage = 0;
                    var kelipatan = 100 / data.total;
                    var sukses = 0;
                    var failed = 0;
                    $.each(data.services, function(index, item) { 
                        $.ajax({
                            url: $('#formservices').attr('action')+'&action=exec',
                            type:'POST',
                            async:true,
                            data: {
                                clientid:item.clientid,
                                plan:item.plan,
                                domain:item.domain,
                                invoice:item.invoice,
                                proses:item.proses,
                                doc:item.doc,
                                position:item.position
                            },
                            complete: function(res) {
                                percentage = percentage + kelipatan;
                                $('div.progress-bar').css('width', percentage+'%');
                                $('div.progress-bar').html(Math.floor(percentage)+'%');

                                $('.monitor').prepend(res.responseText).ready(function() {
                                    var remain = $('.totalremain').html();
                                    var upm = remain - 1;
                                    $('.totalremain').html(upm);

                                    var statuses = res.responseText;
                                    if(containsWord(statuses, "Failed") == true) {
                                        failed = failed + 1;
                                    }
                                    if(containsWord(statuses, "Success") == true) {
                                        sukses = sukses + 1;
                                    } 
                                })

                                if(percentage >= 100) {
                                    $('.suk').html(sukses)
                                    $('.fal').html(failed)
                                    $('.remaining').css({'display':'none'});
                                    $('.result').css({'display':'block'});

                                    $('.linkreport').attr('href', item.url+'downloads/bulkservices/'+item.doc);

                                }

                                

                            }
                        });
                    
                    });
                } else {
                    $('.alert-danger').show()
                    $('.alert-danger').html(data.message)
                }
            }
        });
    });

    function containsWord(str, word) {
        return str.match(new RegExp("\\b" + word + "\\b")) != null;
      }

})