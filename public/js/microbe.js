$(function(){
    let microbeId = $("#data-holder").attr("data-microbe-id")

    $("#new-metadata-button").click(function() {
        $("#new-metadata-button").hide()
        $("#new-metadata-form").show()

        $.get(
            '/api/metadata/load-missing/' + microbeId,
            function (response) {
                let optionsHtml = ''
                if (response.length === 0) {
                    optionsHtml += "<option value='X' disabled selected hidden>Žádná další metadata nejsou dostupná.</option>"
                } else {
                    optionsHtml += "<option value='X' disabled selected hidden>Vyberte typ metadat</option>"
                }
                response.forEach(function (metadataOption) {
                    optionsHtml += "<option value='" + metadataOption.id + "'>" + metadataOption.name + " (" + metadataOption.datatype + ")</option>\n"
                })
                $("#new-metadata-key").html(optionsHtml);
            }
        )
    })

    $("#new-metadata-key").change(function() {
        let keyId = $("#new-metadata-key").val();

        $.get(
            '/api/metadata/load-value-structure/' + keyId,
            function (response) {
                console.log(response)
            }
        )
    })
});